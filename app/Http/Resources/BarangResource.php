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
            'kategori' => $this->kategori,
            'harga_beli' => $this->harga_beli,
            'harga_jual' => $this->harga_jual,
            'margin_persen' => $this->margin_persen,
            'stok' => $this->stok,
            'tersedia' => $this->stok > 0,
            'diperbarui_pada' => $this->updated_at?->toIso8601String(),
        ];
    }
}
