<?php

namespace App\Http\Controllers;

use App\Models\Harga;
use App\Models\Komoditas;
use App\Services\HargaService;
use App\Services\KomoditasService;
use App\Services\SP2KPService;
use App\Supports\Helpers;
use App\Supports\TanggalMerah;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class KomoditasController extends Controller {
    public function show($idKomoditas) {
        // dd($idKomoditas);
        $komoditas = Komoditas::where('id_komoditas', $idKomoditas)->with(['hargas'])->first();
        if ($komoditas) {
            return response()->json($komoditas);
        }
        return response(status: 404);
    }
    public function index() {
        # Cek data terakhir yang masuk ke database
        // SP2KPService::updateLatestData();
        $data = Komoditas::with(['hargas' => function ($query) {
            $query->orderByRaw("STR_TO_DATE(tanggal, '%e/%c/%Y') ASC"); // Ganti 'asc' dengan 'desc' jika ingin urutan menurun
        }])->get();
        // dd(count($data));
        if (count($data) < 1) {
            $this->updateKomoditas();
        }
        return response()->json($data);
    }
    public function updateKomoditas() {
        $komoditasService = new KomoditasService();
        $hargaService = new HargaService();

        try {
            $last_try = now()->toDateString();
            if (config('app.pull_from_gsheet')) {
                $komoditasService->syncDataKomoditas();
                $hargaService->syncDataHargaGabungan();
            }
            if (config('app.pull_from_sp2kp')) {
                SP2KPService::updateLatestData();
            }
            Harga::where('harga', 0)->delete();
            try {
                $last_date = Carbon::createFromFormat('d/m/Y', Harga::orderByRaw("STR_TO_DATE(tanggal, '%e/%c/%Y') DESC")->first()->tanggal)->toDateString();
            } catch (Exception $e) {
                $last_date = Carbon::createFromFormat('Y-m-d', Harga::orderByRaw("STR_TO_DATE(tanggal, '%e/%c/%Y') DESC")->first()->tanggal)->toDateString();
            }
            return response()->json(["message" => "success", 'last_date' => $last_date, 'last_try' => $last_try])->header('Access-Control-Allow-Origin', '*');;
        } catch (Exception $e) {
            return response()->json(["message" => $e->getMessage()], 500)->header('Access-Control-Allow-Origin', '*');;
        }
    }
}
