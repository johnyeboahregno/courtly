<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe default: only the idempotent super-admin seeder runs.
     * DevelopmentSeeder (sample players/sessions) is opt-in via
     * `php artisan db:seed --class=DevelopmentSeeder`.
     */
    public function run(): void
    {
        $this->call(SuperAdminSeeder::class);
    }
}
