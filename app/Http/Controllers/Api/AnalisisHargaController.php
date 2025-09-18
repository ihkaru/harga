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
     * Menerima dan menyimpan data analisis harga dari n8n.
     * Endpoint ini dirancang untuk menerima satu panggilan API dengan
     * payload JSON yang memiliki struktur: { "data": [ ... ] }.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        // 1. Validasi input, memastikan 'data' ada dan merupakan array
        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
            'data.*.metadata.commodity_name' => 'required|string|max:255',
            'data.*.metadata.analysis_date' => 'required|date_format:Y-m-d', // Validasi tanggal juga penting
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $results = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($request->input('data') as $data) {
                $komoditas = Komoditas::where('nama', $data['metadata']['commodity_name'])->first();

                if (!$komoditas) {
                    $errors[] = "Komoditas '{$data['metadata']['commodity_name']}' tidak ditemukan di database.";
                    continue;
                }

                $analysisDate = $data['metadata']['analysis_date'];

                // [PERUBAHAN UTAMA] Mengganti ::create() dengan ::updateOrCreate()
                $analisis = AnalisisHarga::updateOrCreate(
                    [
                        // Kriteria untuk mencari record unik
                        'komoditas_id'  => $komoditas->id,
                        'analysis_date' => $analysisDate,
                    ],
                    [
                        // Data untuk diisi atau di-update
                        'commodity_category' => $data['metadata']['commodity_category'] ?? null,
                        'data_freshness' => $data['metadata']['data_freshness'] ?? null,
                        'analysis_confidence' => $data['metadata']['analysis_confidence'] ?? null,
                        'condition_level' => $data['price_condition_assessment']['condition_level'] ?? null,
                        'volatility_index' => $data['price_condition_assessment']['volatility_index'] ?? null,
                        'trend_direction' => $data['price_condition_assessment']['trend_direction'] ?? null,
                        'statistical_significance' => $data['price_condition_assessment']['statistical_significance'] ?? null,
                        'key_observation' => $data['price_condition_assessment']['key_observation'] ?? null,
                        'current_position' => $data['data_insights']['current_position'] ?? null,
                        'price_pattern' => $data['data_insights']['price_pattern'] ?? null,
                        'volatility_analysis' => $data['data_insights']['volatility_analysis'] ?? null,
                        'trend_analysis' => $data['data_insights']['trend_analysis'] ?? null,
                        'deviation_percentage' => $data['statistical_findings']['deviation_from_average']['percentage'] ?? null,
                        'deviation_interpretation' => $data['statistical_findings']['deviation_from_average']['interpretation'] ?? null,
                        'volatility_level' => $data['statistical_findings']['volatility_assessment']['level'] ?? null,
                        'volatility_cv_interpretation' => $data['statistical_findings']['volatility_assessment']['cv_interpretation'] ?? null,
                        'trend_strength' => $data['statistical_findings']['trend_strength']['strength'] ?? null,
                        'trend_consistency' => $data['statistical_findings']['trend_strength']['consistency'] ?? null,
                        'short_term_outlook' => $data['forward_indicators']['short_term_outlook'] ?? null,
                        'pattern_sustainability' => $data['forward_indicators']['pattern_sustainability'] ?? null,
                        'external_factors_note' => $data['analysis_limitations']['external_factors_note'] ?? null,
                    ]
                );

                // Hapus data relasi lama sebelum menyisipkan yang baru untuk menghindari duplikasi
                $analisis->dataBasedAlerts()->delete();
                $analisis->monitoringSuggestions()->delete();
                $analisis->patternImplications()->delete();
                $analisis->keyMetricsToWatch()->delete();
                $analisis->dataQualityNotes()->delete();
                $analisis->additionalDataSuggestions()->delete();
                $analisis->statisticalWarnings()->delete();
                $analisis->dataConstraints()->delete();
                $analisis->assumptionsMade()->delete();


                $this->saveRelatedData($analisis->dataBasedAlerts(), $data['potential_considerations']['data_based_alerts'] ?? []);
                $this->saveRelatedData($analisis->monitoringSuggestions(), $data['potential_considerations']['monitoring_suggestions'] ?? []);
                $this->saveRelatedData($analisis->patternImplications(), $data['potential_considerations']['pattern_implications'] ?? []);
                $this->saveRelatedData($analisis->keyMetricsToWatch(), $data['information_support']['key_metrics_to_watch'] ?? []);
                $this->saveRelatedData($analisis->dataQualityNotes(), $data['information_support']['data_quality_notes'] ?? []);
                $this->saveRelatedData($analisis->additionalDataSuggestions(), $data['information_support']['additional_data_suggestions'] ?? []);
                $this->saveRelatedData($analisis->statisticalWarnings(), $data['forward_indicators']['statistical_warnings'] ?? []);
                $this->saveRelatedData($analisis->dataConstraints(), $data['analysis_limitations']['data_constraints'] ?? []);
                $this->saveRelatedData($analisis->assumptionsMade(), $data['analysis_limitations']['assumptions_made'] ?? []);

                $results[] = "Analisis untuk '{$komoditas->nama}' pada tanggal {$analysisDate} berhasil diproses (dibuat/diperbarui).";
            }

            // Jika ada error (misal komoditas tidak ditemukan), batalkan semua operasi database
            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Beberapa data gagal diproses.',
                    'success' => $results,
                    'errors' => $errors,
                ], 422);
            }

            // Jika semua item berhasil diproses, simpan perubahan ke database
            DB::commit();

            return response()->json([
                'message' => 'Semua data analisis berhasil disimpan.',
                'results' => $results
            ], 201);
        } catch (\Exception $e) {
            // Jika terjadi kesalahan tak terduga, batalkan transaksi dan kirim response error
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan internal pada server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper function untuk menyimpan item dari sebuah array ke dalam tabel relasi.
     * Fungsi ini bersifat reusable untuk semua data array yang memiliki struktur sama.
     *
     * @param \Illuminate\Database\Eloquent\Relations\HasMany $relation Objek relasi Eloquent (misal: $analisis->dataBasedAlerts())
     * @param array $items Array berisi string yang akan disimpan (misal: ['Alert 1', 'Alert 2'])
     */
    private function saveRelatedData($relation, array $items) {
        // Jika array kosong, tidak ada yang perlu dilakukan
        if (empty($items)) {
            return;
        }

        $dataToInsert = [];
        // Ubah array of strings menjadi array of arrays untuk createMany()
        foreach ($items as $item) {
            $dataToInsert[] = ['content' => $item];
        }

        // Gunakan createMany untuk efisiensi (bulk insert)
        $relation->createMany($dataToInsert);
    }

    /**
     * Mengambil data analisis untuk ditampilkan di frontend.
     * Fungsi ini mendukung filter berdasarkan rentang tanggal melalui query parameter.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        // 1. Validasi input dari query parameter URL
        $validator = Validator::make($request->query(), [
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Memulai query builder
        // Kita tetap memuat semua relasi yang dibutuhkan oleh frontend
        $query = AnalisisHarga::with([
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
        ]);

        // 3. Terapkan filter tanggal secara kondisional menggunakan when()
        // Ini adalah cara Laravel yang elegan untuk menambahkan klausa jika kondisi terpenuhi.

        // Jika 'start_date' ada di request, tambahkan klausa where
        $query->when($request->start_date, function ($q, $startDate) {
            return $q->where('analysis_date', '>=', $startDate);
        });

        // Jika 'end_date' ada di request, tambahkan klausa where
        $query->when($request->end_date, function ($q, $endDate) {
            return $q->where('analysis_date', '<=', $endDate);
        });

        // Jika 'end_date' ada di request, tambahkan klausa where
        $query->when($request->limit, function ($q, $limit) {
            return $q->limit($limit);
        });

        // 4. Urutkan hasilnya berdasarkan tanggal analisis terbaru dan eksekusi query
        $analisis = $query->latest('analysis_date')->get();

        // 5. Kembalikan data dalam format JSON
        return response()->json($analisis);
    }
}
