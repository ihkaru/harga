<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hargas', function (Blueprint $table) {
            $table->id();
            $table->string("id_komoditas_harian")->nullable();
            $table->string("id_komoditas_pekanan")->nullable();
            $table->string("id_komoditas_bulanan")->nullable();
            $table->string("id_pekan")->nullable();
            $table->string("tanggal")->nullable();
            $table->string("id_komoditas")->nullable();
            $table->string("tahun")->nullable();
            $table->string("bulan")->nullable();
            $table->string("tanggal_angka")->nullable();
            $table->decimal("harga", 15, 2)->nullable();
            $table->string("responden")->nullable();
            $table->string("kecamatan")->nullable();
            // $table->string("harga")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hargas');
    }
};
