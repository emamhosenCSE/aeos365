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
        // Run platform seeders for the central database
        $this->call([
            \Aero\Platform\Database\Seeders\PlatformDatabaseSeeder::class,
        ]);
    }
}
