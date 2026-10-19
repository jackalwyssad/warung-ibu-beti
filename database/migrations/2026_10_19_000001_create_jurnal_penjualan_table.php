<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_penjualan', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique(); // kunci idempoten: satu pesan = satu baris
            $table->string('sku', 7);
            $table->unsignedInteger('jumlah');
            $table->unsignedBigInteger('harga_satuan');
            $table->unsignedBigInteger('total');
            $table->timestamp('terjadi_pada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_penjualan');
    }
};
