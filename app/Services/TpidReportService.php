<?php

namespace App\Services;

use App\Models\Harga;
use App\Models\Komoditas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TpidReportService {

    // 1. TAMBAHKAN DEPENDENSI WeatherService
    protected $weatherService;

    public function __construct(WeatherService $weatherService) {
        $this->weatherService = $weatherService;
    }

    /**
     * PERBAIKAN: Fungsi ini dioptimalkan untuk menghindari N+1 Query.
     * Mengasumsikan kolom 'tanggal' adalah string 'd/m/Y'.
     *
     * @param int $limit
     * @param int $days
     * @return Collection
     */
    public function getTopMovers($limit = 5, $days = 7): Collection {
        // Peringatan: Sangat direkomendasikan untuk mengubah kolom 'tanggal' menjadi tipe DATE di database.
        // Jika sudah DATE, hapus semua Carbon::createFromFormat.

        // 1. Ambil semua data harga dalam rentang waktu yang relevan (misal: 100 hari terakhir) dalam satu query.
        // Ini jauh lebih efisien daripada query di dalam loop.
        $allPrices = Harga::whereIn('id_komoditas', Komoditas::pluck('id_komoditas'))
            ->orderBy('id_komoditas')
            ->orderByRaw("STR_TO_DATE(tanggal, '%d/%m/%Y') DESC") // <-- Order berdasarkan tanggal yang dikonversi
            // ->limit(40 * 100) // Ambil cukup data untuk setiap komoditas
            ->get();

        if ($allPrices->isEmpty()) {
            return new Collection();
        }

        // 2. Kelompokkan berdasarkan id_komoditas
        $pricesByCommodity = $allPrices->groupBy('id_komoditas');
        $allCommodities = Komoditas::whereIn('id_komoditas', $pricesByCommodity->keys())->get()->keyBy('id_komoditas');
        $changes = new Collection();

        foreach ($pricesByCommodity as $id_komoditas => $prices) {
            // Urutkan sekali lagi di collection untuk memastikan (terkadang groupBy bisa mengubah urutan)
            $sortedPrices = $prices->sortByDesc(fn($p) => Carbon::createFromFormat('d/m/Y', $p->tanggal));

            $latestPriceRecord = $sortedPrices->first();
            if (!$latestPriceRecord) {
                continue;
            }

            $comparisonDate = Carbon::createFromFormat('d/m/Y', $latestPriceRecord->tanggal)->subDays($days);

            // Cari harga pembanding di dalam collection, bukan query DB lagi
            $comparisonPriceRecord = $sortedPrices->first(function ($price) use ($comparisonDate) {
                return Carbon::createFromFormat('d/m/Y', $price->tanggal)->lte($comparisonDate);
            });

            $komoditas = $allCommodities->get($id_komoditas);
            // dump($allCommodities->get($id_komoditas)->nama);



            if ($komoditas && $comparisonPriceRecord && $comparisonPriceRecord->harga > 0) {
                $latestPrice = (float) $latestPriceRecord->harga;
                $comparisonPrice = (float) $comparisonPriceRecord->harga;
                $percentageChange = (($latestPrice - $comparisonPrice) / $comparisonPrice) * 100;

                $changes->push([
                    'komoditas' => $komoditas,
                    'change' => $percentageChange,
                    'latest_price' => $latestPrice,
                    'comparison_price' => $comparisonPrice,
                ]);
            }
        }

        $sortedChanges = $changes->sortByDesc(fn($item) => abs($item['change']));

        return $sortedChanges->values()->take($limit); // Gunakan values() untuk reset keys
    }
    /**
     * PERBAIKAN: Query disesuaikan untuk menangani format tanggal string 'd/m/Y'
     * dan diurutkan dengan benar.
     */
    public function generateTpidAnalysisPrompt(Komoditas $komoditas, Carbon $currentDate): string {
        // ... (logika pengambilan data harga tetap sama) ...
        $allPricesForCommodity = Harga::where('id_komoditas', $komoditas->id_komoditas)->get();

        $prices = $allPricesForCommodity
            ->sortBy(fn($p) => Carbon::createFromFormat('d/m/Y', $p->tanggal))
            ->slice(-90);

        if ($prices->count() < 2) {
            return json_encode(['error' => "Data untuk komoditas {$komoditas->nama} tidak cukup untuk dianalisis."]);
        }
        $lastData = $prices->last();
        if (!$lastData) {
            return json_encode(['error' => "Gagal mendapatkan data terakhir untuk {$komoditas->nama}."]);
        }

        $statistics = $this->calculateStatistics($prices);

        // 2. AMBIL DATA CUACA DI SINI
        $weatherContext = $this->getWeatherContext();


        // 3. PASS DATA CUACA KE buildPrompt
        return $this->buildPrompt($komoditas, $statistics, $currentDate, $lastData->tanggal, $weatherContext);
    }

    /**
     * FUNGSI BARU (YANG DIPERBAIKI): Mengambil dan menyusun konteks cuaca
     * dengan implementasi CACHING untuk menghindari timeout.
     */
    private function getWeatherContext(): array {
        $mempawahCoords = config('tpid.locations.mempawah');
        $kalbarCoords = config('tpid.locations.kalbar_regional');
        $cacheDuration = now()->addHours(6); // Simpan data cuaca selama 6 jam

        $weatherContext = [
            'konteks_cuaca_mempawah' => null,
            'konteks_cuaca_kalbar' => null,
        ];

        // --- Ambil data untuk Mempawah DENGAN CACHE ---
        $mempawahCacheKey = 'weather_' . Str::slug($mempawahCoords['name']) . '_' . now()->toDateString();

        $mempawahData = Cache::remember($mempawahCacheKey, $cacheDuration, function () use ($mempawahCoords) {
            return $this->weatherService->getHistoricalWeatherSummary($mempawahCoords['latitude'], $mempawahCoords['longitude']);
        });

        if ($mempawahData) {
            $weatherContext['konteks_cuaca_mempawah'] = [
                'level' => $mempawahCoords['name'],
                'ringkasan_data' => $mempawahData,
            ];
        }

        // --- Ambil data untuk Regional Kalbar DENGAN CACHE ---
        $regionalData = [];
        foreach ($kalbarCoords as $key => $coords) {
            $regionalCacheKey = 'weather_' . Str::slug($coords['name']) . '_' . now()->toDateString();

            $data = Cache::remember($regionalCacheKey, $cacheDuration, function () use ($coords) {
                return $this->weatherService->getHistoricalWeatherSummary($coords['latitude'], $coords['longitude']);
            });

            if ($data) {
                $regionalData[] = [
                    'lokasi' => $coords['name'],
                    'ringkasan_data' => $data,
                ];
            }
        }

        if (!empty($regionalData)) {
            $weatherContext['konteks_cuaca_kalbar'] = [
                'level' => 'Provinsi Kalimantan Barat (Regional)',
                'data_per_lokasi' => $regionalData,
            ];
        }

        return $weatherContext;
    }


    /**
     * PERBAIKAN: Konsisten menggunakan createFromFormat untuk semua operasi tanggal.
     */
    private function calculateStatistics(Collection $prices): array {
        $latest = $prices->last();
        $latestPrice = (float) $latest->harga;

        $previousDay = $prices->slice(-2, 1)->first();

        // PERBAIKAN: Gunakan Carbon untuk perbandingan tanggal yang akurat pada collection
        $latestDate = Carbon::createFromFormat('d/m/Y', $latest->tanggal);
        $sevenDaysAgoDate = $latestDate->copy()->subDays(7);
        $thirtyDaysAgoDate = $latestDate->copy()->subDays(30);

        $sevenDaysAgo = $prices->last(fn($p) => Carbon::createFromFormat('d/m/Y', $p->tanggal)->lte($sevenDaysAgoDate));
        $thirtyDaysAgo = $prices->last(fn($p) => Carbon::createFromFormat('d/m/Y', $p->tanggal)->lte($thirtyDaysAgoDate));

        // Kalkulasi perubahan (tidak ada perubahan di sini, sudah benar)
        $dayChange = $previousDay && $previousDay->harga > 0 ? (($latestPrice - $previousDay->harga) / $previousDay->harga) * 100 : 'N/A';
        $weekChange = $sevenDaysAgo && $sevenDaysAgo->harga > 0 ? (($latestPrice - $sevenDaysAgo->harga) / $sevenDaysAgo->harga) * 100 : 'N/A';
        $monthChange = $thirtyDaysAgo && $thirtyDaysAgo->harga > 0 ? (($latestPrice - $thirtyDaysAgo->harga) / $thirtyDaysAgo->harga) * 100 : 'N/A';

        $priceValues = $prices->pluck('harga')->map(fn($p) => (float)$p);
        $average = $priceValues->avg();
        $max = $priceValues->max();
        $min = $priceValues->min();

        // PERBAIKAN: Hindari division by zero jika hanya ada 1 data.
        $count = $priceValues->count();
        $stdDev = 0;
        if ($count > 1) {
            $mean = $average; // Sudah dihitung
            $stdDev = sqrt($priceValues->map(fn($val) => pow($val - $mean, 2))->sum() / $count);
        }
        // --- TAMBAHAN BARU: EKSTRAK RIWAYAT HARGA 7 HARI TERAKHIR ---
        $priceHistory7d = $prices->slice(-7)->map(function ($price) {
            return [
                'tanggal' => $price->tanggal,
                'harga' => (int) $price->harga,
            ];
        })->values()->all(); // `values()` untuk mereset keys array menjadi 0, 1, 2, ...


        return [
            'latest_price' => $latestPrice,
            'change_daily' => $dayChange,
            'change_weekly' => $weekChange,
            'change_monthly' => $monthChange,
            'average_90d' => $average ?: 0, // Hindari null jika collection kosong
            'max_90d' => $max ?: 0,
            'min_90d' => $min ?: 0,
            'volatility_90d' => $stdDev,
            'price_history_7d' => $priceHistory7d, // <-- DATA BARU DITAMBAHKAN DI SINI
        ];
    }

    /**
     * PERBAIKAN: Signature method diubah dan prompt diperkaya dengan data cuaca.
     *
     * @param Komoditas $komoditas
     * @param array $stats
     * @param Carbon $currentDate
     * @param string $lastDataDate
     * @param array $weatherContext <--- PARAMETER BARU
     * @return string
     */
    private function buildPrompt(Komoditas $komoditas, array $stats, Carbon $currentDate, string $lastDataDate, array $weatherContext): string {
        // Persiapan variabel dengan validasi dan formatting yang lebih robust
        $dailyChangeText = is_numeric($stats['change_daily']) ? number_format($stats['change_daily'], 2) . '%' : 'N/A';
        $weeklyChangeText = is_numeric($stats['change_weekly']) ? number_format($stats['change_weekly'], 2) . '%' : 'N/A';
        $monthlyChangeText = is_numeric($stats['change_monthly']) ? number_format($stats['change_monthly'], 2) . '%' : 'N/A';
        $latestPriceFormatted = number_format($stats['latest_price'], 0, ',', '.');
        $average90dFormatted = number_format($stats['average_90d'], 0, ',', '.');
        $max90dFormatted = number_format($stats['max_90d'], 0, ',', '.');
        $min90dFormatted = number_format($stats['min_90d'], 0, ',', '.');
        $volatility90dFormatted = number_format($stats['volatility_90d'], 0, ',', '.');

        // Kalkulasi tambahan untuk memberikan konteks yang lebih kaya
        $priceToAvgRatio = ($stats['average_90d'] > 0) ? ($stats['latest_price'] / $stats['average_90d']) * 100 : 0;
        $priceToAvgText = number_format($priceToAvgRatio - 100, 2) . '%';
        $coefficientOfVariation = ($stats['average_90d'] > 0) ? ($stats['volatility_90d'] / $stats['average_90d']) * 100 : 0;
        $cvText = number_format($coefficientOfVariation, 2) . '%';

        // 4. FORMAT DATA CUACA UNTUK DIMASUKKAN KE PROMPT
        // Gunakan JSON_PRETTY_PRINT agar mudah dibaca oleh LLM
        $weatherContextJson = json_encode($weatherContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Kategorisasi komoditas untuk konteks yang lebih tepat
        $kategoriKomoditas = $this->getKategoriKomoditas($komoditas->nama);

        $komoditasNama = $komoditas->nama;
        $tanggalAnalisis = $currentDate->isoFormat('dddd, D MMMM YYYY');

        // Informasi kontekstual tambahan
        $musimInfo = $this->getMusimInfo($currentDate);
        $hbknTerdekat = $this->getHBKNTerdekat($currentDate);

        // --- TAMBAHAN BARU: FORMAT RIWAYAT HARGA SEBAGAI JSON ---
        $priceHistoryJson = 'N/A';
        if (!empty($stats['price_history_7d'])) {
            $priceHistoryJson = json_encode($stats['price_history_7d'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $prompt = <<<PROMPT
**# SISTEM INSTRUKSI UTAMA #**
Anda adalah "Analis Kontekstual", sebuah AI yang dirancang untuk memberikan dukungan keputusan kepada Tim Pengendali Inflasi Daerah (TPID) Kabupaten Mempawah. Peran Anda adalah mengubah data harga mentah menjadi wawasan strategis yang sadar konteks, memberdayakan, dan transparan.

**## Prinsip Panduan Anda: ##**
1.  **Konteks di Atas Prediksi:** Fokus pada **konteks** historis dan kelembagaan, bukan prediksi absolut.
2.  **Pertanyaan yang Memberdayakan, Bukan Jawaban yang Mendikte:** Sajikan **"pertanyaan untuk dipertimbangkan"** atau **"poin untuk dimonitor"**, bukan rekomendasi kaku.
3.  **Transparansi adalah Kunci:** Secara proaktif komunikasikan keterbatasan analisis (misal: "Analisis ini hanya berdasarkan data Pasar Sebukit Rama").
4.  **Manusia sebagai Pusat (Human-in-the-Loop):** Anda adalah alat bantu untuk analis manusia, bukan pengganti.

---
**# BAGIAN 1: KNOWLEDGE BASE STRATEGIS TPID #**

**## 1.1. Framework Analisis 4 Lapis (Wajib Digunakan) ##**
Anda harus menstrukturkan analisis Anda melalui empat lapisan ini:
1.  **Pattern Recognition:** Identifikasi anomali, tren, dan volatilitas dalam data. (Ini adalah output dasar Anda).
2.  **Causal Analysis (Hipotesis):** Berdasarkan pola data dan konteks musiman, berikan **hipotesis** kemungkinan penyebabnya. Contoh: "Kenaikan tajam dan cepat saat musim hujan mengindikasikan *supply-side shock*."
3.  **Impact Projection (Pembingkaian):** Kaitkan pergerakan harga dengan potensi dampaknya bagi masyarakat. Contoh: "Sebagai komoditas pokok, kenaikan harga beras berpotensi signifikan mempengaruhi daya beli masyarakat berpenghasilan rendah."
4.  **Intervention Strategy Framing (Pemberdayaan):** Rumuskan **PERTANYANAN KUNCI** yang relevan untuk stakeholder, bukan rekomendasi. Tujuannya adalah memicu diskusi dan verifikasi.

**## 1.2. Konteks Kelembagaan TPID (Pemetaan Stakeholder) ##**
Gunakan pemetaan ini untuk menghasilkan pertimbangan yang relevan:
*   **Koordinator TPID (Sekda/Ketua):** Fokus pada gambaran besar, koordinasi lintas-sektor, dan implikasi kebijakan secara umum. Pertanyaan untuk mereka harus bersifat strategis.
*   **Dinas Perdagangan:** Fokus pada stabilitas pasokan, kelancaran distribusi, dan operasi pasar. Pertanyaan untuk mereka harus seputar stok, harga di tingkat distributor, dan anomali di rantai pasok.
*   **Dinas Pertanian:** Fokus pada produksi, musim tanam/panen, dan kesehatan tanaman. Pertanyaan untuk mereka harus seputar kondisi di tingkat petani, potensi gagal panen, dan faktor-faktor produksi.

**## 1.3. Katalog Efektivitas Intervensi (Sebagai "Contekan") ##**
Gunakan ini untuk membingkai pertanyaan yang lebih cerdas:
*   **Operasi Pasar:** Efektif untuk stabilisasi jangka pendek (5-15% dalam 1-2 minggu) jika volume dan waktu tepat.
*   **Penetapan HET:** Berhasil jika ada penegakan yang kuat, namun berisiko menciptakan pasar gelap.
*   **Intervensi Rantai Pasok (misal: subsidi transportasi):** Dampak bisa lebih lama, namun memerlukan investasi lebih besar.

---
**# BAGIAN 1B: KNOWLEDGE BASE ANALISIS HARGA (UNTUK KOMPATIBILITAS) #**

**## 1B.1. Framework Klasifikasi Kondisi Harga ##**

**### Level 1: STABIL ###**
* **Kriteria:** Pergerakan harga dalam rentang normal
* **Indikator Komoditas Pokok:**
  - Perubahan harian < ±1%
  - Perubahan mingguan < ±2%
  - Harga dalam rentang ±5% dari rata-rata 90 hari
* **Indikator Komoditas Volatil:**
  - Perubahan harian < ±3%
  - Perubahan mingguan < ±7%
  - CV (Coefficient of Variation) < 15%

**### Level 2: FLUKTUATIF ###**
* **Kriteria:** Pergerakan di atas normal, perlu pemantauan
* **Indikator Komoditas Pokok:**
  - Perubahan harian 1-2% selama beberapa hari
  - Perubahan mingguan 2-5%
  - Harga 5-10% di atas/bawah rata-rata 90 hari
* **Indikator Komoditas Volatil:**
  - Perubahan harian 3-7%
  - Perubahan mingguan 7-15%
  - CV 15-25%

**### Level 3: BERGEJOLAK ###**
* **Kriteria:** Pergerakan signifikan, analisis mendalam diperlukan
* **Indikator Komoditas Pokok:**
  - Perubahan mingguan 5-10%
  - Harga 10-20% di atas/bawah rata-rata 90 hari
* **Indikator Komoditas Volatil:**
  - Perubahan mingguan 15-25%
  - CV 25-35%

**### Level 4: EKSTREM ###**
* **Kriteria:** Pergerakan sangat tidak normal, analisis kritis diperlukan
* **Indikator Universal:**
  - Perubahan mingguan >25%
  - Harga >20% di atas/bawah rata-rata
  - Volatilitas melampaui batas historis

**## 1B.2. Analysis Confidence Criteria ##**
- **HIGH:** Data lengkap, pola jelas, statistik signifikan
- **MEDIUM:** Data cukup, pola terlihat, beberapa keterbatasan
- **LOW:** Data terbatas, pola tidak jelas, banyak keterbatasan

---
**# BAGIAN 2: DATA UNTUK ANALISIS #**

**## Informasi Dasar ##**
- **Wilayah:** Kabupaten Mempawah, Kalimantan Barat
- **Komoditas:** {$komoditasNama}
- **Kategori:** {$kategoriKomoditas}
- **Tanggal Analisis:** {$tanggalAnalisis}
- **Data Terakhir:** {$lastDataDate}
- **Konteks Musiman:** {$musimInfo}
- **HBKN Terdekat:** {$hbknTerdekat}

**## Konteks Cuaca Aktual (Data dari API - 7 Hari Terakhir) ##**
{$weatherContextJson}

**## Data Harga dan Statistik (90 Hari Terakhir) ##**
- **Harga Saat Ini:** Rp {$latestPriceFormatted}
- **Perubahan Harian:** {$dailyChangeText}
- **Perubahan Mingguan:** {$weeklyChangeText}
- **Perubahan Bulanan:** {$monthlyChangeText}
- **Posisi thd. Rata-rata:** {$priceToAvgText}
- **Rata-rata Harga:** Rp {$average90dFormatted}
- **Harga Tertinggi:** Rp {$max90dFormatted}
- **Harga Terendah:** Rp {$min90dFormatted}
- **Volatilitas (Std Dev):** Rp {$volatility90dFormatted}
- **Coefficient of Variation:** {$cvText}

**## Riwayat Harga (7 Hari Terakhir) ##**
{$priceHistoryJson}

---
**# BAGIAN 3: INSTRUKSI OUTPUT JSON v2.0 #**

**TUGAS ANDA:** Berdasarkan **Knowledge Base** di Bagian 1 dan **Data** di Bagian 2, hasilkan output dalam format **JSON MURNI** dengan struktur v2.0 di bawah ini. Pastikan setiap field terisi sesuai dengan prinsip dan panduan yang diberikan.

```json
{
    "metadata": {
        "commodity_name": "string",
        "commodity_category": "string (POKOK/PENTING/VOLATIL)",
        "analysis_date": "string (format: YYYY-MM-DD)",
        "data_freshness": "string (format: YYYY-MM-DD)",
        "analysis_confidence": "string (HIGH/MEDIUM/LOW)"
    },
    "price_condition_assessment": {
        "condition_level": "string (STABIL/FLUKTUATIF/BERGEJOLAK/EKSTREM)",
        "volatility_index": "number (0-100)",
        "trend_direction": "string (NAIK/TURUN/SIDEWAYS)",
        "statistical_significance": "string (SIGNIFIKAN/NORMAL/TIDAK_SIGNIFIKAN)",
        "key_observation": "string (observasi utama dari data)"
    },
    "data_insights": {
        "current_position": "string (posisi harga saat ini relatif terhadap historis)",
        "price_pattern": "string (pola yang teridentifikasi dari data)",
        "volatility_analysis": "string (analisis tingkat volatilitas)",
        "trend_analysis": "string (analisis tren berdasarkan data)"
    },
    "statistical_findings": {
        "deviation_from_average": {
            "percentage": "number",
            "interpretation": "string"
        },
        "volatility_assessment": {
            "level": "string (RENDAH/SEDANG/TINGGI/SANGAT_TINGGI)",
            "cv_interpretation": "string"
        },
        "trend_strength": {
            "strength": "string (LEMAH/SEDANG/KUAT)",
            "consistency": "string (KONSISTEN/TIDAK_KONSISTEN)"
        }
    },
    "strategic_analysis": {
        "causal_hypothesis": "string (Hipotesis utama penyebab pergerakan harga berdasarkan pola data dan konteks musiman)",
        "potential_impact_framing": "string (Penjelasan singkat mengenai potensi signifikansi pergerakan harga ini bagi masyarakat lokal)"
    },
    "stakeholder_specific_considerations": {
        "for_dinas_perdagangan": "string (Pertanyaan kunci atau poin monitor yang relevan untuk Dinas Perdagangan)",
        "for_dinas_pertanian": "string (Pertanyaan kunci atau poin monitor yang relevan untuk Dinas Pertanian)",
        "for_koordinator_tpid": "string (Poin diskusi tingkat tinggi yang relevan untuk Ketua/Koordinator TPID)"
    },
    "potential_considerations": {
        "data_based_alerts": [
            "string (peringatan berdasarkan pola data)"
        ],
        "monitoring_suggestions": [
            "string (saran pemantauan berdasarkan analisis)"
        ],
        "pattern_implications": [
            "string (implikasi dari pola yang teridentifikasi)"
        ]
    },
    "information_support": {
        "key_metrics_to_watch": [
            "string (metrik kunci yang perlu dipantau)"
        ],
        "data_quality_notes": [
            "string (catatan tentang kualitas data)"
        ],
        "additional_data_suggestions": [
            "string (saran data tambahan yang mungkin diperlukan)"
        ]
    },
    "forward_indicators": {
        "short_term_outlook": "string (indikasi berdasarkan data untuk 1-7 hari)",
        "pattern_sustainability": "string (keberlanjutan pola berdasarkan data historis)",
        "statistical_warnings": [
            "string (peringatan statistik berdasarkan pola data)"
        ]
    },
    "analysis_limitations": {
        "data_constraints": [
            "string (keterbatasan data yang mempengaruhi analisis)"
        ],
        "assumptions_made": [
            "string (asumsi yang dibuat dalam analisis)"
        ],
        "external_factors_note": "string (catatan bahwa faktor eksternal tidak dianalisis)"
    }
}
```
HASILKAN HANYA JSON OUTPUT MURNI BERDASARKAN INSTRUKSI DI ATAS.
PROMPT;

        return $prompt;
    }

    /**
     * Helper method untuk kategorisasi komoditas
     */
    private function getKategoriKomoditas(string $namaKomoditas): string {
        $kategorisasi = [
            'POKOK' => ['beras', 'gula pasir', 'minyak goreng', 'tepung terigu', 'garam'],
            'PENTING' => ['daging sapi', 'daging ayam', 'telur ayam', 'susu', 'jagung', 'kedelai', 'ikan'],
            'VOLATIL' => ['cabai merah', 'cabai rawit', 'bawang merah', 'bawang putih', 'tomat', 'kentang']
        ];

        $namaLower = strtolower($namaKomoditas);

        foreach ($kategorisasi as $kategori => $items) {
            foreach ($items as $item) {
                if (str_contains($namaLower, $item)) {
                    return $kategori;
                }
            }
        }

        return 'PENTING'; // Default kategori
    }

    /**
     * Helper method untuk informasi musim berdasarkan prediksi BMKG terkini.
     *
     * @param \Carbon\Carbon $date
     * @return string
     */
    private function getMusimInfo(Carbon $date): string {
        $month = $date->month;

        // Pancaroba I: Peralihan dari Musim Hujan ke Kemarau (sekitar April – Mei)
        if ($month >= 4 && $month <= 5) {
            return "Musim Peralihan I (Hujan ke Kemarau). Curah hujan mulai menurun; potensi cuaca tidak menentu, ancaman kekeringan lokal, dan risiko hama penyakit tanaman.";
        }

        // Musim Kemarau: bulan puncak kemarau (biasanya Juni – Agustus)
        if ($month >= 6 && $month <= 8) {
            return "Musim Kemarau. Curah hujan rendah; potensi kekeringan, meningkatnya risiko kebakaran hutan dan lahan, suhu udara tinggi, kebutuhan air meningkat.";
        }

        // Puncak nearing akhir Musim Kemarau → peralihan ke hujan
        if ($month == 9) {
            return "Pancaroba (Kemarau ke Hujan). Curah hujan mulai meningkat; potensi hujan lokal/lebat, gangguan pengeringan, kesiapsiagaan banjir diperlukan.";
        }

        // Musim Hujan: mulai Oktober sampai sekitar April
        if ($month >= 10 || $month <= 4) {
            return "Musim Hujan. Curah hujan tinggi; risiko banjir, genangan, tanah longsor, gangguan transportasi dan logistik.";
        }

        // Fallback jika ada bulan yang tidak sesuai logika di atas
        return "Data musim untuk bulan ini belum tersedia / dalam masa transisi.";
    }


    /**
     * Helper method untuk HBKN terdekat
     */
    private function getHBKNTerdekat(Carbon $date): string {
        // Simplified implementation - dalam produksi gunakan database HBKN
        $hbkn = [
            '2025-03-29' => 'Ramadan (H-30)',
            '2025-04-29' => 'Idul Fitri (H-30)',
            '2025-12-25' => 'Natal (H-30)',
        ];

        foreach ($hbkn as $tanggal => $nama) {
            $hbknDate = Carbon::parse($tanggal);
            $diff = $date->diffInDays($hbknDate, false);

            if ($diff > 0 && $diff <= 30) {
                return "$nama - $diff hari lagi";
            }
        }

        return "Tidak ada HBKN dalam 30 hari ke depan";
    }
}
