<?php

namespace App\Exceptions;

use RuntimeException;

class PembayaranKasbonMelebihiUtang extends RuntimeException
{
    public function __construct(
        public readonly int $pelangganId,
        public readonly string $namaPelanggan,
        public readonly int $kasbonSaatIni,
        public readonly int $nominalBayar,
    ) {
        parent::__construct("Pembayaran ditolak: Pelanggan {$namaPelanggan} hanya memiliki kasbon aktif sebesar Rp {$kasbonSaatIni}. Pembayaran Rp {$nominalBayar} melebihi jumlah utang (total kasbon tidak boleh minus).");
    }
}
