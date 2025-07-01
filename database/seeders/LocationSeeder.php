<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            CountrySeeder::class,
            RDCLocationSeeder::class,
        ]);
    }
}