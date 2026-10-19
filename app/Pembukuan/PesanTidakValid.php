<?php

namespace App\Pembukuan;

use RuntimeException;

/** Pesan yang tidak mungkin diproses, berapa kali pun dicoba ulang. */
class PesanTidakValid extends RuntimeException {}
