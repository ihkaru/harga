<?php

namespace App\Services;

use App\Models\Harga;
use App\Services\GoogleSheetsService;
use App\Supports\Constants;
use Exception;
use PDO;

class HargaService
{
    protected GoogleSheetsService $gsheet;
    public function __construct()
    {
        $this->gsheet = new GoogleSheetsService();
    }
    public function getDataHarga($rowTotal = null)
    {
        $rowTotal ??= $this->getJumlahDataHarga() * 1 + 1;
        // dump($rowTotal);
        $data = $this->gsheet->getSheetData('19T2PxHgnWvwLmVa-xfnQ9mlV0Qp0NtpAw57VMvKkvCk', "'Analysis_Basis Data Long'!A2:L$rowTotal");
        // dump(count($data));
        return $data;
    }
    public function getDataHargaKecamatan($rowTotal = null)
    {
        $rowTotal ??= '';
    }
    public function getMetadataHarga()
    {

        $data = $this->gsheet->getSheetData('19T2PxHgnWvwLmVa-xfnQ9mlV0Qp0NtpAw57VMvKkvCk', "'Analysis_Basis Data Long Rekap'!A2:B");
        return $data;
    }
    public function getJumlahDataHarga()
    {
        return $this->getMetadataHarga()[1][0];
    }
    public function toNamedColumn($data, $col, $shift = false)
    {
        if ($shift) {
            array_shift($data);
        }
        $data_input = $data;
        // dd(count($data_input), $data_input[0]);
        $res = [];
        // dd($data_input);
        foreach ($data_input as $d) {
            $r = [];
            for ($i = 0; $i < count($col); $i++) {
                try {
                    if ($col[$i] == "nama_komoditas") {
                        continue;
                        // dump($col[$i]);
                    }
                    $r[$col[$i]] = $d[$i];
                } catch (Exception $e) {
                    $r[$col[$i]] = null;
                }
            }
            $res[] = $r;
        }
        return $res;
    }
    public function syncDataHarga()
    {
        $service = new HargaService();
        $data = $service->getDataHarga();
        $col = Constants::KOLOM_HARGA;
        $res = $service->toNamedColumn($data, $col, shift: true);
        $chunks = array_chunk($res, 1000); // Bagi menjadi beberapa bagian, misalnya 1000 baris per batch
        $id = $col[0];
        array_shift($col);
        // dump($id, $col);
        Harga::whereNotNull('id')->delete();
        foreach ($chunks as $chunk) {
            Harga::upsert($chunk, [$id], $col);
        }
        Harga::whereNull("harga")->delete();
    }

    public function getDataHargaGabungan($rowTotal = null)
    {
        $rowTotal ??= $this->getJumlahDataHargaGabungan() * 1 + 1;
        // dump($rowTotal);
        $data = $this->gsheet->getSheetData('1h_q8lzW-pVjTIVnEAtMnC3Wn-cMKRuktR42HgTBmpHM', "'Basis Data Gabungan'!A2:L$rowTotal");
        // dump(count($data));
        // dump($data[0]);
        return $data;
    }

    public function getMetadataHargaGabungan()
    {

        $data = $this->gsheet->getSheetData('1h_q8lzW-pVjTIVnEAtMnC3Wn-cMKRuktR42HgTBmpHM', "'Basis Data Gabungan'!N2:O");
        return $data;
    }
    public function getJumlahDataHargaGabungan()
    {
        return $this->getMetadataHargaGabungan()[0][1];
    }

    public function syncDataHargaGabungan()
    {
        $service = new HargaService();
        $data = $service->getDataHargaGabungan();
        $col = Constants::KOLOM_HARGA_GABUNGAN;
        $res = $service->toNamedColumn($data, $col, shift: false);
        // dump($res[0]);
        $chunks = array_chunk($res, 1000); // Bagi menjadi beberapa bagian, misalnya 1000 baris per batch
        Harga::whereNotNull('id')->delete();
        foreach ($chunks as $chunk) {
            Harga::insert($chunk);
        }
        Harga::whereNull("harga")->delete();
    }
}
