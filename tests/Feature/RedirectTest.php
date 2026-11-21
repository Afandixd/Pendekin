<?php

namespace Tests\Feature;

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_active_link_redirects_to_target_url(): void
    {
        $targetUrl = 'https://google.com';

        Link::create([
            'code' => 'abc123',
            'target_url' => $targetUrl,
            'is_active' => true,
            'expires_at' => null,
        ]);

        $response = $this->get('/abc123');

        $response->assertStatus(302);
        $response->assertRedirect($targetUrl);
    }

    public function test_unknown_code_returns_404(): void
    {
        $response = $this->get('/unknown123');

        $response->assertNotFound();
    }

    public function test_inactive_link_returns_410(): void
    {
        Link::create([
            'code' => 'inactive123',
            'target_url' => 'https://google.com',
            'is_active' => false,
            'expires_at' => null,
        ]);

        $response = $this->get('/inactive123');

        $response->assertStatus(410);
    }

    public function test_expired_link_returns_410(): void
    {
        Link::create([
            'code' => 'expired123',
            'target_url' => 'https://google.com',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->get('/expired123');

        $response->assertStatus(410);
    }

    public function test_redirect_uses_cached_link_data(): void
    {
        $targetUrl = 'https://google.com';

        Link::create([
            'code' => 'cache123',
            'target_url' => $targetUrl,
            'is_active' => true,
            'expires_at' => null,
        ]);

        // Request pertama mengambil data dari database dan menyimpannya ke cache.
        $this->get('/cache123');

        // Ubah URL langsung di database.
        Link::where('code', 'cache123')->update([
            'target_url' => 'https://example.com',
        ]);

        // Request kedua seharusnya masih menggunakan data dari cache.
        $response = $this->get('/cache123');

        $response->assertStatus(302);
        $response->assertRedirect($targetUrl);
    }
}
