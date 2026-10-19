<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SinkronkanKatalogWhatsApp implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $kodeBarang, public int $stok) {}

    public function handle(): void
    {
        // Simulasi update katalog stok realtime di server WhatsApp Bot
        Log::info("[whatsapp-bot] stok komoditas {$this->kodeBarang} disinkronkan ke catalog menjadi {$this->stok}");
    }
}
