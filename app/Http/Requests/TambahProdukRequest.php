<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TambahProdukRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'regex:/^[A-Z]{3}-[0-9]{3}$/', 'unique:produk,sku'],
            'nama' => ['required', 'string', 'max:120'],
            'harga' => ['required', 'integer', 'min:0'],
            'stok' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
