<?php

use App\Http\Controllers\Api\V1\BarangController;
use App\Http\Controllers\Api\V1\PelangganController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Katalog Sembako & Transaksi Kasir
    Route::get('/barang', [BarangController::class, 'index']);
    Route::post('/barang', [BarangController::class, 'store']);
    Route::get('/barang/{barang:kode_barang}', [BarangController::class, 'show']);
    Route::post('/barang/{barang:kode_barang}/catat-transaksi', [BarangController::class, 'catatTransaksi']);

    // Manajemen Pelanggan, Kasbon (Utang Belanja), dan Pelunasan Kasbon
    Route::get('/pelanggan', [PelangganController::class, 'index']);
    Route::post('/pelanggan', [PelangganController::class, 'store']);
    Route::get('/pelanggan/{pelanggan}', [PelangganController::class, 'show']);
    Route::post('/pelanggan/{pelanggan}/kasbon', [PelangganController::class, 'catatKasbon']);
    Route::post('/pelanggan/{pelanggan}/bayar-kasbon', [PelangganController::class, 'bayarKasbon']);
});
