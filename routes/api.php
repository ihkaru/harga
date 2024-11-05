<?php

use App\Http\Controllers\HargaController;
use App\Http\Controllers\KomoditasController;
use Illuminate\Support\Facades\Route;

Route::get("/harga", [HargaController::class, "index"]);
Route::get("/komoditas", [KomoditasController::class, "index"]);
Route::get("/komoditas/{idKomoditas}", [KomoditasController::class, "show"]);
