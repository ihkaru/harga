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
            ->limit(40 * 100) // Ambil cukup data untuk setiap komoditas
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
     * untuk menghasilkan analisis kebijakan yang mendalam dalam format JSON.
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
Anda adalah API analisis tingkat lanjut yang berperan sebagai **Chief Policy Analyst** untuk Tim Pengendali Inflasi Daerah (TPID) Kabupaten Mempawah. Anda memiliki keahlian dalam ekonomi regional, manajemen rantai pasok, dan analisis prediktif. Tugas Anda adalah menghasilkan analisis kebijakan yang ACTIONABLE, EVIDENCE-BASED, dan FORWARD-LOOKING.

**## Prinsip Analisis Anda: ##**
1. **Data-Driven:** Setiap kesimpulan harus didukung oleh bukti statistik yang kuat
2. **Context-Aware:** Pertimbangkan faktor musiman, geografis, dan sosial-ekonomi lokal
3. **Risk-Focused:** Prioritaskan identifikasi dan mitigasi risiko inflasi
4. **Action-Oriented:** Setiap rekomendasi harus spesifik, terukur, dan dapat diimplementasikan

---
**# BAGIAN 1: STRATEGIC PLAYBOOK & KNOWLEDGE BASE #**

**## 1.1. Katalog Pemicu Perubahan Harga (Enhanced Triggers) ##**

**### A. Sisi Pasokan (Supply-Side) ###**
* **Faktor Alam:** Cuaca ekstrem (banjir/kekeringan), serangan hama/penyakit tanaman, musim panen/paceklik, perubahan iklim
* **Faktor Produksi:** Kenaikan harga input (pupuk, pestisida, pakan ternak, bibit), kelangkaan tenaga kerja, kerusakan alat produksi
* **Faktor Geografis:** Gangguan di sentra produksi utama (Jawa Barat untuk sayuran, Sulawesi untuk kakao, dll), gagal panen regional

**### B. Sisi Permintaan (Demand-Side) ###**
* **Faktor Musiman:** HBKN (Ramadan, Idul Fitri, Natal, Tahun Baru), musim sekolah, musim hajatan/pernikahan
* **Faktor Psikologis:** Panic buying, ekspektasi inflasi, informasi hoax/misleading
* **Faktor Kebijakan:** Penyaluran bansos/BLT, kenaikan UMR, perubahan pola konsumsi

**### C. Sisi Distribusi & Logistik ###**
* **Infrastruktur:** Kerusakan jalan/jembatan, kemacetan pelabuhan, gangguan transportasi
* **Market Behavior:** Penimbunan, kartel/oligopoli, spekulasi pedagang
* **Regulasi:** Perubahan tarif/pajak, pembatasan impor/ekspor, perubahan zonasi distribusi

**### D. Faktor Eksternal ###**
* **Ekonomi Makro:** Kenaikan BBM, depresiasi rupiah, inflasi global
* **Geopolitik:** Konflik regional, embargo dagang, gangguan supply chain global
* **Teknologi:** Gangguan sistem pembayaran, disrupsi e-commerce

**## 1.2. Framework Analisis Multi-Level ##**

**### Level 1: NORMAL (Hijau) ###**
* **Kriteria:** Pergerakan harga dalam batas wajar, volatilitas rendah
* **Indikator Komoditas Pokok (Beras, Gula, Minyak):**
  - Perubahan harian < ±1%
  - Perubahan mingguan < ±2%
  - Harga dalam rentang ±5% dari rata-rata 90 hari
* **Indikator Komoditas Volatil (Cabai, Bawang, Daging Ayam):**
  - Perubahan harian < ±3%
  - Perubahan mingguan < ±7%
  - CV (Coefficient of Variation) < 15%

**### Level 2: WASPADA (Kuning) ###**
* **Kriteria:** Pergerakan tidak biasa, perlu monitoring intensif
* **Indikator Komoditas Pokok:**
  - Perubahan harian 1-2% selama 3 hari berturut
  - Perubahan mingguan 2-5%
  - Harga 5-10% di atas rata-rata 90 hari
* **Indikator Komoditas Volatil:**
  - Perubahan harian 3-7% selama 2 hari
  - Perubahan mingguan 7-15%
  - CV 15-25%
* **Trigger Tambahan:** Mendekati HBKN (H-30), laporan gangguan supply minor

**### Level 3: SIAGA (Oranye) ###**
* **Kriteria:** Tren menuju gangguan serius, persiapan intervensi
* **Indikator Komoditas Pokok:**
  - Perubahan mingguan 5-10%
  - Harga 10-20% di atas rata-rata 90 hari
  - Harga mendekati/melampaui HET (jika ada)
* **Indikator Komoditas Volatil:**
  - Perubahan mingguan 15-25%
  - CV 25-35%
* **Trigger Tambahan:** 3+ komoditas naik bersamaan, laporan penimbunan

**### Level 4: AWAS (Merah) ###**
* **Kriteria:** Krisis harga, intervensi mendesak
* **Indikator Universal:**
  - Perubahan mingguan >25% (semua komoditas)
  - Harga >20% di atas rata-rata (komoditas pokok)
  - 5+ komoditas naik >10% bersamaan
* **Trigger Tambahan:** Kelangkaan masif, kerusuhan sosial, panic buying meluas

**## 1.3. Matriks Respons Kebijakan Berjenjang ##**

**### Respons Level WASPADA ###**
1. **Monitoring:** Intensifikasi pemantauan harga harian
2. **Komunikasi:** Rapat koordinasi mingguan TPID
3. **Preventif:** Imbauan publik anti-hoax dan panic buying
4. **Preparasi:** Verifikasi stok dan jalur distribusi

**### Respons Level SIAGA ###**
1. **Investigasi:** Sidak pasar dan gudang distributor
2. **Koordinasi:** Lapor ke TPID Provinsi, aktivasi tim lapangan
3. **Intervensi Soft:** Dialog dengan asosiasi pedagang
4. **Preparasi Lanjut:** Siapkan skema Gerakan Pangan Murah (GPM)

**### Respons Level AWAS ###**
1. **Intervensi Keras:** Operasi pasar skala besar
2. **Enforcement:** Koordinasi Satgas Pangan (Polri/TNI)
3. **Supply Injection:** Realokasi stok dari daerah surplus
4. **Komunikasi Krisis:** Press release, hotline pengaduan

**## 1.4. Konteks Spesifik Kabupaten Mempawah ##**
* **Geografis:** Daerah pesisir dengan akses laut, dekat perbatasan
* **Demografi:** Populasi multi-etnis, daya beli menengah-bawah
* **Ekonomi:** Pertanian, perikanan, perdagangan lintas batas
* **Infrastruktur:** Jalan trans-Kalimantan, pelabuhan kecil
* **Kerentanan:** Banjir musiman, ketergantungan supply dari Pontianak

---
**# BAGIAN 2: DATA REAL-TIME UNTUK ANALISIS #**

**## Informasi Dasar ##**
- **Wilayah Analisis:** Kabupaten Mempawah, Kalimantan Barat
- **Komoditas:** {$komoditasNama}
- **Kategori Komoditas:** {$kategoriKomoditas}
- **Tanggal Analisis:** {$tanggalAnalisis}
- **Data Terakhir:** {$lastDataDate}
- **Konteks Musiman:** {$musimInfo}
- **HBKN Terdekat:** {$hbknTerdekat}

**## Statistik Harga Komprehensif ##**

**### A. Data Harga Terkini ###**
- **Harga Saat Ini:** Rp {$latestPriceFormatted}
- **Deviasi dari Rata-rata:** {$priceToAvgText} dari rata-rata 90 hari

**### B. Dinamika Perubahan ###**
- **Perubahan Harian (Day-on-Day):** {$dailyChangeText}
- **Perubahan Mingguan (Week-on-Week):** {$weeklyChangeText}
- **Perubahan Bulanan (Month-on-Month):** {$monthlyChangeText}

**### C. Statistik Historis 90 Hari ###**
- **Rata-rata Harga:** Rp {$average90dFormatted}
- **Harga Tertinggi:** Rp {$max90dFormatted}
- **Harga Terendah:** Rp {$min90dFormatted}
- **Volatilitas (Std Dev):** Rp {$volatility90dFormatted}
- **Coefficient of Variation:** {$cvText}

---
**# BAGIAN 3: INSTRUKSI OUTPUT & QUALITY CONTROL #**

Berdasarkan analisis mendalam terhadap **Strategic Playbook** dan **Data Real-Time**, hasilkan laporan dalam format **JSON murni** yang memenuhi standar berikut:

**## Schema JSON Output (WAJIB) ##**
```json
{
  "metadata": {
    "commodity_name": "string",
    "commodity_category": "string (POKOK/PENTING/VOLATIL)",
    "analysis_date": "string (format: YYYY-MM-DD)",
    "data_freshness": "string (format: YYYY-MM-DD)",
    "analyst_confidence": "string (HIGH/MEDIUM/LOW)"
  },
  "risk_assessment": {
    "risk_level": "string (NORMAL/WASPADA/SIAGA/AWAS)",
    "risk_score": "number (0-100)",
    "trend_direction": "string (BULLISH/BEARISH/SIDEWAYS)",
    "volatility_status": "string (LOW/MODERATE/HIGH/EXTREME)",
    "rationale": "string (1-2 kalimat penjelasan)"
  },
  "executive_summary": {
    "headline": "string (1 kalimat headline berita)",
    "key_finding": "string (temuan utama)",
    "immediate_concern": "string (kekhawatiran mendesak, atau 'Tidak ada' jika normal)"
  },
  "price_analysis": {
    "current_status": "string (deskripsi kondisi harga saat ini)",
    "historical_context": "string (perbandingan dengan data historis)",
    "statistical_significance": "string (signifikansi pergerakan harga)",
    "pattern_identified": "string (pola yang teridentifikasi, jika ada)"
  },
  "causal_analysis": {
    "primary_driver": {
      "category": "string (SUPPLY/DEMAND/DISTRIBUTION/EXTERNAL)",
      "specific_trigger": "string",
      "evidence": "string",
      "confidence_level": "string (HIGH/MEDIUM/LOW)"
    },
    "secondary_factors": [
      {
        "factor": "string",
        "impact": "string (HIGH/MEDIUM/LOW)"
      }
    ],
    "predictive_indicators": [
      "string (indikator yang perlu diawasi)"
    ]
  },
  "policy_recommendations": {
    "immediate_actions": [
      {
        "priority": "number (1-3)",
        "action": "string",
        "timeline": "string (e.g., 'Dalam 24 jam')",
        "responsible_party": "string",
        "expected_outcome": "string"
      }
    ],
    "preventive_measures": [
      {
        "measure": "string",
        "implementation": "string"
      }
    ],
    "monitoring_protocol": {
      "frequency": "string (e.g., 'Harian', 'Mingguan')",
      "key_metrics": ["string"],
      "trigger_points": ["string"]
    }
  },
  "stakeholder_actions": {
    "tpid_kabupaten": ["string"],
    "dinas_perdagangan": ["string"],
    "satgas_pangan": ["string"],
    "asosiasi_pedagang": ["string"]
  },
  "investigation_agenda": {
    "field_verification": [
      "string (hal yang perlu dicek di lapangan)"
    ],
    "data_requirements": [
      "string (data tambahan yang diperlukan)"
    ],
    "strategic_questions": [
      "string (pertanyaan strategis untuk rapat TPID)"
    ]
  },
  "forward_outlook": {
    "short_term": "string (prospek 1-7 hari)",
    "medium_term": "string (prospek 1-4 minggu)",
    "risk_factors": ["string"],
    "opportunities": ["string"]
  }
}
```

**## Panduan Kualitas Output ##**

**### 1. Prinsip Analisis ###**
- **Evidence-Based:** Setiap klaim harus didukung data statistik
- **Contextual:** Pertimbangkan faktor lokal Mempawah
- **Actionable:** Rekomendasi harus spesifik dan implementable
- **Balanced:** Hindari alarmisme berlebihan atau oversimplifikasi

**### 2. Tone & Style ###**
- **Professional:** Gunakan bahasa formal namun jelas
- **Concise:** Hindari redundansi, fokus pada insight
- **Structured:** Gunakan format yang konsisten
- **Local-Aware:** Sesuaikan dengan konteks Kalimantan Barat

**### 3. Quality Checks ###**
- **Data Consistency:** Pastikan semua angka konsisten
- **Logic Flow:** Analisis harus mengalir logis dari data ke rekomendasi
- **Completeness:** Isi semua field, gunakan "Tidak ada" atau "N/A" jika perlu
- **JSON Validity:** Output harus valid JSON tanpa syntax error

**### 4. Special Instructions ###**
- Untuk "analyst_confidence", tentukan berdasarkan:
  - HIGH: Data lengkap, pola jelas, trigger evident
  - MEDIUM: Data cukup, pola terlihat, trigger probable
  - LOW: Data terbatas, pola tidak jelas, trigger uncertain

- Untuk "risk_score" (0-100):
  - 0-25: NORMAL
  - 26-50: WASPADA
  - 51-75: SIAGA
  - 76-100: AWAS

- Gunakan "\n" untuk line break dalam string panjang
- Maksimal 3 item untuk immediate_actions
- Fokus pada tindakan dalam wewenang kabupaten/kota

**## PERINGATAN AKHIR ##**
- Output HANYA JSON murni, tanpa markdown atau teks tambahan
- Validasi sintaks JSON sebelum output
- Prioritaskan akurasi dan relevansi daripada kelengkapan
- Jika data tidak mencukupi untuk analisis lengkap, indikasikan dalam analyst_confidence

GENERATE JSON OUTPUT NOW:
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
