<?php

namespace App\Console\Commands;

use App\Services\OutboxPublisherService;
use Illuminate\Console\Command;

class KirimOutboxCommand extends Command
{
    protected $signature = 'warung:kirim-outbox {--limit=50 : Jumlah event maksimal yang diterbitkan}';
    protected $description = 'Menerbitkan antrean outbox event ke RabbitMQ broker (Outbox Pattern Worker)';

    public function handle(OutboxPublisherService $service): int
    {
        $limit = (int) $this->option('limit');
        $terkirim = $service->publishPending($limit);

        if ($terkirim > 0) {
            $this->info("Berhasil menerbitkan {$terkirim} event outbox ke broker RabbitMQ.");
        } else {
            $this->line("Tidak ada event pending di tabel outbox.");
        }

        return self::SUCCESS;
    }
}
