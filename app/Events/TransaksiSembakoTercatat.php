<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

/**
 * Event domain: Transaksi persediaan sembako berhasil diproses.
 * Menyatakan fakta bisnis yang sudah selesai dan terkonfirmasi di database.
 */
class TransaksiSembakoTercatat
{
    use Dispatchable;

    public readonly string $id;
    public readonly string $terjadiPada;

    public function __construct(
        public readonly string $kodeBarang,
        public readonly int $perubahan,
        public readonly string $jenisTransaksi,
        public readonly int $stokSesudah,
        public readonly int $harga,
    ) {
        $this->id = (string) Str::uuid();
        $this->terjadiPada = now()->toIso8601String();
    }

    /**
     * Kontrak pesan event yang dikirimkan ke RabbitMQ
     */
    public function payload(): array
    {
        return [
            'kode_barang' => $this->kodeBarang,
            'perubahan' => $this->perubahan,
            'jenis_transaksi' => $this->jenisTransaksi,
            'stok_sesudah' => $this->stokSesudah,
            'harga' => $this->harga,
            'terjadi_pada' => $this->terjadiPada,
        ];
    }
}
