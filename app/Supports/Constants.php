<?php

namespace App\Supports;

class Constants
{
    const KOLOM_HARGA = ["id_komoditas_harian", "id_komoditas_pekanan", "id_komoditas_bulanan", "id_pekan", "tanggal", "id_komoditas", "tahun", "bulan", "tanggal_angka", "harga", "responden", "kecamatan"];
    const KOLOM_KOMODITAS = ['id_komoditas', 'nama'];
    const KECAMATAN_PASAR = [
        "Sungai Pinyuh" => "Pasar Usaha Bersama",
        "Anjongan" => "Pasar Gunung Loncet",
        "Jongkat" => "Pasar Pangkalan Mas",
        "Mempawah Hilir" => "Pasar Sebukit Rama",
    ];
    const KOLOM_HARGA_GABUNGAN = ["tanggal", "id_komoditas", "nama_komoditas", "kecamatan", "harga", "id_komoditas_harian", "id_komoditas_pekanan", "id_komoditas_bulanan", "id_pekan", "tahun", "bulan", "tanggal_angka"];
}
