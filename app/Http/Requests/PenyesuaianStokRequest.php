<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PenyesuaianStokRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'perubahan' => ['required', 'integer', 'not_in:0'],
            'alasan' => ['required', 'in:penjualan,restok,koreksi'],
        ];
    }
}
