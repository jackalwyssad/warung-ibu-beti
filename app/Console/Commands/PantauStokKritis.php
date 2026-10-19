<?php

namespace App\Console\Commands;

use App\Messaging\RabbitMQ;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;

class PantauStokKritis extends Command
{
    protected $signature = 'gudang:pantau-habis {--ambang=3 : Batas minimum persediaan sembako}';
    protected $description = 'Memantau stok sembako realtime dan memberikan alarm saat persediaan kritis';

    public function handle(): int
    {
        $koneksi = RabbitMQ::koneksi();
        $channel = $koneksi->channel();
        RabbitMQ::siapkanTopologi($channel);

        $this->info('Menunggu mutasi stok di antrean gudang.stok-kritis. Tekan Ctrl+C untuk berhenti.');

        $channel->basic_consume(
            'gudang.stok-kritis',
            '',
            false,
            false,
            false,
            false,
            function (AMQPMessage $pesan) {
                $event = json_decode($pesan->getBody(), true);
                if (! isset($event['kode_barang'], $event['stok_sesudah'])) {
                    $this->error('Data event tidak valid, dibuang: ' . $pesan->getBody());
                    $pesan->nack(false);
                    return;
                }

                $logTeks = "[{$pesan->getRoutingKey()}] Komoditas {$event['kode_barang']} sisa persediaan: {$event['stok_sesudah']}";

                if ($event['stok_sesudah'] <= (int) $this->option('ambang')) {
                    $this->warn("⚠️ PERINGATAN GUDANG: STOK KRITIS! - {$logTeks}");
                } else {
                    $this->line("ℹ️ Stok Aman - {$logTeks}");
                }

                $pesan->ack();
            }
        );

        $channel->consume();
        return self::SUCCESS;
    }
}
