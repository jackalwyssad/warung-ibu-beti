<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TambahPelangganRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'nomor_wa' => ['sometimes', 'nullable', 'string', 'max:20'],
            'limit_kasbon' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
