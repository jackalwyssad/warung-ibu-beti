<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RekapHarianWarung extends Model
{
    protected $table = 'rekap_harian_warung';

    protected $fillable = [
        'tanggal',
        'total_transaksi',
        'total_omzet',
        'total_modal',
        'total_laba_kotor',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'total_transaksi' => 'integer',
        'total_omzet' => 'integer',
        'total_modal' => 'integer',
        'total_laba_kotor' => 'integer',
    ];
}
