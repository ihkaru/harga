### **BAGIAN 1: FONDASI DAN VISI PROYEK**

# **Spesifikasi Pengembangan: DSS TPID v2.0 - "Analis Kontekstual"**

Dokumen ini menguraikan spesifikasi untuk pengembangan tahap berikutnya dari Sistem Pendukung Keputusan (DSS) Pengendalian Inflasi Daerah Kabupaten Mempawah. Tujuan utamanya adalah untuk mentransformasi sistem dari sekadar monitor data harga menjadi asisten analisis yang cerdas dan sadar konteks.

---

## **1. Visi & Tujuan Utama**

### **Visi Proyek**

Mengevolusikan sistem dari **"pelapor data"** menjadi **"asisten analisis strategis"** bagi TPID. AI tidak hanya menyajikan _apa_ yang terjadi pada data harga, tetapi juga membantu analis memahami _mengapa_ itu mungkin terjadi dan _apa artinya_ dalam kerangka kerja kelembagaan dan kebijakan TPID.

### **Tujuan Utama**

1.  **Memperkaya Analisis AI:** Mengintegrasikan wawasan dari riset mendalam (kerangka hukum, teori ekonomi, efektivitas intervensi) ke dalam _knowledge base_ LLM untuk menghasilkan analisis yang lebih relevan secara kontekstual.
2.  **Meningkatkan Kepercayaan Pengguna:** Secara transparan mengakui dan mengkomunikasikan keterbatasan data (sumber tunggal) sambil memberikan saran proaktif untuk mitigasi.
3.  **Menyediakan Wawasan yang Relevan:** Menghasilkan butir-butir pertimbangan yang disesuaikan untuk setiap pemangku kepentingan (stakeholder) di dalam TPID, sesuai dengan tugas dan fungsi mereka.
4.  **Menciptakan Umpan Balik:** Mengimplementasikan mekanisme sederhana bagi pengguna untuk memberikan umpan balik terhadap kualitas analisis, sebagai dasar untuk perbaikan berkelanjutan.

---

## **2. Persona Pengguna Utama**

### **Nama:** Analis Kebijakan TPID (Contoh: Staf di Bagian Ekonomi Setda atau analis di Bank Indonesia)

-   **Latar Belakang:** Memiliki pemahaman ekonomi dan kebijakan, namun terikat oleh waktu. Bertanggung jawab menyiapkan bahan untuk rapat koordinasi TPID.
-   **Kebutuhan:**
    -   Memahami dengan cepat signifikansi dari pergerakan harga komoditas.
    -   Mendapatkan konteks di balik angka (misalnya, apakah kenaikan ini normal secara musiman?).
    -   Menyiapkan poin-poin diskusi yang relevan dan berbasis data untuk setiap dinas terkait.
-   **Tantangan Saat Ini (Pain Points):**
    -   Melihat grafik harga naik, tetapi tidak yakin seberapa serius.
    -   Menghabiskan banyak waktu untuk menghubungkan data mentah dengan kemungkinan penyebab atau tindakan kebijakan yang relevan.
    -   Kesulitan merumuskan pertanyaan yang tepat untuk dinas teknis saat rapat.
-   **Tujuan dengan Sistem Baru:**
    -   "Dalam 5 menit, saya bisa mendapatkan ringkasan kondisi harga komoditas strategis, lengkap dengan hipotesis penyebab dan pertanyaan kunci untuk saya sampaikan dalam rapat TPID."

---

## **3. Prinsip Panduan Pengembangan**

Ini adalah "vibe" atau filosofi yang harus menjadi panduan setiap baris kode yang ditulis.

1.  **Konteks di Atas Prediksi:** Fokus sistem adalah memberikan **konteks** yang kaya pada data historis, bukan membuat prediksi harga yang absolut.
2.  **Pertanyaan yang Memberdayakan, Bukan Jawaban yang Mendikte:** Output AI harus merangsang pemikiran kritis dan diskusi dengan menyajikan **"pertanyaan untuk dipertimbangkan"** atau **"poin untuk dimonitor"**, bukan **"rekomendasi tindakan"** yang kaku.
3.  **Transparansi adalah Kunci:** Sistem harus secara proaktif dan jujur mengkomunikasikan keterbatasannya (misalnya, "Analisis ini hanya berdasarkan data Pasar Sebukit Rama").
4.  **Manusia sebagai Pusat (Human-in-the-Loop):** AI adalah alat bantu untuk memperkuat keahlian analis manusia, bukan untuk menggantikannya. Desain dan output harus selalu mempertimbangkan bagaimana manusia akan berinteraksi dengannya.

### **BAGIAN 2: SPESIFIKASI FUNGSIONAL & TEKNIS**

Bagian ini merinci perubahan dan penambahan fungsional yang diperlukan pada backend, AI core, dan frontend.

---

## **4. Backend & AI Core (`TpidReportService`)**

Komponen ini adalah otak dari sistem. Perubahan utama akan terjadi pada logika pembuatan prompt dan struktur data yang diharapkan dari LLM.

### **4.1. Spesifikasi `buildPrompt()` - Peningkatan _Knowledge Base_**

Metode `buildPrompt()` dalam `TpidReportService` harus diperkaya dengan konteks dari riset. Prompt tidak lagi hanya berisi data statistik, tetapi juga "kerangka berpikir" seorang analis TPID.

**Requirement:** Tambahkan seksi-seksi berikut ke dalam _knowledge base_ (Bagian 1) dari `prompt` string:

1.  **Framework Analisis 4 Lapis (Konseptual):**

    -   **Causal Analysis:** Instruksikan AI untuk memberikan hipotesis penyebab berdasarkan pola data (misalnya, suplai vs. permintaan, musiman).
    -   **Impact Projection:** Instruksikan AI untuk mengaitkan pergerakan harga dengan potensi dampaknya terhadap daya beli (misalnya, "beras sebagai komoditas pokok").
    -   **Intervention Strategy Framing:** Instruksikan AI untuk merumuskan **pertanyaan kunci** bagi stakeholder, bukan rekomendasi. _Contoh: "Data menunjukkan gejolak. Pertanyaan untuk Dinas Perdagangan: Apakah stok di tingkat distributor perlu diverifikasi?"_

2.  **Konteks Kelembagaan TPID:**

    -   Sertakan pemetaan peran dan fokus masing-masing stakeholder (Kepala Daerah, Sekda, BI, SKPD Teknis) seperti yang diuraikan dalam riset. Ini akan menjadi dasar bagi AI untuk menghasilkan pertimbangan yang relevan per stakeholder.

3.  **Katalog Efektivitas Intervensi:**
    -   Sertakan ringkasan dari riset Bab V mengenai efektivitas intervensi (Operasi Pasar, HET, dll.). Ini akan menjadi "contekan" bagi AI untuk membingkai pertanyaan kunci dengan lebih cerdas.

### **4.2. Spesifikasi Skema Output JSON**

Struktur JSON yang diminta dari LLM harus diperluas untuk mengakomodasi analisis yang lebih dalam. Ini adalah "kontrak" data antara backend dan frontend.

**Requirement:** Modifikasi skema JSON output yang diminta dari LLM untuk menyertakan struktur berikut:

```json
{
    "metadata": {
        /* ... (tidak berubah) ... */
    },
    "price_condition_assessment": {
        /* ... (tidak berubah) ... */
    },
    "data_insights": {
        /* ... (tidak berubah) ... */
    },
    "statistical_findings": {
        /* ... (tidak berubah) ... */
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

    "information_support": {
        "key_metrics_to_watch": [
            /* ... (tidak berubah) ... */
        ],
        "additional_data_suggestions": [
            "string (Saran proaktif untuk validasi data, misal: 'Bandingkan dengan data dari pasar lain jika tersedia.')",
            "string (Saran data kualitatif, misal: 'Verifikasi ketersediaan stok dengan petugas lapangan.')"
        ]
    },

    "analysis_limitations": {
        "data_constraints": [
            "string (Pernyataan eksplisit tentang bias geografis dari data tunggal)"
        ],
        "external_factors_note": "string (Pengingat bahwa analisis tidak mencakup faktor eksternal yang tidak tercermin dalam data harga)"
    }
}
```

---

## **5. Frontend (Antarmuka Pengguna)**

Tampilan antarmuka harus dirombak untuk menyajikan wawasan baru ini dengan cara yang jelas, ringkas, dan dapat ditindaklanjuti.

### **5.1. Komponen Utama: Kartu "Ringkasan Analis"**

Ini adalah tampilan utama untuk setiap analisis komoditas.

**Requirement:**

-   Desain ulang kartu analisis untuk menampilkan informasi baru dengan jelas.
-   Gunakan ikonografi dan kode warna untuk menyoroti level kondisi harga (misal: Hijau untuk Stabil, Kuning untuk Fluktuatif, Merah untuk Bergejolak).
-   Tampilkan `key_observation` dan `causal_hypothesis` secara menonjol di bagian atas kartu.

### **5.2. Komponen Baru: "Pertimbangan untuk Pemangku Kepentingan"**

Ini adalah fitur inti baru yang menerjemahkan data menjadi agenda diskusi.

**Requirement:**

-   Buat sebuah area di bawah ringkasan utama (bisa menggunakan _tabs_ atau _accordion_).
-   Setiap tab/panel sesuai dengan stakeholder dalam `stakeholder_specific_considerations` (misal: "Dinas Perdagangan", "Dinas Pertanian", "Koordinator TPID").
-   Saat diklik, tampilkan poin-poin atau pertanyaan kunci yang relevan untuk stakeholder tersebut.
-   **Vibe:** Pengguna harus merasa seperti mendapatkan _briefing notes_ yang sudah disiapkan untuk rapat.

### **5.3. Komponen Baru: Kotak "Transparansi Analisis"**

Komponen ini bertujuan untuk membangun kepercayaan dengan pengguna.

**Requirement:**

-   Buat sebuah kotak informasi (information box) yang jelas namun tidak mengganggu di bagian bawah analisis.
-   Isi kotak ini dengan konten dari `analysis_limitations` dan `information_support.additional_data_suggestions`.
-   Gunakan judul seperti "Catatan & Keterbatasan Analisis" atau "Cara Membaca Analisis Ini".

### **5.4. Komponen Baru: "Mekanisme Umpan Balik (Feedback)"**

**Requirement:**

-   Tambahkan dua tombol ikon sederhana di bagian bawah setiap kartu analisis: "👍 Analisis Membantu" dan "👎 Kurang Relevan".
-   **Aksi Frontend:** Saat diklik, tombol berubah menjadi status "terpilih" (misal: berwarna) dan mengirimkan permintaan API sederhana ke backend.
-   **API Endpoint (Backend):** Buat endpoint baru, misal `POST /api/analysis-feedback`, yang menerima `commodity_id`, `analysis_date`, dan `feedback_type` ('positive' atau 'negative').
-   **Aksi Backend:** Simpan data umpan balik ini ke dalam tabel database baru (`analysis_feedbacks`) untuk analisis di masa depan.

### **BAGIAN 3: IMPLEMENTASI, SUKSES, DAN CONTOH**

Bagian ini memastikan bahwa visi dan spesifikasi teknis dapat diimplementasikan secara bertahap dan kesuksesannya dapat diukur.

---

## **6. Rencana Implementasi Bertahap (Roadmap)**

Untuk mengelola kompleksitas, pengembangan akan dibagi menjadi tiga fase yang jelas.

### **Fase 1: Penguatan Backend & AI Core (Prioritas Tertinggi)**

-   **Tujuan:** Membangun fondasi analisis yang cerdas. Frontend masih akan menampilkan output lama (atau JSON mentah) hingga fase ini selesai.
-   **Tugas Utama:**
    1.  Modifikasi `TpidReportService` untuk mengimplementasikan `buildPrompt()` versi 2.0 dengan semua _knowledge base_ tambahan.
    2.  Definisikan dan uji coba skema JSON output v2.0 yang baru. Lakukan iterasi pada prompt hingga LLM secara konsisten menghasilkan output dengan struktur dan kualitas yang diinginkan.
    3.  Buat _endpoint_ dan tabel database untuk fitur umpan balik (`analysis_feedbacks`).
-   **Kriteria Selesai:** `TpidReportService` mampu menghasilkan output JSON v2.0 yang valid dan kaya konteks untuk setiap komoditas.

### **Fase 2: Perombakan Frontend (UI/UX Redesign)**

-   **Tujuan:** Menerjemahkan output JSON yang kaya menjadi antarmuka yang intuitif dan bermanfaat bagi pengguna.
-   **Tugas Utama:**
    1.  Implementasikan komponen "Kartu Ringkasan Analis" yang baru.
    2.  Bangun komponen "Pertimbangan untuk Pemangku Kepentingan" (menggunakan tabs/accordion).
    3.  Desain dan implementasikan kotak "Transparansi Analisis".
    4.  Hubungkan tombol umpan balik di frontend ke _endpoint_ API yang sudah dibuat di Fase 1.
-   **Kriteria Selesai:** Semua data dari output JSON v2.0 ditampilkan dengan benar di antarmuka baru. Fitur umpan balik berfungsi _end-to-end_.

### **Fase 3: Fitur Lanjutan & Iterasi (Masa Depan)**

-   **Tujuan:** Memperluas kemampuan sistem berdasarkan fondasi yang sudah ada.
-   **Potensi Fitur:**
    -   **Analisis Lintas Komoditas:** Mengidentifikasi korelasi harga antar komoditas (misal: pakan ternak vs. telur ayam).
    -   **Integrasi Data Eksternal:** Menambahkan konteks otomatis dari API cuaca atau berita.
    -   **Dashboard Umpan Balik:** Membuat halaman admin sederhana untuk meninjau umpan balik yang diberikan pengguna, untuk membantu menyempurnakan prompt di masa depan.
-   **Kriteria Selesai:** Satu fitur dari daftar di atas diimplementasikan sebagai _proof of concept_.

---

## **7. Metrik Kesuksesan**

Bagaimana kita tahu bahwa pengembangan ini berhasil?

### **Metrik Kualitatif (Diukur melalui wawancara/survei pengguna setelah rilis):**

1.  **Peningkatan Relevansi:** "Apakah analisis yang baru terasa lebih relevan dan 'to the point' untuk pekerjaan Anda di TPID?"
2.  **Peningkatan Kepercayaan:** "Seberapa percayakah Anda dengan wawasan yang diberikan oleh sistem, terutama dengan adanya kotak transparansi?"
3.  **Peningkatan Efisiensi:** "Apakah sistem yang baru membantu Anda mempersiapkan bahan rapat TPID dengan lebih cepat?"

### **Metrik Kuantitatif (Diukur dari data penggunaan sistem):**

1.  **Tingkat Adopsi Fitur:** Persentase sesi pengguna yang berinteraksi dengan tab "Pertimbangan untuk Pemangku Kepentingan".
2.  **Keterlibatan Pengguna (Engagement):** Jumlah klik pada tombol umpan balik (baik positif maupun negatif) per minggu. Ini menunjukkan bahwa pengguna peduli dengan kualitas analisis.
3.  **Waktu di Halaman (Time on Page):** Peningkatan waktu yang dihabiskan pada halaman analisis, yang dapat mengindikasikan bahwa pengguna benar-benar membaca dan mencerna wawasan yang lebih dalam.

---

## **8. Contoh Skenario Pengguna (User Story)**

Untuk memberikan gambaran yang jelas, berikut adalah skenario "sebelum" dan "sesudah".

**Komoditas: Cabai Merah Keriting**
**Data:** Harga naik 18% dalam seminggu terakhir. Bertepatan dengan musim hujan intensitas tinggi.

### **Sistem Versi 1.0 ("Sebelum"):**

-   **Tampilan:** Grafik menunjukkan lonjakan tajam.
-   **Teks AI:** "Harga Cabai Merah Keriting naik 18% dalam 7 hari terakhir. Volatilitas tinggi. Rata-rata 90 hari adalah Rp 45.000, harga saat ini Rp 59.000."
-   **Pengalaman Analis:** "Oke, harga naik. Tapi ini biasa untuk cabai. Seberapa serius saya harus melaporkan ini? Apa yang harus saya tanyakan ke Dinas Pertanian?"

### **Sistem Versi 2.0 ("Sesudah"):**

-   **Tampilan:** Kartu analisis berwarna merah ("BERGEJOLAK").
-   **Ringkasan Analis:**
    -   **Observasi Kunci:** "Terjadi lonjakan harga signifikan yang melampaui batas fluktuasi normal."
    -   **Hipotesis Penyebab:** "Pola kenaikan yang tajam dan cepat, bertepatan dengan konteks musim hujan, sangat mengindikasikan adanya **kejutan dari sisi pasokan (supply-side shock)**, kemungkinan akibat gangguan produksi atau distribusi."
-   **Pertimbangan untuk Pemangku Kepentingan (Tabs):**
    -   **Dinas Perdagangan:** "Data mengindikasikan potensi kelangkaan pasokan. **Pertanyaan Kunci:** Bagaimana kondisi stok cabai di tingkat distributor utama yang memasok ke Pasar Sebukit Rama?"
    -   **Dinas Pertanian:** "Curah hujan tinggi menjadi faktor risiko utama. **Poin untuk Dimonitor:** Apakah ada laporan kerusakan tanaman atau gagal panen dari sentra produksi cabai lokal?"
    -   **Koordinator TPID:** "Gejolak harga komoditas volatil ini berpotensi menjadi penyumbang inflasi. **Poin Diskusi:** Perlu dikonfirmasi apakah isu ini bersifat lokal (hanya di Mempawah) atau regional (seluruh Kalbar) untuk menentukan skala respons."
-   **Kotak Transparansi:** "Catatan: Analisis ini didasarkan pada data Pasar Sebukit Rama. Untuk gambaran utuh, disarankan memvalidasi informasi ini dengan data dari pasar lain dan laporan langsung dari lapangan."
-   **Pengalaman Analis:** "Sempurna. Saya punya semua bahan yang saya butuhkan. Saya tahu apa yang terjadi, mengapa itu mungkin terjadi, dan pertanyaan apa yang harus saya ajukan kepada siapa dalam rapat besok."
