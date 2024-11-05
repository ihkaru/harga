<?php

use App\Http\Controllers\HargaController;
use App\Http\Controllers\KomoditasController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
