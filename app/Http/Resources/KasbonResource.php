<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KasbonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_transaksi_kasbon' => $this->id_transaksi_kasbon,
            'pelanggan_id' => $this->pelanggan_id,
            'tipe' => $this->tipe ?? 'pinjam',
            'nominal' => $this->nominal,
            'keterangan' => $this->keterangan,
            'sisa_kasbon_pelanggan' => $this->pelanggan?->total_kasbon,
            'tercatat_pada' => $this->created_at?->toIso8601String(),
        ];
    }
}
