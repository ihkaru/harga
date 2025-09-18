<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalisisHarga;
use App\Models\Komoditas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AnalisisHargaController extends Controller {
    /**
     * Menerima data analisis dari n8n dan menyimpannya ke database.
     */
    public function store(Request $request) {
        // 1. Validasi input utama (harus berupa array)
        $validator = Validator::make($request->all(), [
            '*.metadata.commodity_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $results = [];
        $errors = [];

        // Gunakan transaksi database untuk memastikan semua data berhasil disimpan
        DB::beginTransaction();
        try {
            foreach ($request->all() as $data) {
                // 2. Cari Komoditas berdasarkan nama
                $komoditas = Komoditas::where('nama', $data['metadata']['commodity_name'])->first();

                if (!$komoditas) {
                    $errors[] = "Komoditas '{$data['metadata']['commodity_name']}' tidak ditemukan.";
                    continue; // Lanjut ke item berikutnya
                }

                // 3. Simpan data utama ke tabel 'analisis_harga'
                $analisis = AnalisisHarga::create([
                    'komoditas_id' => $komoditas->id,

                    // Metadata
                    'commodity_category' => $data['metadata']['commodity_category'] ?? null,
                    'analysis_date' => $data['metadata']['analysis_date'] ?? null,
                    'data_freshness' => $data['metadata']['data_freshness'] ?? null,
                    'analysis_confidence' => $data['metadata']['analysis_confidence'] ?? null,

                    // Price Condition Assessment
                    'condition_level' => $data['price_condition_assessment']['condition_level'] ?? null,
                    'volatility_index' => $data['price_condition_assessment']['volatility_index'] ?? null,
                    'trend_direction' => $data['price_condition_assessment']['trend_direction'] ?? null,
                    'statistical_significance' => $data['price_condition_assessment']['statistical_significance'] ?? null,
                    'key_observation' => $data['price_condition_assessment']['key_observation'] ?? null,

                    // Data Insights
                    'current_position' => $data['data_insights']['current_position'] ?? null,
                    'price_pattern' => $data['data_insights']['price_pattern'] ?? null,
                    'volatility_analysis' => $data['data_insights']['volatility_analysis'] ?? null,
                    'trend_analysis' => $data['data_insights']['trend_analysis'] ?? null,

                    // Statistical Findings
                    'deviation_percentage' => $data['statistical_findings']['deviation_from_average']['percentage'] ?? null,
                    'deviation_interpretation' => $data['statistical_findings']['deviation_from_average']['interpretation'] ?? null,
                    'volatility_level' => $data['statistical_findings']['volatility_assessment']['level'] ?? null,
                    'volatility_cv_interpretation' => $data['statistical_findings']['volatility_assessment']['cv_interpretation'] ?? null,
                    'trend_strength' => $data['statistical_findings']['trend_strength']['strength'] ?? null,
                    'trend_consistency' => $data['statistical_findings']['trend_strength']['consistency'] ?? null,

                    // Forward Indicators
                    'short_term_outlook' => $data['forward_indicators']['short_term_outlook'] ?? null,
                    'pattern_sustainability' => $data['forward_indicators']['pattern_sustainability'] ?? null,

                    // Analysis Limitations
                    'external_factors_note' => $data['analysis_limitations']['external_factors_note'] ?? null,
                ]);

                // 4. Simpan data array ke tabel-tabel pendukung
                $this->saveRelatedData($analisis->dataBasedAlerts(), $data['potential_considerations']['data_based_alerts'] ?? []);
                $this->saveRelatedData($analisis->monitoringSuggestions(), $data['potential_considerations']['monitoring_suggestions'] ?? []);
                $this->saveRelatedData($analisis->patternImplications(), $data['potential_considerations']['pattern_implications'] ?? []);
                $this->saveRelatedData($analisis->keyMetricsToWatch(), $data['information_support']['key_metrics_to_watch'] ?? []);
                $this->saveRelatedData($analisis->dataQualityNotes(), $data['information_support']['data_quality_notes'] ?? []);
                $this->saveRelatedData($analisis->additionalDataSuggestions(), $data['information_support']['additional_data_suggestions'] ?? []);
                $this->saveRelatedData($analisis->statisticalWarnings(), $data['forward_indicators']['statistical_warnings'] ?? []);
                $this->saveRelatedData($analisis->dataConstraints(), $data['analysis_limitations']['data_constraints'] ?? []);
                $this->saveRelatedData($analisis->assumptionsMade(), $data['analysis_limitations']['assumptions_made'] ?? []);

                $results[] = "Analisis untuk '{$komoditas->nama}' berhasil disimpan.";
            }

            // Jika ada error, batalkan semua perubahan
            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Beberapa data gagal diproses.',
                    'success' => $results,
                    'errors' => $errors,
                ], 422);
            }

            // Jika semua berhasil, commit transaksi
            DB::commit();

            return response()->json([
                'message' => 'Semua data analisis berhasil disimpan.',
                'results' => $results
            ], 201);
        } catch (\Exception $e) {
            // Jika terjadi error tak terduga, batalkan transaksi
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan pada server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper function untuk menyimpan item dari array ke relasi.
     * @param \Illuminate\Database\Eloquent\Relations\HasMany $relation
     * @param array $items
     */
    private function saveRelatedData($relation, array $items) {
        if (empty($items)) return;

        $dataToInsert = [];
        foreach ($items as $item) {
            $dataToInsert[] = ['content' => $item];
        }
        $relation->createMany($dataToInsert);
    }

    /**
     * Mengambil data analisis untuk ditampilkan di frontend.
     */
    public function index() {
        $analisis = AnalisisHarga::with([
            'komoditas',
            'dataBasedAlerts',
            'monitoringSuggestions',
            'patternImplications',
            'keyMetricsToWatch',
            'dataQualityNotes',
            'additionalDataSuggestions',
            'statisticalWarnings',
            'dataConstraints',
            'assumptionsMade'
        ])
            ->latest() // Ambil yang terbaru
            ->get();

        return response()->json($analisis);
    }
}
