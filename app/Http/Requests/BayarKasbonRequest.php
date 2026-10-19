<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BayarKasbonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nominal' => ['required', 'integer', 'min:1000'],
            'keterangan' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
