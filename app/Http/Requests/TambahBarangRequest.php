<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TambahBarangRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'kode_barang' => ['required', 'string', 'regex:/^[A-Z]{3}-[0-9]{3}$/', 'unique:barang,kode_barang'],
            'nama' => ['required', 'string', 'max:120'],
            'kategori' => ['sometimes', 'string', 'in:sembako,minuman,bumbu,makanan'],
            'harga_beli' => ['required', 'integer', 'min:0'],
            'harga_jual' => ['required', 'integer', 'min:0'],
            'stok' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
