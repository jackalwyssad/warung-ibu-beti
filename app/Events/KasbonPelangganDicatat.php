<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

class KasbonPelangganDicatat
{
    use Dispatchable;

    public readonly string $id;
    public readonly string $terjadiPada;

    public function __construct(
        public readonly int $pelangganId,
        public readonly string $namaPelanggan,
        public readonly int $nominal,
        public readonly int $totalKasbonSaatIni,
        public readonly int $sisaLimit,
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
            'nominal' => $this->nominal,
            'total_kasbon' => $this->totalKasbonSaatIni,
            'sisa_limit' => $this->sisaLimit,
            'keterangan' => $this->keterangan,
            'terjadi_pada' => $this->terjadiPada,
        ];
    }
}
