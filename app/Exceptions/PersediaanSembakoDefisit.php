<?php

namespace App\Exceptions;

use RuntimeException;

class PersediaanSembakoDefisit extends RuntimeException
{
    public function __construct(
        public readonly string $kodeBarang,
        public readonly int $stokTersedia,
        public readonly int $diminta,
    ) {
        parent::__construct("Persediaan barang {$kodeBarang} di rak warung hanya tersisa {$stokTersedia}, tidak mencukupi permintaan transaksi {$diminta}.");
    }
}
