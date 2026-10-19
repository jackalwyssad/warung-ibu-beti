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
            $table->string('id_event_transaksi', 64)->unique(); // Kunci idempoten event
            $table->string('kode_referensi', 64);
            $table->string('jenis_aliran', 30); // penjualan_tunai, pesanan_wa, kasbon
            $table->unsignedBigInteger('omzet');
            $table->unsignedBigInteger('modal');
            $table->bigInteger('laba_kotor'); // omzet - modal
            $table->timestamp('terjadi_pada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buku_kas_warung');
    }
};
