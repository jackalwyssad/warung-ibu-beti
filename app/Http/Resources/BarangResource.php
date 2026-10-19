<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BarangResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kode_barang' => $this->kode_barang,
            'nama' => $this->nama,
            'kategori' => $this->kategori ?? 'sembako',
            'harga' => $this->harga,
            'stok' => $this->stok,
            'tersedia' => $this->stok > 0,
            'diperbarui_pada' => $this->updated_at?->toIso8601String(),
        ];
    }
}
