<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representasi produk yang dijanjikan kontrak (schema Produk di openapi.yaml).
 * Kolom internal seperti id dan created_at sengaja tidak ikut ditampilkan.
 */
class ProdukResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sku' => $this->sku,
            'nama' => $this->nama,
            'harga' => $this->harga,
            'stok' => $this->stok,
            'diperbarui_pada' => $this->updated_at?->toIso8601String(),
        ];
    }
}
