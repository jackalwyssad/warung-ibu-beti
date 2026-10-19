<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelanggan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('nomor_wa', 20)->nullable();
            $table->unsignedBigInteger('limit_kasbon')->default(150000); // Batas maksimal utang
            $table->unsignedBigInteger('total_kasbon')->default(0); // Total utang aktif saat ini
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelanggan');
    }
};
