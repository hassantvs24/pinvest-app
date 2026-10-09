<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * DefaultDataSeeder: permanent Bangla business defaults (owner +
 * partners + masters) — safe to re-run anytime.
 * DemoDataSeeder: demo cycles/pending — only on an empty database.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DefaultDataSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
