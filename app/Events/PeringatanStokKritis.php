<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

class PeringatanStokKritis
{
    use Dispatchable;

    public readonly string $id;
    public readonly string $terjadiPada;

    public function __construct(
        public readonly string $kodeBarang,
        public readonly string $namaBarang,
        public readonly int $sisaStok,
        ?string $id = null
    ) {
        $this->id = $id ?? (string) Str::uuid();
        $this->terjadiPada = now()->toIso8601String();
    }

    public function payload(): array
    {
        return [
            'id' => $this->id,
            'kode_barang' => $this->kodeBarang,
            'nama' => $this->namaBarang,
            'sisa_stok' => $this->sisaStok,
            'pesan' => "PERINGATAN GUDANG: Stok komoditas {$this->kodeBarang} ({$this->namaBarang}) tersisa {$this->sisaStok} pcs!",
            'terjadi_pada' => $this->terjadiPada,
        ];
    }
}
