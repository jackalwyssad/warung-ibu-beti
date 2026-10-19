<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

class TransaksiKasirSelesai
{
    use Dispatchable;

    public readonly string $id;
    public readonly string $terjadiPada;

    public function __construct(
        public readonly string $kodeBarang,
        public readonly int $jumlah,
        public readonly string $jenisTransaksi,
        public readonly int $hargaBeli,
        public readonly int $hargaJual,
        public readonly int $stokSesudah,
        ?string $id = null
    ) {
        $this->id = $id ?? (string) Str::uuid();
        $this->terjadiPada = now()->toIso8601String();
    }

    public function omzet(): int
    {
        return $this->jumlah * $this->hargaJual;
    }

    public function modal(): int
    {
        return $this->jumlah * $this->hargaBeli;
    }

    public function labaKotor(): int
    {
        return $this->omzet() - $this->modal();
    }

    public function payload(): array
    {
        return [
            'id' => $this->id,
            'kode_barang' => $this->kodeBarang,
            'jumlah' => $this->jumlah,
            'jenis_transaksi' => $this->jenisTransaksi,
            'harga_beli' => $this->hargaBeli,
            'harga_jual' => $this->hargaJual,
            'omzet' => $this->omzet(),
            'modal' => $this->modal(),
            'laba_kotor' => $this->labaKotor(),
            'stok_sesudah' => $this->stokSesudah,
            'terjadi_pada' => $this->terjadiPada,
        ];
    }
}
