<?php

use App\Http\Controllers\Api\V1\BarangController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/barang', [BarangController::class, 'index']);
    Route::post('/barang', [BarangController::class, 'store']);
    Route::get('/barang/{barang:kode_barang}', [BarangController::class, 'show']);
    Route::post('/barang/{barang:kode_barang}/catat-transaksi', [BarangController::class, 'catatTransaksi']);
});
