<?php

namespace Database\Seeders;

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
        // Production users must be provisioned explicitly, never seeded from defaults.
        // $this->call(PerformanceHistorySeeder::class); // Manual local benchmark only; keep disabled for normal/server seeding.
    }
}
