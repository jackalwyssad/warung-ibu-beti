<?php

namespace App\Console\Commands;

use App\Messaging\RabbitMQ;
use Illuminate\Console\Command;

class SiapkanTopologi extends Command
{
    protected $signature = 'warung:topologi';
    protected $description = 'Menyiapkan exchange, queue, dead-letter queue, dan binding di RabbitMQ';

    public function handle(): int
    {
        $conn = RabbitMQ::koneksi();
        $ch = $conn->channel();
        RabbitMQ::siapkanTopologi($ch);
        $ch->close();
        $conn->close();

        $this->info('Topologi RabbitMQ Warung Ibu Beti berhasil disiapkan!');
        $this->line('- Exchange: warung.events (topic)');
        $this->line('- Exchange DLX: warung.dlx (fanout)');
        $this->line('- Queue: pembukuan.penjualan (binding: stok.penjualan)');
        $this->line('- Queue: pembukuan.penjualan.dlq (binding: warung.dlx)');
        $this->line('- Queue: notifikasi.stok (binding: stok.*)');

        return self::SUCCESS;
    }
}
