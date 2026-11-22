<?php

// Run only on a development/validation stack: creates and removes unique fixtures.
// docker compose exec -T app php docker/smoke.php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function dockerSmokeCheck(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException($label);
    }

    echo "PASS: {$label}\n";
}

function dockerSmokeRequest(string $path, string $method = 'GET', ?array $payload = null): array
{
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => "Accept: application/json\r\nContent-Type: application/json\r\n",
        'content' => $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
        'follow_location' => 0,
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);
    $body = file_get_contents('http://127.0.0.1:8000'.$path, false, $context);
    $headers = $http_response_header ?? [];
    preg_match('/^HTTP\/\S+ (\d+)/', $headers[0] ?? '', $match);
    $location = null;
    foreach ($headers as $header) {
        if (stripos($header, 'Location:') === 0) {
            $location = trim(substr($header, 9));
        }
    }

    return [(int) ($match[1] ?? 0), $location, $body];
}

$prefix = 'docker-'.bin2hex(random_bytes(6));
$codes = [$prefix.'-active', $prefix.'-inactive', $prefix.'-expired', $prefix.'-missing'];
$exit = 0;

try {
    dockerSmokeCheck(config('database.default') === 'mysql', 'Laravel uses MySQL');
    dockerSmokeCheck(config('cache.default') === 'redis', 'Laravel uses Redis cache');
    dockerSmokeCheck(extension_loaded('redis'), 'phpredis extension loaded');
    DB::select('SELECT 1');
    $cache = Cache::store('redis');
    $store = $cache->getStore();
    $store->connection()->ping();
    $cache->put($prefix.'-probe', 'connected', 10);
    dockerSmokeCheck($cache->get($prefix.'-probe') === 'connected', 'Laravel Redis write/read');

    [$status, , $body] = dockerSmokeRequest('/health');
    dockerSmokeCheck($status === 200 && json_decode($body, true) === ['status' => 'ok', 'database' => 'up'], 'HTTP health 200 / database up');

    $original = 'https://example.com/docker-original';
    $updated = 'https://example.com/docker-updated';
    [$status, , $body] = dockerSmokeRequest('/api/links', 'POST', ['url' => $original, 'alias' => $codes[0]]);
    dockerSmokeCheck($status === 201 && (json_decode($body, true)['code'] ?? '') === $codes[0], 'API creates link in MySQL (201)');

    foreach ([1 => [false, null], 2 => [true, now()->subMinute()]] as $index => [$active, $expires]) {
        DB::table('links')->insert([
            'code' => $codes[$index], 'target_url' => $original,
            'is_active' => $active, 'expires_at' => $expires,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    [$status, $location] = dockerSmokeRequest('/'.$codes[0]);
    dockerSmokeCheck($status === 302 && $location === $original, 'Active redirect 302 with correct Location');
    $ttl = $store->connection()->ttl($store->getPrefix().'link:'.$codes[0]);
    dockerSmokeCheck($ttl > 0 && $ttl <= 60, 'Real Redis cache TTL is 1..60 seconds');

    DB::table('links')->where('code', $codes[0])->update(['target_url' => $updated]);
    [$status, $location] = dockerSmokeRequest('/'.$codes[0]);
    dockerSmokeCheck($status === 302 && $location === $original, 'Second HTTP request uses Redis cache after DB update');

    [$status] = dockerSmokeRequest('/'.$codes[3]);
    dockerSmokeCheck($status === 404, 'Unknown code 404');
    [$status] = dockerSmokeRequest('/'.$codes[1]);
    dockerSmokeCheck($status === 410, 'Inactive code 410');
    [$status] = dockerSmokeRequest('/'.$codes[2]);
    dockerSmokeCheck($status === 410, 'Expired code 410');

    echo "Waiting for the original 60-second cache TTL to expire...\n";
    $deadline = microtime(true) + 65;
    while ($cache->has('link:'.$codes[0]) && microtime(true) < $deadline) {
        sleep(1);
    }
    dockerSmokeCheck(! $cache->has('link:'.$codes[0]), 'Redis entry actually expires');
    [$status, $location] = dockerSmokeRequest('/'.$codes[0]);
    dockerSmokeCheck($status === 302 && $location === $updated, 'Redirect reloads MySQL value after cache expiry');
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: '.$error->getMessage()."\n");
    $exit = 1;
} finally {
    // Delete only this run's fixtures. Never flush Redis or reset the database.
    DB::table('links')->whereIn('code', $codes)->delete();
    foreach ($codes as $code) {
        Cache::store('redis')->forget('link:'.$code);
    }
    Cache::store('redis')->forget($prefix.'-probe');
}

exit($exit);
