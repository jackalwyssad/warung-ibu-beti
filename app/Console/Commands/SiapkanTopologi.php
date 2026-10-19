<?php

namespace App\Console\Commands;

use App\Messaging\RabbitMQ;
use Illuminate\Console\Command;

class SiapkanTopologi extends Command
{
    protected $signature = 'warung:topologi';
    protected $description = 'Mendeklarasikan Exchange warung.sembako.events, DLX warung.sembako.dlx, dan antrean';

    public function handle(): int
    {
        $koneksi = RabbitMQ::koneksi();
        $channel = $koneksi->channel();

        RabbitMQ::siapkanTopologi($channel);

        $this->info('Topologi RabbitMQ Warung Ibu Beti berhasil disiapkan:');
        $this->line('- Topic Exchange: warung.sembako.events');
        $this->line('- Fanout DLX: warung.sembako.dlx');
        $this->line('- Queue Buku Kas: kasir.buku-kas (DLX -> warung.audit.dlq)');
        $this->line('- Queue Pantau Stok: gudang.stok-kritis');

        $channel->close();
        $koneksi->close();

        return self::SUCCESS;
    }
}
