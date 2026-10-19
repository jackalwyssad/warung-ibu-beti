<?php

namespace App\Exceptions;

use RuntimeException;

class StokTidakCukup extends RuntimeException
{
    public function __construct(
        public readonly string $sku,
        public readonly int $stokTersedia,
        public readonly int $diminta,
    ) {
        parent::__construct("Stok {$sku} tinggal {$stokTersedia}, tidak bisa dikurangi {$diminta}.");
    }
}
