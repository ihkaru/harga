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
        Schema::create('analisis_stakeholder_considerations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analisis_harga_id')->constrained('analisis_harga')->onDelete('cascade');
            $table->text('for_dinas_perdagangan')->nullable();
            $table->text('for_dinas_pertanian')->nullable();
            $table->text('for_koordinator_tpid')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analisis_stakeholder_considerations');
    }
};
