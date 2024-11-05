<?php

namespace Database\Seeders;

use App\Services\KomoditasService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class KomoditasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $service = new KomoditasService();
        $service->syncDataKomoditas();
    }
}
