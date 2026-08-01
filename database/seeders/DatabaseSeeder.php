<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The demo accounts share one well known password, so they must never
        // be created on a production database.
        if (App::environment('production')) {
            return;
        }

        $this->call([
            UserSeeder::class,
            ProjectSeeder::class,
            TaskSeeder::class,
        ]);
    }
}
