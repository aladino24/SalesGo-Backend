<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Seeder development dilarang pada production.');
        }
        $this->call([
            DevelopmentSeeder::class,
            ProductCatalogSeeder::class,
            OutletShipToLocationSeeder::class,
        ]);
    }
}
