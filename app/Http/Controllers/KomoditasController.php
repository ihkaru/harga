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

class KomoditasController extends Controller
{
    public function show($idKomoditas)
    {
        // dd($idKomoditas);
        $komoditas = Komoditas::where('id_komoditas', $idKomoditas)->with(['hargas'])->first();
        if ($komoditas) {
            return response()->json($komoditas);
        }
        return response(status: 404);
    }
    public function index()
    {
        # Cek data terakhir yang masuk ke database
        // SP2KPService::updateLatestData();
        return response()->json(Komoditas::with(['hargas' => function ($query) {
            $query->orderBy('id_komoditas_harian', 'asc'); // Ganti 'asc' dengan 'desc' jika ingin urutan menurun
        }])->get());
    }
    public function updateKomoditas()
    {
        $komoditasService = new KomoditasService();
        $hargaService = new HargaService();

        try {
            $last_try = now()->toDateString();
            // $komoditasService->syncDataKomoditas();
            // $hargaService->syncDataHargaGabungan();
            SP2KPService::updateLatestData();
            try {
                $last_date = Carbon::createFromFormat('d/m/Y', Harga::orderBy('tanggal', 'desc')->first()->tanggal)->toDateString();
            } catch (Exception $e) {
                $last_date = Carbon::createFromFormat('Y-m-d', Harga::orderBy('tanggal', 'desc')->first()->tanggal)->toDateString();
            }
            return response()->json(["message" => "success", 'last_date' => $last_date, 'last_try' => $last_try])->header('Access-Control-Allow-Origin', '*');;
        } catch (Exception $e) {
            return response()->json(["message" => $e->getMessage()], 500)->header('Access-Control-Allow-Origin', '*');;
        }
    }
}
