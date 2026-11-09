<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HargaController;
use App\Http\Controllers\JadwalTanamController;
use App\Http\Controllers\LahanController;
use App\Http\Controllers\PengepulController;
use App\Http\Controllers\SewaLahanController;
use App\Http\Controllers\VerifikasiController;
use App\Http\Controllers\WaWebhookController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/harga', [HargaController::class, 'index']);
Route::post('/wa/webhook', [WaWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware(RoleMiddleware::class . ':admin')->prefix('admin')->group(function () {
        Route::get('/ping', fn () => response()->json(['message' => 'Halo admin AGROENDER']));

        Route::apiResource('lahan', LahanController::class);
        Route::apiResource('pengepul', PengepulController::class);

        Route::get('/sewa', [SewaLahanController::class, 'index']);
        Route::post('/sewa', [SewaLahanController::class, 'store']);

        Route::get('/jadwal', [JadwalTanamController::class, 'index']);
        Route::post('/jadwal', [JadwalTanamController::class, 'store']);

        Route::post('/harga', [HargaController::class, 'store']);

        Route::get('/verifikasi', [VerifikasiController::class, 'index']);
        Route::post('/verifikasi/{tipe}/{id}', [VerifikasiController::class, 'set']);
    });
});
