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
        Schema::create('analysis_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analisis_harga_id')->constrained('analisis_harga')->onDelete('cascade');
            $table->string('feedback_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_feedbacks');
    }
};
