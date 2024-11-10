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
        $data = $this->gsheet->getSheetData('19T2PxHgnWvwLmVa-xfnQ9mlV0Qp0NtpAw57VMvKkvCk', "'Analysis_Basis Data Long'!A1:J$rowTotal");
        // dump(count($data));
        return $data;
    }
    public function getMetadataHarga()
    {

        $data = $this->gsheet->getSheetData('19T2PxHgnWvwLmVa-xfnQ9mlV0Qp0NtpAw57VMvKkvCk', "'Analysis_Basis Data Long Rekap'!A:B");
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
}
