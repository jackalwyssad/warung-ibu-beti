<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BukuKasWarung extends Model
{
    protected $table = 'buku_kas_warung';

    protected $fillable = [
        'id_event_transaksi',
        'kode_barang',
        'qty_keluar',
        'harga_eceran',
        'total_omzet',
        'kanal_transaksi',
        'terjadi_pada',
    ];

    protected $casts = [
        'qty_keluar' => 'integer',
        'harga_eceran' => 'integer',
        'total_omzet' => 'integer',
        'terjadi_pada' => 'datetime',
    ];
}
