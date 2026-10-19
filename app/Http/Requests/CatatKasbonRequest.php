<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatatKasbonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nominal' => ['required', 'integer', 'min:1000'],
            'keterangan' => ['required', 'string', 'max:255'],
        ];
    }
}
