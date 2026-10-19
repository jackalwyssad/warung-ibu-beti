<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SinkronkanStokWhatsApp implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $sku, public int $stok) {}

    public function handle(): void
    {
        // Di sistem sungguhan: sinkronisasi katalog & ketersediaan ke Bot WhatsApp Warung Ibu Beti
        Log::info("[whatsapp] stok {$this->sku} disinkronkan menjadi {$this->stok}");
    }
}
