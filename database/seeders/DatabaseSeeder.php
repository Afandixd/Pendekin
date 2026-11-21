<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Link;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User']
        );

        Link::updateOrCreate(
            ['code' => 'contoh'],
            [
                'target_url' => 'https://www.google.com',
                'is_active' => true,
                'expires_at' => null,
            ]
        );
    }
}