<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PelangganResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'nomor_wa' => $this->nomor_wa,
            'limit_kasbon' => $this->limit_kasbon,
            'total_kasbon' => $this->total_kasbon,
            'sisa_limit' => max(0, $this->limit_kasbon - $this->total_kasbon),
            'dibuat_pada' => $this->created_at?->toIso8601String(),
        ];
    }
}
