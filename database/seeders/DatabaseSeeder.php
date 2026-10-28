<?php

namespace Database\Seeders;

use App\Services\WorldService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(WorldService::class)->seed();
    }
}
