<?php

namespace App\Services;

use App\Models\Harga;
use App\Models\Komoditas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TpidReportService {

    public function getTopMovers($limit = 5, $days = 7) {
        $allCommodities = Komoditas::all();
        $changes = new Collection();

        foreach ($allCommodities as $komoditas) {
            // Ambil harga terbaru dan harga X hari yang lalu
            $latestPriceRecord = Harga::where('id_komoditas', $komoditas->id_komoditas)->orderBy('tanggal', 'desc')->first();

            if (!$latestPriceRecord) {
                continue;
            }

            $comparisonDate = Carbon::createFromFormat('d/m/Y', $latestPriceRecord->tanggal)
                ->subDays($days)
                ->toDateString();
            $comparisonPriceRecord = Harga::where('id_komoditas', $komoditas->id_komoditas)
                ->where('tanggal', '<=', $comparisonDate)
                ->orderBy('tanggal', 'desc')
                ->first();

            if ($latestPriceRecord && $comparisonPriceRecord && $comparisonPriceRecord->harga > 0) {
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

        // Urutkan berdasarkan nilai absolut perubahan, dari terbesar ke terkecil
        $sorted = $changes->sortByDesc(function ($item) {
            return abs($item['change']);
        });

        return $sorted->take($limit);
    }
    /**
     * Menghasilkan prompt LLM yang komprehensif untuk analisis TPID berdasarkan data harga.
     *
     * @param Komoditas $komoditas
     * @param Carbon $currentDate Tanggal saat ini untuk memberikan konteks.
     * @return string
     */
    public function generateTpidAnalysisPrompt(Komoditas $komoditas, Carbon $currentDate): string {
        // 1. Ambil data harga historis (misal, 90 hari terakhir)
        $prices = Harga::where('id_komoditas', $komoditas->id_komoditas)
            ->where('tanggal', '<=', '2024-11-30') // Sesuai info data Anda
            ->orderBy('tanggal', 'desc')
            ->limit(90)
            ->get()
            ->sortBy('tanggal'); // Urutkan kembali dari terlama ke terbaru

        if ($prices->count() < 2) {
            return "Data untuk komoditas {$komoditas->nama} tidak cukup untuk dianalisis.";
        }

        // 2. Lakukan perhitungan statistik
        $statistics = $this->calculateStatistics($prices);

        // 3. Bangun prompt yang kaya konteks
        return $this->buildPrompt($komoditas, $statistics, $currentDate, $prices->last()->tanggal);
    }

    /**
     * Menghitung statistik kunci dari koleksi data harga.
     *
     * @param Collection $prices
     * @return array
     */
    private function calculateStatistics(Collection $prices): array {
        $latest = $prices->last();
        $latestPrice = (float) $latest->harga;

        // Ambil data pembanding
        $previousDay = $prices->slice(-2, 1)->first();
        $latestDate = Carbon::createFromFormat('d/m/Y', $latest->tanggal);

        $sevenDaysAgo = $prices
            ->where('tanggal', '<=', $latestDate->copy()->subDays(7)->toDateString())
            ->last();

        $thirtyDaysAgo = $prices
            ->where('tanggal', '<=', $latestDate->copy()->subDays(30)->toDateString())
            ->last();

        // Kalkulasi perubahan
        $dayChange = $previousDay ? (($latestPrice - $previousDay->harga) / $previousDay->harga) * 100 : 'N/A';
        $weekChange = $sevenDaysAgo ? (($latestPrice - $sevenDaysAgo->harga) / $sevenDaysAgo->harga) * 100 : 'N/A';
        $monthChange = $thirtyDaysAgo ? (($latestPrice - $thirtyDaysAgo->harga) / $thirtyDaysAgo->harga) * 100 : 'N/A';

        // Kalkulasi statistik deskriptif pada data yang ada
        $priceValues = $prices->pluck('harga')->map(fn($p) => (float)$p);
        $average = $priceValues->avg();
        $max = $priceValues->max();
        $min = $priceValues->min();

        // Kalkulasi Volatilitas (Standard Deviation)
        $mean = $priceValues->avg();
        $stdDev = sqrt($priceValues->map(fn($val) => pow($val - $mean, 2))->sum() / $priceValues->count());

        return [
            'latest_price' => $latestPrice,
            'change_daily' => $dayChange,
            'change_weekly' => $weekChange,
            'change_monthly' => $monthChange,
            'average_90d' => $average,
            'max_90d' => $max,
            'min_90d' => $min,
            'volatility_90d' => $stdDev, // Standar deviasi harga
        ];
    }

    /**
     * Membangun string prompt yang diperkaya dengan konteks "Strategic Playbook"
     * untuk menghasilkan analisis kebijakan yang mendalam dalam format JSON.
     *
     * @param Komoditas $komoditas
     * @param array $stats
     * @param Carbon $currentDate
     * @param string $lastDataDate
     * @return string
     */
    private function buildPrompt(Komoditas $komoditas, array $stats, Carbon $currentDate, string $lastDataDate): string {
        // Persiapan variabel tetap sama
        $dailyChangeText = is_numeric($stats['change_daily']) ? number_format($stats['change_daily'], 2) . '%' : 'N/A';
        $weeklyChangeText = is_numeric($stats['change_weekly']) ? number_format($stats['change_weekly'], 2) . '%' : 'N/A';
        $monthlyChangeText = is_numeric($stats['change_monthly']) ? number_format($stats['change_monthly'], 2) . '%' : 'N/A';
        $latestPriceFormatted = number_format($stats['latest_price'], 0, ',', '.');
        $average90dFormatted = number_format($stats['average_90d'], 0, ',', '.');
        $max90dFormatted = number_format($stats['max_90d'], 0, ',', '.');
        $min90dFormatted = number_format($stats['min_90d'], 0, ',', '.');
        $volatility90dFormatted = number_format($stats['volatility_90d'], 0, ',', '.');
        $komoditasNama = $komoditas->nama;
        $tanggalAnalisis = $currentDate->isoFormat('dddd, D MMMM YYYY');

        $prompt = <<<PROMPT
**# KONTEKS PERMINTAAN UTAMA #**
Anda adalah sebuah API canggih yang berfungsi sebagai Ahli Penasihat Kebijakan Ekonomi untuk Tim Pengendali Inflasi Daerah (TPID) Kabupaten Mempawah. Tugas Anda adalah menginternalisasi "Strategic Playbook TPID" yang diberikan, menganalisis data statistik harian, dan menghasilkan laporan analisis kebijakan yang mendalam dalam format JSON yang ketat.

---
**# BAGIAN 1: STRATEGIC PLAYBOOK TPID (INTERNALISASI PENGETAHUAN) #**

**## 1.1. Katalog Pemicu Perubahan Harga (Triggers) ##**
*   **Sisi Pasokan:** Gangguan cuaca, hama/penyakit, gagal panen, kenaikan harga input (pupuk, pakan), gangguan di sentra produksi utama.
*   **Sisi Permintaan:** HBKN (Hari Besar Keagamaan Nasional), panic buying, perubahan pola konsumsi, penyaluran bansos.
*   **Sisi Distribusi & Kebijakan:** Kerusakan infrastruktur, praktik penimbunan, kenaikan harga BBM, perubahan regulasi.

**## 1.2. Kerangka Kerja Ambang Batas Statistik (Thresholds) ##**
*   **Level 1: WASPADA (Kuning):** Pergerakan tidak biasa, butuh pantauan intensif.
    *   *Contoh Volatil (Cabai):* Naik harian >5% (2 hari), atau naik mingguan >10%.
    *   *Contoh Pokok (Beras):* Naik harian >1% (3 hari), atau naik mingguan >3%.
*   **Level 2: SIAGA (Oranye):** Tren jelas menuju gangguan, butuh investigasi & persiapan intervensi.
    *   *Contoh:* Naik mingguan >15% (volatil) atau >5% (pokok), harga melampaui HET, atau 3+ komoditas naik signifikan bersamaan.
*   **Level 3: AWAS (Merah):** Risiko tinggi, butuh intervensi segera.
    *   *Contoh:* Naik mingguan >25% (apapun), 5+ komoditas naik >10% bersamaan, laporan kelangkaan masif.

**## 1.3. Matriks Intervensi Kebijakan (Wewenang Kab/Kota) ##**
*   **Jika Sinyal Oranye & Dugaan Gangguan Pasokan:** Sidak gudang, lapor ke provinsi, siapkan Gerakan Pangan Murah (GPM).
*   **Jika Sinyal Kuning & Dekat HBKN:** Imbauan publik anti-panic buying, konfirmasi jadwal pasokan ke distributor.
*   **Jika Sinyal Merah & Dugaan Penimbunan:** Sidak mendadak, koordinasi erat dengan Satgas Pangan (Polri), operasi pasar darurat.
*   **Jika Sinyal Oranye & Gangguan Infrastruktur:** Koordinasi dengan Dinas PU, cari jalur alternatif.
---
**# BAGIAN 2: DATA HARIAN UNTUK DIANALISIS #**

- **Wilayah:** Kabupaten Mempawah
- **Komoditas:** {$komoditasNama}
- **Tanggal Analisis (Hari Ini):** {$tanggalAnalisis}
- **Data Tersedia Hingga:** {$lastDataDate}

**## Data Statistik Harga (90 Hari Terakhir): ##**
- **Harga Terakhir Tercatat:** Rp {$latestPriceFormatted}
- **Perubahan Harian (DoD):** {$dailyChangeText}
- **Perubahan Mingguan (WoW):** {$weeklyChangeText}
- **Perubahan Bulanan (MoM):** {$monthlyChangeText}
- **Harga Rata-rata (90 Hari):** Rp {$average90dFormatted}
- **Harga Tertinggi (90 Hari):** Rp {$max90dFormatted}
- **Harga Terendah (90 Hari):** Rp {$min90dFormatted}
- **Tingkat Volatilitas (Std Dev, 90 Hari):** Rp {$volatility90dFormatted}
---
**# BAGIAN 3: TUGAS UTAMA ANDA #**

Berdasarkan internalisasi **"Strategic Playbook"** di Bagian 1 dan analisis **"Data Harian"** di Bagian 2, buatlah laporan dalam format **JSON yang valid dan murni**. Jangan sertakan teks atau markdown di luar blok JSON.

**Struktur JSON harus mengikuti skema berikut:**
```json
{
  "commodity_name": "string",
  "analysis_date": "string",
  "risk_level": {
    "level": "string (NORMAL / WASPADA / SIAGA / AWAS)",
    "rationale": "string"
  },
  "executive_summary": "string",
  "detailed_analysis": "string",
  "suspected_triggers": [
    "string",
    "string"
  ],
  "recommended_actions": [
    {
      "priority": "integer (1-3, 1=tertinggi)",
      "action": "string",
      "stakeholder": "string"
    }
  ],
  "investigative_questions": [
    "string",
    "string",
    "string"
  ]
}
```

**## Penjelasan Isi Setiap Key: ##**
1.  **`commodity_name`**: Nama komoditas yang dianalisis.
2.  **`analysis_date`**: Tanggal analisis hari ini.
3.  **`risk_level`**: Sebuah objek yang berisi:
    *   **`level`**: Tentukan level risiko berdasarkan **Kerangka Ambang Batas Statistik** dari playbook. Pilih salah satu: "NORMAL", "WASPADA", "SIAGA", atau "AWAS".
    *   **`rationale`**: Jelaskan secara singkat (1 kalimat) mengapa Anda memilih level risiko tersebut, dengan merujuk pada data statistik.
4.  **`executive_summary`**: Ringkasan eksekutif singkat (2-3 kalimat) mengenai kondisi harga dan level risiko saat ini.
5.  **`detailed_analysis`**: Analisis data yang lebih mendalam. Jelaskan tren, perbandingan harga dengan rata-rata, dan signifikansi volatilitas. Gunakan `\n` untuk paragraf baru.
6.  **`suspected_triggers`**: Berdasarkan **Katalog Pemicu** dari playbook dan pola data, berikan **hipotesis** 1-2 pemicu yang paling mungkin terjadi. Awali dengan kata "Dugaan:" atau "Potensi:". Ini adalah bagian inferensi Anda.
7.  **`recommended_actions`**: Sebuah **array of objects** yang berisi tindakan konkret. Berdasarkan level risiko dan pemicu yang diduga, rujuk pada **Matriks Intervensi** dari playbook. Setiap objek harus berisi:
    *   **`priority`**: Prioritas tindakan (1 untuk paling mendesak).
    *   **`action`**: Deskripsi tindakan yang harus dilakukan.
    *   **`stakeholder`**: Pihak utama yang harus dilibatkan (contoh: "Dinas Perdagangan, Satgas Pangan").
8.  **`investigative_questions`**: Sebuah **array of strings** berisi pertanyaan investigatif tajam untuk diajukan dalam rapat TPID guna memvalidasi dugaan pemicu dan mempersiapkan langkah selanjutnya.

**## Aturan Penting: ##**
- **Sintesis, Jangan Mengarang:** Gunakan playbook untuk membuat koneksi logis antara data dan rekomendasi. Jangan membuat informasi di luar konteks yang diberikan.
- **Output JSON Murni:** Pastikan output Anda adalah JSON yang valid tanpa teks pembuka/penutup.
PROMPT;

        return $prompt;
    }
}
