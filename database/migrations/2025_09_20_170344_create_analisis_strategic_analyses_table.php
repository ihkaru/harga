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
        Schema::create('analisis_strategic_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analisis_harga_id')->constrained('analisis_harga')->onDelete('cascade');
            $table->text('causal_hypothesis')->nullable();
            $table->text('potential_impact_framing')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analisis_strategic_analyses');
    }
};
