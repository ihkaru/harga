<?php

namespace App\Services;

use App\Models\Harga;
use App\Models\Komoditas;
use App\Services\GoogleSheetsService;
use App\Supports\Constants;
use Exception;
use PDO;

class KomoditasService
{
    protected GoogleSheetsService $gsheet;
    public function __construct()
    {
        $this->gsheet = new GoogleSheetsService();
    }
    public function getDataKomoditas($rowTotal = null)
    {
        $rowTotal ??= $this->getJumlahDataKomoditas() * 1 + 1;
        // dump($rowTotal);
        $data = $this->gsheet->getSheetData('19T2PxHgnWvwLmVa-xfnQ9mlV0Qp0NtpAw57VMvKkvCk', "'Analysis_Komoditas'!A1:B$rowTotal");
        // dump(count($data));
        return $data;
    }
    public function getMetadata()
    {

        $data = $this->gsheet->getSheetData('19T2PxHgnWvwLmVa-xfnQ9mlV0Qp0NtpAw57VMvKkvCk', "'Analysis_Basis Data Long Rekap'!A:B");
        return $data;
    }
    public function getJumlahDataKomoditas()
    {
        return $this->getMetadata()[1][1];
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
    public function syncDataKomoditas()
    {
        $data = $this->getDataKomoditas();
        $col = Constants::KOLOM_KOMODITAS;
        $res = $this->toNamedColumn($data, $col, shift: true);
        $chunks = array_chunk($res, 1000); // Bagi menjadi beberapa bagian, misalnya 1000 baris per batch
        $id = $col[0];
        array_shift($col);
        // dump($id, $col);
        Komoditas::whereNotNull('id')->delete();
        foreach ($chunks as $chunk) {
            Komoditas::upsert($chunk, [$id], $col);
        }
    }
}
