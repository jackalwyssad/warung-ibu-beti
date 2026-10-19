<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pelanggan extends Model
{
    protected $table = 'pelanggan';

    protected $fillable = [
        'nama',
        'nomor_wa',
        'limit_kasbon',
        'total_kasbon',
    ];

    protected $casts = [
        'limit_kasbon' => 'integer',
        'total_kasbon' => 'integer',
    ];

    public function kasbon(): HasMany
    {
        return $this->hasMany(Kasbon::class, 'pelanggan_id');
    }
}
