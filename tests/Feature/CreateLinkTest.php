<?php

namespace Tests\Feature;

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_tanpa_alias_menghasilkan_kode_acak_6_karakter(): void
    {
        $res = $this->postJson('/api/links', ['url' => 'https://tokoku.com/katalog']);

        $res->assertStatus(201);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{6}$/', $res->json('code'));
    }

    public function test_alias_kustom_diterima(): void
    {
        $this->postJson('/api/links', [
            'url' => 'https://tokoku.com',
            'alias' => 'promo-baju',
        ])->assertStatus(201)->assertJson(['code' => 'promo-baju']);
    }

    public function test_alias_duplikat_ditolak(): void
    {
        Link::create(['code' => 'promo', 'target_url' => 'https://a.com']);

        $this->postJson('/api/links', [
            'url' => 'https://b.com',
            'alias' => 'promo',
        ])->assertStatus(422);
    }

    public function test_alias_dengan_karakter_terlarang_ditolak(): void
    {
        foreach (['promo baju', 'Promo_2026', 'promo!'] as $alias) {
            $this->postJson('/api/links', [
                'url' => 'https://tokoku.com',
                'alias' => $alias,
            ])->assertStatus(422);
        }
    }

    public function test_alias_yang_bentrok_dengan_route_ditolak(): void
    {
        $this->postJson('/api/links', [
            'url' => 'https://tokoku.com',
            'alias' => 'health',
        ])->assertStatus(422);
    }

    public function test_url_tujuan_harus_http_atau_https(): void
    {
        foreach (['tokoku.com', 'ftp://tokoku.com'] as $url) {
            $this->postJson('/api/links', ['url' => $url])->assertStatus(422);
        }
    }
}