<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JurnalPenjualan extends Model
{
    protected $table = 'jurnal_penjualan';

    protected $fillable = ['message_id', 'sku', 'jumlah', 'harga_satuan', 'total', 'terjadi_pada'];

    protected $casts = [
        'jumlah' => 'integer',
        'harga_satuan' => 'integer',
        'total' => 'integer',
        'terjadi_pada' => 'datetime',
    ];
}
