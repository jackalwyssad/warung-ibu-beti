<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CatatTransaksiBarangRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'perubahan' => ['required', 'integer', 'not_in:0'],
            'jenis_transaksi' => ['required', 'in:penjualan_kasir,pesanan_wa,kulakan_gudang,retur_rusak'],
        ];
    }
}
