<?php

namespace App\Http\Controllers;

use App\Models\Komoditas;
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
        return response()->json(Komoditas::with("hargas")->get());
    }
}
