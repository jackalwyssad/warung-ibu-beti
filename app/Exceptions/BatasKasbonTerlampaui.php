<?php

namespace App\Exceptions;

use RuntimeException;

class BatasKasbonTerlampaui extends RuntimeException
{
    public function __construct(
        public readonly int $pelangganId,
        public readonly string $namaPelanggan,
        public readonly int $kasbonSaatIni,
        public readonly int $nominalDiminta,
        public readonly int $limitKasbon,
    ) {
        $totalSetelah = $kasbonSaatIni + $nominalDiminta;
        parent::__construct("Kasbon ditolak: Pelanggan {$namaPelanggan} memiliki kasbon aktif Rp {$kasbonSaatIni}. Tambahan Rp {$nominalDiminta} membuat total kasbon Rp {$totalSetelah}, melebihi batas limit Rp {$limitKasbon}.");
    }
}
