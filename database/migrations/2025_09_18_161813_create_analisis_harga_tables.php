<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        // Tabel utama untuk menyimpan inti dari setiap analisis komoditas
        Schema::create('analisis_harga', function (Blueprint $table) {
            $table->id();
            // Foreign key yang terhubung ke tabel komoditas Anda
            $table->foreignId('komoditas_id')->constrained('komoditas')->onDelete('cascade');

            // Kolom dari bagian 'metadata'
            $table->string('commodity_category')->nullable();
            $table->date('analysis_date')->nullable();
            $table->date('data_freshness')->nullable();
            $table->string('analysis_confidence')->nullable();

            // Kolom dari bagian 'price_condition_assessment'
            $table->string('condition_level')->nullable();
            $table->decimal('volatility_index', 8, 2)->nullable();
            $table->string('trend_direction')->nullable();
            $table->string('statistical_significance')->nullable();
            $table->text('key_observation')->nullable();

            // Kolom dari bagian 'data_insights'
            $table->text('current_position')->nullable();
            $table->text('price_pattern')->nullable();
            $table->text('volatility_analysis')->nullable();
            $table->text('trend_analysis')->nullable();

            // Kolom dari 'statistical_findings'
            $table->decimal('deviation_percentage', 8, 2)->nullable();
            $table->text('deviation_interpretation')->nullable();
            $table->string('volatility_level')->nullable();
            $table->text('volatility_cv_interpretation')->nullable();
            $table->string('trend_strength')->nullable();
            $table->string('trend_consistency')->nullable();

            // Kolom dari 'forward_indicators'
            $table->text('short_term_outlook')->nullable();
            $table->text('pattern_sustainability')->nullable();

            // Kolom dari 'analysis_limitations'
            $table->text('external_factors_note')->nullable();

            $table->timestamps();
        });

        // Tabel-tabel pendukung untuk menyimpan data yang berbentuk array
        // Setiap tabel ini akan memiliki relasi one-to-many dengan 'analisis_harga'

        $this->createRelatedTable('analisis_data_based_alerts');
        $this->createRelatedTable('analisis_monitoring_suggestions');
        $this->createRelatedTable('analisis_pattern_implications');
        $this->createRelatedTable('analisis_key_metrics_to_watch');
        $this->createRelatedTable('analisis_data_quality_notes');
        $this->createRelatedTable('analisis_additional_data_suggestions');
        $this->createRelatedTable('analisis_statistical_warnings');
        $this->createRelatedTable('analisis_data_constraints');
        $this->createRelatedTable('analisis_assumptions_made');
    }

    /**
     * Helper function untuk membuat tabel pendukung dengan struktur yang sama.
     */
    private function createRelatedTable(string $tableName): void {
        Schema::create($tableName, function (Blueprint $table) use ($tableName) {
            $table->id();
            $table->foreignId('analisis_harga_id')->constrained('analisis_harga')->onDelete('cascade');
            $table->text('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('analisis_data_based_alerts');
        Schema::dropIfExists('analisis_monitoring_suggestions');
        Schema::dropIfExists('analisis_pattern_implications');
        Schema::dropIfExists('analisis_key_metrics_to_watch');
        Schema::dropIfExists('analisis_data_quality_notes');
        Schema::dropIfExists('analisis_additional_data_suggestions');
        Schema::dropIfExists('analisis_statistical_warnings');
        Schema::dropIfExists('analisis_data_constraints');
        Schema::dropIfExists('analisis_assumptions_made');
        Schema::dropIfExists('analisis_harga');
    }
};
