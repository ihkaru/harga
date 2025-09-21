<?php

use App\Http\Controllers\Api\AnalysisFeedbackController;
use App\Http\Controllers\Api\AnalisisHargaController;
use App\Http\Controllers\HargaController;
use App\Http\Controllers\KomoditasController;
use App\Http\Controllers\TpidController; // <-- 1. Tambahkan import TpidController
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// --- Routes Data Dasar ---
Route::get("/harga", [HargaController::class, "index"]);
Route::get("/komoditas", [KomoditasController::class, "index"]);
Route::get("/komoditas/{idKomoditas}", [KomoditasController::class, "show"]);
Route::get("/update_komoditas", [KomoditasController::class, "updateKomoditas"]);


// --- Routes Analisis TPID (Tim Pengendali Inflasi Daerah) ---

// 2. Gunakan Route::prefix dan Route::controller untuk mengelompokkan route TPID
Route::prefix('tpid/report')->controller(TpidController::class)->group(function () {

    /**
     * Endpoint untuk mendapatkan laporan analisis 5 komoditas teratas
     * yang paling bergejolak dalam seminggu terakhir.
     * Sesuai dengan method `generateTopMoversReport`.
     *
     * Contoh Penggunaan: GET /api/tpid/report/top-movers
     */
    Route::get('/top-movers', 'generateTopMoversReport')->name('tpid.report.topMovers');

    /**
     * Endpoint untuk mendapatkan laporan analisis mendalam untuk satu komoditas spesifik.
     * Sesuai dengan method `generateSingleReport`.
     *
     * Contoh Penggunaan: GET /api/tpid/report/komoditas/10  (untuk bawang merah)
     */
    Route::get('/komoditas/{komoditasId}', 'generateSingleReport')->name('tpid.report.single');

    /**
     * Endpoint untuk menghasilkan laporan untuk SEMUA komoditas.
     * PERHATIAN: Bisa jadi lambat dan memakan banyak resource.
     * Lebih cocok untuk dijalankan sebagai background job.
     * Sesuai dengan method `generateFullReport`.
     *
     * Contoh Penggunaan: GET /api/tpid/report/full
     */
    Route::get('/full', 'generateFullReport')->name('tpid.report.full');
});

// Endpoint untuk menerima data dari n8n (POST)
Route::post('/analisis-harga', [AnalisisHargaController::class, 'store']);

// Endpoint untuk mengambil data untuk frontend (GET)
Route::get('/analisis-harga', [AnalisisHargaController::class, 'index']);

Route::post('/analysis-feedback', [AnalysisFeedbackController::class, 'store']);
