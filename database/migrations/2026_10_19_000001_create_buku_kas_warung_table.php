<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buku_kas_warung', function (Blueprint $table) {
            $table->id();
            $table->string('id_event_transaksi')->unique(); // Kunci idempoten event
            $table->string('kode_barang', 10);
            $table->unsignedInteger('qty_keluar');
            $table->unsignedBigInteger('harga_eceran');
            $table->unsignedBigInteger('total_omzet');
            $table->string('kanal_transaksi', 30)->default('kasir');
            $table->timestamp('terjadi_pada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buku_kas_warung');
    }
};
