<?php

namespace Database\Seeders;

use App\Models\Harga;
use App\Models\Komoditas;
use App\Services\HargaService;
use App\Supports\Constants;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HargaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $service = new HargaService();
        $service->syncDataHargaGabungan();
    }
}
