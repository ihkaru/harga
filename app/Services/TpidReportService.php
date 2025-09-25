<?php

namespace App\Services;

use App\Models\Harga;
use App\Models\Komoditas;
use App\Services\HETService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TpidReportService {

    // 1. TAMBAHKAN DEPENDENSI WeatherService
    protected $weatherService;
    protected $hetService;

    public function __construct(WeatherService $weatherService, HETService $hetService) {
        $this->weatherService = $weatherService;
        $this->hetService = $hetService;
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
        // Ambil data dengan limit yang wajar untuk mencegah memory overflow
        $allPrices = Harga::whereIn('id_komoditas', Komoditas::pluck('id_komoditas'))
            ->orderBy('id_komoditas')
            ->get();

        if ($allPrices->isEmpty()) {
            return new Collection();
        }

        // PERBAIKAN: Validasi dan filter tanggal yang tidak valid
        $validPrices = $allPrices->filter(function ($price) {
            try {
                Carbon::createFromFormat('d/m/Y', $price->tanggal);
                return $price->harga > 0; // Filter harga non-positif
            } catch (\Exception $e) {
                Log::warning("Invalid date format for price ID {$price->id}: {$price->tanggal}");
                return false;
            }
        });

        $pricesByCommodity = $validPrices->groupBy('id_komoditas');
        $allCommodities = Komoditas::whereIn('id_komoditas', $pricesByCommodity->keys())->get()->keyBy('id_komoditas');
        $changes = new Collection();

        foreach ($pricesByCommodity as $id_komoditas => $prices) {
            $sortedPrices = $prices->sortByDesc(function ($p) {
                return Carbon::createFromFormat('d/m/Y', $p->tanggal);
            });

            $latestPriceRecord = $sortedPrices->first();
            if (!$latestPriceRecord) {
                continue;
            }

            $comparisonDate = Carbon::createFromFormat('d/m/Y', $latestPriceRecord->tanggal)->subDays($days);
            $comparisonPriceRecord = $sortedPrices->first(function ($price) use ($comparisonDate) {
                return Carbon::createFromFormat('d/m/Y', $price->tanggal)->lte($comparisonDate);
            });

            $komoditas = $allCommodities->get($id_komoditas);

            // PERBAIKAN: Validasi yang lebih ketat
            if (
                $komoditas && $comparisonPriceRecord &&
                $comparisonPriceRecord->harga > 0 && $latestPriceRecord->harga > 0
            ) {

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

        return $changes->sortByDesc(fn($item) => abs($item['change']))->values()->take($limit);
    }
    public function generateTpidAnalysisPrompt(Komoditas $komoditas, Carbon $currentDate): string {
        // Cek weather sensitivity dulu sebelum fetch data weather
        $isWeatherRelevant = $this->isWeatherSensitive($komoditas);

        $allPricesForCommodity = Harga::where('id_komoditas', $komoditas->id_komoditas)
            ->get();

        // PERBAIKAN: Validasi data sebelum sorting
        $validPrices = $allPricesForCommodity->filter(function ($price) {
            try {
                // BUG FIX: Use createFromFormat for accurate parsing
                Carbon::createFromFormat('d/m/Y', $price->tanggal);
                return $price->harga > 0;
            } catch (\Exception $e) {
                return false;
            }
        });

        $prices = $validPrices
            ->sortBy(fn($p) => Carbon::createFromFormat('d/m/Y', $p->tanggal))
            ->slice(-90);

        if ($prices->count() < 2) {
            return json_encode(['error' => "Data untuk komoditas {$komoditas->nama} tidak cukup untuk dianalisis."]);
        }

        $lastData = $prices->last();
        if (!$lastData) {
            return json_encode(['error' => "Gagal mendapatkan data terakhir untuk {$komoditas->nama}."]);
        }

        $statistics = $this->calculateStatistics($prices, $komoditas);

        // PERBAIKAN: Fetch weather hanya jika relevan
        $weatherContext = $isWeatherRelevant ? $this->getWeatherContext() : [];

        $hetContext = $this->hetService->findPriceControl($komoditas);
        $specificCommodityContext = $this->getCommoditySpecificContext($komoditas);

        return $this->buildPrompt(
            $komoditas,
            $statistics,
            $currentDate,
            $lastData->tanggal,
            $weatherContext,
            $hetContext,
            $specificCommodityContext,
            $isWeatherRelevant
        );
    }

    /**
     * FUNGSI BARU (YANG DIPERBAIKI): Mengambil dan menyusun konteks cuaca
     * dengan implementasi CACHING untuk menghindari timeout.
     */
    private function getWeatherContext(): array {
        $mempawahCoords = config('tpid.locations.mempawah');
        $kalbarCoords = config('tpid.locations.kalbar_regional');

        // PERBAIKAN: Cache per jam, bukan per hari
        $cacheHour = now()->format('Y-m-d-H');
        $cacheDuration = now()->addHours(1);

        $weatherContext = [
            'konteks_cuaca_mempawah' => null,
            'konteks_cuaca_kalbar' => null,
        ];

        // PERBAIKAN: Tambah try-catch untuk handle API failures
        try {
            $mempawahCacheKey = "weather_mempawah_{$cacheHour}";
            $mempawahData = Cache::remember($mempawahCacheKey, $cacheDuration, function () use ($mempawahCoords) {
                return $this->weatherService->getHistoricalWeatherSummary(
                    $mempawahCoords['latitude'],
                    $mempawahCoords['longitude']
                );
            });

            if ($mempawahData) {
                $weatherContext['konteks_cuaca_mempawah'] = [
                    'level' => $mempawahCoords['name'],
                    'ringkasan_data' => $mempawahData,
                ];
            }
        } catch (\Exception $e) {
            Log::warning("Weather API failed for Mempawah: " . $e->getMessage());
        }

        // Regional data dengan error handling
        $regionalData = [];
        foreach ($kalbarCoords as $key => $coords) {
            try {
                $regionalCacheKey = "weather_{$key}_{$cacheHour}";
                $data = Cache::remember($regionalCacheKey, $cacheDuration, function () use ($coords) {
                    return $this->weatherService->getHistoricalWeatherSummary(
                        $coords['latitude'],
                        $coords['longitude']
                    );
                });

                if ($data) {
                    $regionalData[] = [
                        'lokasi' => $coords['name'],
                        'ringkasan_data' => $data,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("Weather API failed for {$coords['name']}: " . $e->getMessage());
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
    private function calculateStatistics(Collection $prices, Komoditas $komoditas): array {
        $latest = $prices->last();
        $latestPrice = (float) $latest->harga;

        $previousDay = $prices->slice(-2, 1)->first();

        $latestDate = Carbon::createFromFormat('d/m/Y', $latest->tanggal);
        $sevenDaysAgoDate = $latestDate->copy()->subDays(7);
        $thirtyDaysAgoDate = $latestDate->copy()->subDays(30);

        $sevenDaysAgo = $prices->last(fn($p) => Carbon::createFromFormat('d/m/Y', $p->tanggal)->lte($sevenDaysAgoDate));
        $thirtyDaysAgo = $prices->last(fn($p) => Carbon::createFromFormat('d/m/Y', $p->tanggal)->lte($thirtyDaysAgoDate));

        // PERBAIKAN: Tambah validasi untuk menghindari division by zero
        $dayChange = ($previousDay && $previousDay->harga > 0) ?
            (($latestPrice - $previousDay->harga) / $previousDay->harga) * 100 : null;

        $weekChange = ($sevenDaysAgo && $sevenDaysAgo->harga > 0) ?
            (($latestPrice - $sevenDaysAgo->harga) / $sevenDaysAgo->harga) * 100 : null;

        $monthChange = ($thirtyDaysAgo && $thirtyDaysAgo->harga > 0) ?
            (($latestPrice - $thirtyDaysAgo->harga) / $thirtyDaysAgo->harga) * 100 : null;

        $priceValues = $prices->pluck('harga')->map(fn($p) => (float)$p);
        $count = $priceValues->count();
        $average = $priceValues->avg();
        $max = $priceValues->max();
        $min = $priceValues->min();

        // PERBAIKAN: Gunakan sample standard deviation untuk data yang lebih akurat
        $stdDev = 0;
        if ($count > 1 && $average > 0) {
            $variance = $priceValues->map(fn($val) => pow($val - $average, 2))->sum() / ($count - 1);
            $stdDev = sqrt($variance);
        }

        $cv = ($average > 0) ? ($stdDev / $average) * 100 : 0;

        // PERBAIKAN: Volatility index yang lebih intuitif
        $maxCvThreshold = $this->getDynamicVolatilityThreshold($komoditas);

        // Menggunakan skala yang lebih intuitif:
        // 0-40: Rendah, 40-70: Sedang, 70-90: Tinggi, 90+: Sangat Tinggi
        $volatilityIndex = ($maxCvThreshold > 0) ? ($cv / $maxCvThreshold) * 100 : 0;
        // Tidak perlu min(100, ...) karena nilai di atas 100 menunjukkan kondisi ekstrem

        $priceHistory7d = $prices->slice(-7)->map(function ($price) {
            return [
                'tanggal' => $price->tanggal,
                'harga' => (int) $price->harga,
            ];
        })->values()->all();

        return [
            'latest_price' => $latestPrice,
            'change_daily' => $dayChange,
            'change_weekly' => $weekChange,
            'change_monthly' => $monthChange,
            'average_90d' => $average ?: 0,
            'max_90d' => $max ?: 0,
            'min_90d' => $min ?: 0,
            'volatility_90d' => $stdDev,
            'cv_percentage' => round($cv, 2),
            'volatility_index' => round($volatilityIndex, 2),
            'dynamic_threshold_used' => round($maxCvThreshold, 2),
            'price_history_7d' => $priceHistory7d,
            // PERBAIKAN: Tambah metadata untuk debugging
            'data_points_used' => $count,
            'calculation_method' => 'sample_standard_deviation'
        ];
    }

    /**
     * PERBAIKAN: Signature method diubah. Prompt kini dinamis berdasarkan relevansi konteks.
     *
     * @param Komoditas $komoditas
     * @param array $stats
     * @param Carbon $currentDate
     * @param string $lastDataDate
     * @param array $weatherContext
     * @param array|null $hetContext
     * @param string $specificCommodityContext <--- PARAMETER BARU
     * @param bool $isWeatherRelevant <--- PARAMETER BARU
     * @return string
     */
    private function buildPrompt(
        Komoditas $komoditas,
        array $stats,
        Carbon $currentDate,
        string $lastDataDate,
        array $weatherContext,
        ?array $hetContext,
        string $specificCommodityContext, // <-- Parameter baru
        bool $isWeatherRelevant
    ): string {
        // ... (bagian persiapan variabel tetap sama) ...
        $dailyChangeText = is_numeric($stats['change_daily']) ? number_format($stats['change_daily'], 2) . '%' : 'N/A';
        $weeklyChangeText = is_numeric($stats['change_weekly']) ? number_format($stats['change_weekly'], 2) . '%' : 'N/A';
        $monthlyChangeText = is_numeric($stats['change_monthly']) ? number_format($stats['change_monthly'], 2) . '%' : 'N/A';
        $latestPriceFormatted = number_format($stats['latest_price'], 0, ',', '.');
        $average90dFormatted = number_format($stats['average_90d'], 0, ',', '.');
        $max90dFormatted = number_format($stats['max_90d'], 0, ',', '.');
        $min90dFormatted = number_format($stats['min_90d'], 0, ',', '.');
        $volatility90dFormatted = number_format($stats['volatility_90d'], 0, ',', '.');
        // PERBAIKAN: Handle division by zero
        $priceToAvgText = ($stats['average_90d'] > 0) ?
            number_format((($stats['latest_price'] / $stats['average_90d']) * 100) - 100, 2) . '%' : 'N/A';



        // --- VARIABEL BARU UNTUK DITAMBAHKAN ---
        // Pastikan Anda sudah menghitung ini di calculateStatistics
        $volatilityIndexFormatted = number_format($stats['volatility_index'], 2);
        $cvText = number_format($stats['cv_percentage'], 2) . '%'; // Sumbernya sekarang dari $stats
        $dynamicThresholdUsedFormatted = number_format($stats['dynamic_threshold_used'], 2) . '%';


        $weatherContextJson = json_encode($weatherContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $priceHistoryJson = !empty($stats['price_history_7d']) ? json_encode($stats['price_history_7d'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'N/A';

        // --- PERUBAHAN UTAMA 1: MEMBUAT KONTEKS HET/HAP MENJADI KONDISIONAL ---
        $hetSection = ""; // Default string kosong
        $hetContextString = "";
        $hetInstruction = "Harga diserahkan pada mekanisme pasar. Analisis berfokus pada volatilitas dan tren historis.";
        if ($hetContext) {
            $latestPrice = $stats['latest_price'];
            $het = (int) filter_var($hetContext['het'], FILTER_SANITIZE_NUMBER_INT);
            $hapString = $hetContext['hap'];
            $status = 'BELUM DITEMUKAN';
            $hetContextString = ""; // Reset
            if ($het > 0) {
                $hetContextString = "HET (Harga Eceran Tertinggi): " . $het > 1000000 ?  $hetContext['het'] : $het . " berdasarkan " . $hetContext['peraturan'] . ".";
                $status = "HET DITEMUKAN";
                // if ($latestPrice > $het) {
                //     $selisih = number_format((($latestPrice / $het) - 1) * 100, 2);
                //     $status = "DI ATAS HET ({$selisih}% lebih tinggi)";
                // } else {
                //     $status = "DI BAWAH HET";
                // }
            } elseif (!empty($hapString) && str_contains($hapString, '-')) {
                list($hapBawah, $hapAtas) = array_map(fn($val) => (int) filter_var($val, FILTER_SANITIZE_NUMBER_INT), explode('-', $hapString));
                $hetContextString = "HAP (Harga Acuan Penjualan): Rp " . number_format($hapBawah, 0, ',', '.') . " - Rp " . number_format($hapAtas, 0, ',', '.') . " berdasarkan " . $hetContext['peraturan'] . ".";
                if ($latestPrice > $hapAtas) $status = "DI ATAS RENTANG HAP";
                elseif ($latestPrice < $hapBawah) $status = "DI BAWAH RENTANG HAP";
                else $status = "DI DALAM RENTANG HAP";
            } elseif (!empty($hapString)) {
                $hap = (int) filter_var($hapString, FILTER_SANITIZE_NUMBER_INT);
                $hetContextString = "HAP (Harga Acuan Penjualan): Rp " . number_format($hap, 0, ',', '.') . " berdasarkan " . $hetContext['peraturan'] . ".";
                if ($latestPrice > $hap) $status = "DI ATAS HAP";
                else $status = "DI BAWAH HAP";
            }

            $hetContextString .= "\n- Status Harga Saat Ini: {$status}";

            // Buat blok teks untuk disuntikkan ke dalam prompt
            $hetSection = <<<HET
**## Konteks Harga Acuan (HET/HAP) ##**
{$hetContextString}
HET;
            // Ganti instruksi default jika ada HET/HAP
            $hetInstruction = <<<HET_INST
*   **HET (Harga Eceran Tertinggi):** Batas atas harga yang mengikat secara hukum. Harga di atas HET adalah pelanggaran dan memerlukan perhatian serius.
*   **HAP (Harga Acuan Penjualan):** Rentang harga wajar. Harga di luar rentang ini adalah **pemicu (trigger)** untuk analisis, bukan pelanggaran.
HET_INST;
        }


        // --- PERUBAHAN UTAMA 2: MEMBUAT KONTEKS CUACA MENJADI KONDISIONAL ---
        $weatherSection = "";
        if ($isWeatherRelevant && !empty($weatherContext)) {
            $weatherContextJson = json_encode($weatherContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $weatherSection = <<<WEATHER
**## Konteks Cuaca Aktual (Data dari API - 7 Hari Terakhir) ##**
*Catatan: Konteks cuaca ini sangat relevan untuk analisis komoditas hortikultura/pertanian.*
{$weatherContextJson}
WEATHER;
        }

        $komoditasNama = $komoditas->nama;
        $kategoriKomoditas = $this->getKategoriKomoditas($komoditas->nama);
        $tanggalAnalisis = $currentDate->isoFormat('dddd, D MMMM YYYY');
        $musimInfo = $this->getMusimInfo($currentDate);
        $hbknTerdekat = $this->getHBKNTerdekat($currentDate);

        // PERBAIKAN: Tambah informasi debugging dalam prompt
        $debugInfo = "Data points used: {$stats['data_points_used']}, Calculation: {$stats['calculation_method']}";

        $prompt = <<<PROMPT
**# SISTEM INSTRUKSI UTAMA #**
Anda adalah "Analis Kontekstual", sebuah AI yang dirancang untuk memberikan dukungan keputusan kepada Tim Pengendali Inflasi Daerah (TPID) Kabupaten Mempawah. Peran Anda adalah mengubah data harga mentah menjadi wawasan strategis yang sadar konteks, memberdayakan, dan transparan.
Gunakan bahasa yang lebih mudah dipahami oleh pembaca non-finansial dan non-statistik.

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

**## 1.4. Konteks Spesifik untuk Komoditas: {$komoditasNama} ##**
Gunakan informasi ini sebagai petunjuk utama saat melakukan analisis penyebab (Causal Analysis):
{$specificCommodityContext}

**## 1.5. Konteks Harga Acuan (HET/HAP) ##**
Ini adalah pilar kebijakan harga pangan nasional. Gunakan ini untuk menilai urgensi pergerakan harga:
*   **HET (Harga Eceran Tertinggi):** Batas atas harga yang mengikat secara hukum. Harga di atas HET adalah pelanggaran dan memerlukan perhatian serius.
*   **HAP (Harga Acuan Penjualan):** Rentang harga wajar. Harga di luar rentang ini (di atas atau di bawah) adalah **pemicu (trigger)** untuk analisis lebih lanjut atau intervensi pemerintah, bukan pelanggaran.
*   **Tanpa Harga Acuan:** Harga diserahkan pada mekanisme pasar. Analisis berfokus pada volatilitas dan tren historis.
Ini adalah pilar kebijakan harga pangan nasional. Gunakan ini untuk menilai urgensi pergerakan harga:
{$hetInstruction}

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

**## 1.4. Interpretasi Indeks Volatilitas (WAJIB DIGUNAKAN) ##**
Anda akan diberikan tiga metrik volatilitas utama:
1.  **Coefficient of Variation (CV):** Tingkat fluktuasi harga saat ini.
2.  **Ambang Batas Volatilitas Historis (P95):** Level CV yang dianggap "ekstrem" berdasarkan data historis 2 tahun terakhir (level persentil ke-95).
3.  **Indeks Volatilitas (0-100):** Hasil perbandingan CV saat ini terhadap Ambang Batas Historis (`Indeks = (CV / Ambang Batas) * 100`).

**TUGAS ANDA:** Dalam output JSON, khususnya di field `key_observation` dan `volatility_analysis`, Anda **HARUS** menjelaskan hubungan antara ketiga metrik ini. Jangan hanya menyebutkan angka indeksnya. Jelaskan bahwa indeks tersebut tinggi/rendah **KARENA** CV saat ini sudah mendekati/jauh dari ambang batas historisnya.

**Contoh Kalimat yang Baik:** "Indeks volatilitas mencapai 75.2, yang tergolong TINGGI. Ini karena tingkat fluktuasi harga saat ini (CV 37.6%) telah mencapai lebih dari tiga perempat dari ambang batas volatilitas ekstrem yang secara historis jarang terjadi untuk komoditas ini (50.0%)."

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

{$hetSection}

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
- **Ambang Batas Volatilitas Historis (P95):** {$dynamicThresholdUsedFormatted}
- **Indeks Volatilitas (0-100):** {$volatilityIndexFormatted}
- **Debug Info:** {$debugInfo}

**## Riwayat Harga (7 Hari Terakhir) ##**
{$priceHistoryJson}


{$weatherSection}



---
**# BAGIAN 3: INSTRUKSI OUTPUT JSON v2.0 #**
Berdasarkan **Knowledge Base** di Bagian 1 dan **Data** di Bagian 2, hasilkan output dalam format **JSON MURNI**.
**PENTING**: Jika tidak ada data HET/HAP untuk komoditas ini (ditandai dengan tidak adanya bagian "Konteks Harga Acuan" di atas), JANGAN menyinggung HET atau HAP sama sekali di dalam `key_observation` Anda. Fokuslah pada perbandingan dengan data historis (rata-rata, min/max).

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
        "causal_hypothesis": "string (Hipotesis utama penyebab pergerakan harga berdasarkan pola data dan konteks musiman, jika ada penurunan harga ke rentang aman juga bisa jadi karena intervensi pasar sebelumnya)",
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

    // Tambahkan method baru ini di dalam class TpidReportService

    /**
     * Menghitung dan mengambil threshold volatilitas dinamis (persentil ke-95 dari CV historis)
     * untuk sebuah komoditas. Hasilnya di-cache untuk meningkatkan kinerja.
     *
     * @param Komoditas $komoditas
     * @return float
     */
    private function getDynamicVolatilityThreshold(Komoditas $komoditas): float {
        $cacheKey = 'volatility_threshold_' . $komoditas->id_komoditas;
        $cacheDuration = now()->addHours(6); // Cache lebih sering untuk data yang dinamis

        return Cache::remember($cacheKey, $cacheDuration, function () use ($komoditas) {
            // PERBAIKAN: Batasi query untuk performa yang lebih baik
            $twoYearsAgo = now()->subYears(2);
            $allPrices = Harga::where('id_komoditas', $komoditas->id_komoditas)
                ->get()
                ->filter(function ($price) {
                    try {
                        $date = Carbon::createFromFormat('d/m/Y', $price->tanggal);
                        return $price->harga > 0 && $date->gte(now()->subYears(2));
                    } catch (\Exception $e) {
                        return false;
                    }
                })
                ->sortBy(function ($price) {
                    return Carbon::createFromFormat('d/m/Y', $price->tanggal);
                })
                ->values();

            // PERBAIKAN: Threshold minimum yang lebih realistis
            $minimumDataPoints = 60; // Setidaknya 2 bulan data
            if ($allPrices->count() < $minimumDataPoints) {
                return $this->getStaticThresholdForCategory($komoditas);
            }

            $historicalCvs = new Collection();
            $windowSize = 30; // PERBAIKAN: Window size yang lebih kecil dan realistis

            for ($i = $windowSize; $i < $allPrices->count(); $i++) {
                $window = $allPrices->slice($i - $windowSize, $windowSize);
                $priceValues = $window->pluck('harga')->map(fn($p) => (float)$p);

                $average = $priceValues->avg();
                if ($average > 0 && $priceValues->count() > 1) {
                    // Gunakan sample standard deviation
                    $variance = $priceValues->map(fn($val) => pow($val - $average, 2))->sum() / ($priceValues->count() - 1);
                    $stdDev = sqrt($variance);
                    $cv = ($stdDev / $average) * 100;
                    $historicalCvs->push($cv);
                }
            }

            if ($historicalCvs->isEmpty()) {
                return $this->getStaticThresholdForCategory($komoditas);
            }

            // PERBAIHAN: Gunakan percentile yang lebih konservatif
            $sortedCvs = $historicalCvs->sort()->values();
            $count = $sortedCvs->count();

            // Gunakan P90 instead of P95 untuk threshold yang lebih realistis
            $percentileIndex = floor(0.90 * ($count - 1));
            $percentileValue = $sortedCvs[$percentileIndex];

            // PERBAIKAN: Minimum threshold yang lebih masuk akal
            return max(5.0, $percentileValue);
        });
    }

    /**
     * Helper untuk fallback ke threshold statis berbasis kategori.
     * (Ini adalah logika dari jawaban sebelumnya)
     */
    private function getStaticThresholdForCategory(Komoditas $komoditas): float {
        $kategori = $this->getKategoriKomoditas($komoditas->nama);

        // PERBAIKAN: Threshold yang lebih realistis berdasarkan observasi pasar
        $thresholds = [
            'POKOK' => 8.0,    // Komoditas pokok relatif stabil
            'PENTING' => 20.0,  // Komoditas penting agak volatil
            'VOLATIL' => 35.0,  // Komoditas volatil memang tinggi fluktuasinya
        ];

        return $thresholds[$kategori] ?? 20.0;
    }

    /**
     * Menentukan apakah konteks cuaca relevan untuk dianalisis bagi komoditas tertentu.
     *
     * @param Komoditas $komoditas
     * @return bool
     */
    private function isWeatherSensitive(Komoditas $komoditas): bool {
        $namaLower = strtolower($komoditas->nama);

        // Komoditas yang sangat dipengaruhi cuaca (hortikultura, pertanian primer)
        $sensitiveKeywords = [
            'cabai',
            'bawang',
            'tomat',
            'kentang',
            'sawi',
            'kangkung',
            'timun',
            'kacang panjang',
            'sayur',
            'ikan',
            'udang',
            'padi',
            'beras',
            'jagung'
        ];

        foreach ($sensitiveKeywords as $keyword) {
            if (str_contains($namaLower, $keyword)) {
                return true;
            }
        }

        // Komoditas yang kurang dipengaruhi cuaca (olahan, impor, ternak dalam kandang)
        return false;
    }

    /**
     * Memberikan petunjuk konteks spesifik berdasarkan jenis komoditas untuk
     * membantu AI dalam melakukan analisis penyebab.
     *
     * @param Komoditas $komoditas
     * @return string
     */
    private function getCommoditySpecificContext(Komoditas $komoditas): string {
        $namaLower = strtolower($komoditas->nama);
        $contexts = [];

        // Konteks untuk Produk Hortikultura (Sangat Volatil)
        if (str_contains($namaLower, 'cabai') || str_contains($namaLower, 'bawang')) {
            $contexts[] = "- **Sisi Pasokan (Supply-Side):** Sangat rentan terhadap perubahan cuaca (curah hujan tinggi/kekeringan), serangan hama, dan siklus panen raya/paceklik.";
            $contexts[] = "- **Sisi Distribusi:** Rantai pasok yang panjang dan sifat produk yang mudah busuk dapat menyebabkan fluktuasi harga yang cepat.";
            $contexts[] = "- **Sisi Permintaan (Demand-Side):** Permintaan cenderung meningkat tajam menjelang Hari Besar Keagamaan Nasional (HBKN).";
        }
        // Konteks untuk Produk Impor
        elseif (str_contains($namaLower, 'kedelai impor') || str_contains($namaLower, 'bawang putih honan')) {
            $contexts[] = "- **Faktor Global:** Harga sangat dipengaruhi oleh harga di negara asal, biaya pengiriman (freight cost), dan kebijakan perdagangan internasional.";
            $contexts[] = "- **Faktor Domestik:** Nilai tukar Rupiah (USD/IDR) memiliki dampak langsung terhadap harga. Kelancaran proses impor dan bongkar muat di pelabuhan juga menjadi faktor kunci.";
        }
        // Konteks untuk Daging dan Telur (Peternakan)
        elseif (str_contains($namaLower, 'daging ayam') || str_contains($namaLower, 'telur ayam')) {
            $contexts[] = "- **Biaya Produksi:** Harga pakan ternak (terutama jagung dan kedelai) adalah komponen biaya terbesar dan sangat memengaruhi harga jual.";
            $contexts[] = "- **Siklus Produksi:** Adanya siklus _day-old-chick_ (DOC) dan dinamika populasi ayam di tingkat peternak (misal: afkir dini) sangat berpengaruh.";
            $contexts[] = "- **Penyakit/Wabah:** Isu seperti flu burung dapat menyebabkan _supply shock_ yang signifikan.";
        }
        // Konteks untuk Beras
        elseif (str_contains($namaLower, 'beras')) {
            $contexts[] = "- **Kebijakan Pemerintah:** Kebijakan terkait Cadangan Beras Pemerintah (CBP), operasi pasar oleh Bulog, dan penetapan HET sangat dominan.";
            $contexts[] = "- **Musim Tanam/Panen:** Hasil panen raya dan gadu (musim tanam kedua) menentukan ketersediaan gabah nasional.";
            $contexts[] = "- **Distribusi:** Kelancaran distribusi dari sentra produksi ke daerah konsumen menjadi faktor penting.";
        }
        // Konteks untuk Produk Olahan (Minyak, Gula, Tepung)
        elseif (str_contains($namaLower, 'minyak goreng') || str_contains($namaLower, 'gula pasir') || str_contains($namaLower, 'tepung terigu')) {
            $contexts[] = "- **Harga Bahan Baku:** Harga sangat dipengaruhi oleh harga bahan baku internasional (misalnya CPO untuk minyak goreng, gandum untuk tepung).";
            $contexts[] = "- **Kebijakan Industri & Perdagangan:** Kebijakan seperti Domestic Market Obligation (DMO), subsidi, atau tarif impor sangat berpengaruh.";
        }

        if (empty($contexts)) {
            return "- Analisis berfokus pada perbandingan data historis dan tren pasar umum karena tidak ada konteks spesifik yang menonjol untuk komoditas ini.";
        }

        return implode("\n", $contexts);
    }
}
