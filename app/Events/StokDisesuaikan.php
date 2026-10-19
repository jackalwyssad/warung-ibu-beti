<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * Event domain: stok sebuah produk baru saja berubah di Warung Ibu Beti.
 * Ditulis dalam bentuk lampau karena menyatakan fakta yang sudah terjadi.
 */
class StokDisesuaikan
{
    use Dispatchable;

    public readonly string $id;
    public readonly string $terjadiPada;

    public function __construct(
        public readonly string $sku,
        public readonly int $perubahan,
        public readonly string $alasan,
        public readonly int $stokSesudah,
        public readonly int $harga,
    ) {
        $this->id = (string) Str::uuid();
        $this->terjadiPada = now()->toIso8601String();
    }

    /** Isi (body) pesan yang dikirim ke broker — inilah kontrak event. */
    public function payload(): array
    {
        return [
            'sku' => $this->sku,
            'perubahan' => $this->perubahan,
            'alasan' => $this->alasan,
            'stok_sesudah' => $this->stokSesudah,
            'harga' => $this->harga,
            'terjadi_pada' => $this->terjadiPada,
        ];
    }
}
