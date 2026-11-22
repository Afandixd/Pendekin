<?php

// Container readiness: HTTP/MySQL and Laravel's configured Redis cache connection.
try {
    $context = stream_context_create(['http' => ['timeout' => 2]]);
    $body = @file_get_contents('http://127.0.0.1:8000/health', false, $context);
    $data = json_decode($body === false ? '' : $body, true);

    if (($data['status'] ?? null) !== 'ok' || ($data['database'] ?? null) !== 'up') {
        exit(1);
    }

    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    if (config('cache.default') !== 'redis') {
        exit(1);
    }

    $store = Illuminate\Support\Facades\Cache::store('redis')->getStore();
    $store->connection()->ping();
    exit(0);
} catch (Throwable) {
    // Do not expose connection credentials in Docker health logs.
    exit(1);
}
