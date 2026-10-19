<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    protected $table = 'barang';

    protected $fillable = [
        'kode_barang',
        'nama',
        'kategori',
        'harga_beli',
        'harga_jual',
        'stok',
    ];

    protected $casts = [
        'harga_beli' => 'integer',
        'harga_jual' => 'integer',
        'stok' => 'integer',
    ];

    public function getMarginPersenAttribute(): float
    {
        if ($this->harga_beli <= 0) {
            return 0.0;
        }
        $laba = $this->harga_jual - $this->harga_beli;
        return round(($laba / $this->harga_beli) * 100, 1);
    }
}
