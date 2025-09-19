<?php

namespace App\Services;

use App\Models\Harga;
use App\Models\Komoditas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TpidReportService {

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
        // Ambil semua data dan lakukan sorting di level collection.
        // Ini kurang efisien dibanding sorting di DB, tapi lebih aman untuk format tanggal string.
        $allPricesForCommodity = Harga::where('id_komoditas', $komoditas->id_komoditas)->get();

        $prices = $allPricesForCommodity
            ->sortBy(fn($p) => Carbon::createFromFormat('d/m/Y', $p->tanggal))
            ->slice(-90); // Ambil 90 data terakhir

        if ($prices->count() < 2) {
            // PERBAIKAN: Kembalikan JSON error yang konsisten, bukan string.
            return json_encode(['error' => "Data untuk komoditas {$komoditas->nama} tidak cukup untuk dianalisis."]);
        }

        // PERBAIKAN: Pastikan data terakhir tidak null sebelum diteruskan.
        $lastData = $prices->last();
        if (!$lastData) {
            return json_encode(['error' => "Gagal mendapatkan data terakhir untuk {$komoditas->nama}."]);
        }

        $statistics = $this->calculateStatistics($prices);

        return $this->buildPrompt($komoditas, $statistics, $currentDate, $lastData->tanggal);
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

        return [
            'latest_price' => $latestPrice,
            'change_daily' => $dayChange,
            'change_weekly' => $weekChange,
            'change_monthly' => $monthChange,
            'average_90d' => $average ?: 0, // Hindari null jika collection kosong
            'max_90d' => $max ?: 0,
            'min_90d' => $min ?: 0,
            'volatility_90d' => $stdDev,
        ];
    }

    /**
     * Membangun string prompt yang diperkaya dengan konteks "Strategic Playbook"
     * untuk menghasilkan analisis dukungan kebijakan yang mendalam dalam format JSON.
     *
     * @param Komoditas $komoditas
     * @param array $stats
     * @param Carbon $currentDate
     * @param string $lastDataDate
     * @return string
     */
    private function buildPrompt(Komoditas $komoditas, array $stats, Carbon $currentDate, string $lastDataDate): string {
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
        $priceToAvgRatio = ($stats['latest_price'] / $stats['average_90d']) * 100;
        $priceToAvgText = number_format($priceToAvgRatio - 100, 2) . '%';
        $coefficientOfVariation = ($stats['volatility_90d'] / $stats['average_90d']) * 100;
        $cvText = number_format($coefficientOfVariation, 2) . '%';

        // Kategorisasi komoditas untuk konteks yang lebih tepat
        $kategoriKomoditas = $this->getKategoriKomoditas($komoditas->nama);

        $komoditasNama = $komoditas->nama;
        $tanggalAnalisis = $currentDate->isoFormat('dddd, D MMMM YYYY');

        // Informasi kontekstual tambahan
        $musimInfo = $this->getMusimInfo($currentDate);
        $hbknTerdekat = $this->getHBKNTerdekat($currentDate);

        $prompt = <<<PROMPT
**# SISTEM INSTRUKSI UTAMA #**
Anda adalah sistem analisis data harga komoditas di Pasar Sebukit Rama, Kabupaten Mempawah, Kalimantan Barat yang dirancang untuk memberikan dukungan informasi kepada Tim Pengendali Inflasi Daerah (TPID) Kabupaten Mempawah. Peran Anda adalah menganalisis data harga berdasarkan pola statistik dan memberikan insight yang dapat digunakan sebagai bahan pertimbangan dalam pengambilan kebijakan.

**## Prinsip Analisis Anda: ##**
1. **Data-Driven:** Setiap kesimpulan harus didukung oleh bukti statistik yang tersedia
2. **Context-Aware:** Pertimbangkan faktor musiman, geografis, dan pola historis
3. **Objective Analysis:** Berikan analisis objektif berdasarkan data tanpa asumsi kondisi eksternal
4. **Decision Support:** Analisis sebagai bahan pertimbangan, bukan keputusan final

**PENTING:** Analisis yang Anda berikan adalah dukungan informasi untuk pengambilan keputusan, bukan rekomendasi resmi atau tindakan yang harus dilakukan. Semua keputusan kebijakan tetap menjadi wewenang dan tanggung jawab TPID dan instansi terkait.

---
**# BAGIAN 1: KNOWLEDGE BASE ANALISIS HARGA #**

**## 1.1. Katalog Pola Perubahan Harga ##**

**### A. Faktor Internal Pasar ###**
* **Pola Musiman:** Variasi harga akibat siklus panen, musim hujan/kemarau, pola konsumsi tradisional
* **Pola Mingguan:** Fluktuasi harga berdasarkan hari pasar, weekend effect, siklus distribusi
* **Volatilitas Normal:** Rentang perubahan harga yang masih dalam batas wajar berdasarkan karakteristik komoditas

**### B. Indikator Statistik Kunci ###**
* **Tren Pergerakan:** Analisis perubahan harga harian, mingguan, dan bulanan
* **Deviasi dari Rata-rata:** Posisi harga saat ini terhadap rata-rata historis
* **Volatilitas:** Tingkat fluktuasi harga dalam periode tertentu
* **Coefficient of Variation:** Ukuran volatilitas relatif terhadap rata-rata harga

**### C. Pola Harga Berdasarkan Jenis Komoditas ###**
* **Komoditas Pokok:** Biasanya memiliki volatilitas rendah, perubahan gradual
* **Komoditas Volatil:** Fluktuasi harga tinggi, sensitif terhadap faktor eksternal
* **Komoditas Musiman:** Pola perubahan mengikuti siklus musiman yang dapat diprediksi

**## 1.2. Framework Klasifikasi Kondisi Harga ##**

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

**## 1.3. Konteks Regional Kabupaten Mempawah ##**
* **Geografis:** Daerah pesisir dengan karakteristik iklim tropis
* **Akses Distribusi:** Ketergantungan pada jalur darat dan laut
* **Karakteristik Pasar:** Pasar tradisional dengan pola distribusi lokal
* **Faktor Musiman:** Pengaruh musim hujan dan kemarau terhadap produksi dan distribusi

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

**## Data Harga dan Statistik ##**

**### A. Kondisi Harga Terkini ###**
- **Harga Saat Ini:** Rp {$latestPriceFormatted}
- **Posisi terhadap Rata-rata:** {$priceToAvgText} dari rata-rata 90 hari

**### B. Dinamika Perubahan ###**
- **Perubahan Harian:** {$dailyChangeText}
- **Perubahan Mingguan:** {$weeklyChangeText}
- **Perubahan Bulanan:** {$monthlyChangeText}

**### C. Profil Statistik 90 Hari Terakhir ###**
- **Rata-rata Harga:** Rp {$average90dFormatted}
- **Harga Tertinggi:** Rp {$max90dFormatted}
- **Harga Terendah:** Rp {$min90dFormatted}
- **Volatilitas (Std Dev):** Rp {$volatility90dFormatted}
- **Coefficient of Variation:** {$cvText}

---
**# BAGIAN 3: INSTRUKSI OUTPUT #**

Berdasarkan analisis data statistik yang tersedia, berikan output dalam format **JSON murni** dengan struktur sebagai berikut:

**## Schema JSON Output ##**
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

**## Panduan Analisis ##**

**### 1. Fokus pada Data ###**
- Analisis hanya berdasarkan data statistik yang tersedia
- Hindari spekulasi tentang faktor eksternal yang tidak terdokumentasi dalam data
- Berikan interpretasi objektif dari pola statistik

**### 2. Bahasa dan Tone ###**
- Gunakan bahasa formal namun mudah dipahami
- Hindari klaim yang bersifat prediktif absolut
- Fokus pada "indikasi" dan "kemungkinan" berdasarkan data

**### 3. Posisi Sebagai Decision Support ###**
- Tekankan bahwa analisis adalah "dukungan informasi"
- Hindari memberikan "rekomendasi" langsung
- Gunakan frasa seperti "data menunjukkan", "pola mengindikasikan", "berdasarkan statistik"

**### 4. Kualitas Output ###**
- Pastikan semua field terisi dengan informasi relevan
- Gunakan "Tidak tersedia" jika data tidak mencukupi
- Validasi sintaks JSON sebelum output

**### 5. Analysis Confidence Criteria ###**
- **HIGH:** Data lengkap, pola jelas, statistik signifikan
- **MEDIUM:** Data cukup, pola terlihat, beberapa keterbatasan
- **LOW:** Data terbatas, pola tidak jelas, banyak keterbatasan

**## CATATAN PENTING ##**
- Output HANYA dalam format JSON murni tanpa tambahan teks
- Analisis bersifat deskriptif berdasarkan data, bukan preskriptif
- Semua kesimpulan harus dapat ditelusuri kembali ke data yang disediakan
- Hindari menggunakan istilah yang mengimplikasikan kepastian absolut tentang kondisi masa depan

HASILKAN JSON OUTPUT BERDASARKAN DATA YANG TERSEDIA:
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
     * Helper method untuk informasi musim
     */
    private function getMusimInfo(Carbon $date): string {
        $month = $date->month;

        if ($month >= 4 && $month <= 9) {
            return "Musim Kemarau (potensi kekeringan, produksi sayuran menurun)";
        } else {
            return "Musim Hujan (potensi banjir, gangguan distribusi)";
        }
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
