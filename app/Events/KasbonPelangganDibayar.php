<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

class KasbonPelangganDibayar
{
    use Dispatchable;

    public readonly string $id;
    public readonly string $terjadiPada;

    public function __construct(
        public readonly int $pelangganId,
        public readonly string $namaPelanggan,
        public readonly int $nominalBayar,
        public readonly int $sisaKasbon,
        public readonly string $keterangan,
        ?string $id = null
    ) {
        $this->id = $id ?? (string) Str::uuid();
        $this->terjadiPada = now()->toIso8601String();
    }

    public function payload(): array
    {
        return [
            'id' => $this->id,
            'pelanggan_id' => $this->pelangganId,
            'nama_pelanggan' => $this->namaPelanggan,
            'nominal' => $this->nominalBayar,
            'omzet' => $this->nominalBayar, // Uang tunai masuk ke laci kasir warung
            'modal' => 0,
            'laba_kotor' => 0,
            'jenis_transaksi' => 'bayar_kasbon',
            'sisa_kasbon' => $this->sisaKasbon,
            'keterangan' => $this->keterangan,
            'terjadi_pada' => $this->terjadiPada,
        ];
    }
}
