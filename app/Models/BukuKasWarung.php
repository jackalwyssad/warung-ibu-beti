<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BukuKasWarung extends Model
{
    protected $table = 'buku_kas_warung';

    protected $fillable = [
        'id_event_transaksi',
        'kode_referensi',
        'jenis_aliran',
        'omzet',
        'modal',
        'laba_kotor',
        'terjadi_pada',
    ];

    protected $casts = [
        'omzet' => 'integer',
        'modal' => 'integer',
        'laba_kotor' => 'integer',
        'terjadi_pada' => 'datetime',
    ];
}
