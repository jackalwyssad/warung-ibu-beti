<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_harian_warung', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->unsignedInteger('total_transaksi')->default(0);
            $table->unsignedBigInteger('total_omzet')->default(0);
            $table->unsignedBigInteger('total_modal')->default(0);
            $table->bigInteger('total_laba_kotor')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_harian_warung');
    }
};
