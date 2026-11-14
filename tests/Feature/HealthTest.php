<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class HealthTest extends TestCase
{
    public function test_health_returns_200_when_database_up(): void
    {
        $this->getJson('/health')
            ->assertStatus(200)
            ->assertJson(['database' => 'up']);
    }

    public function test_health_returns_503_when_database_down(): void
    {
        DB::shouldReceive('connection')->andThrow(new \Exception('down'));

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJson(['database' => 'down']);
    }
}
