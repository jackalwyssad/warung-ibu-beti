<?php

namespace App\Console\Commands;

use App\Messaging\RabbitMQ;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;

/** Mewakili layanan notifikasi (Bot WhatsApp / Kasir) yang berlangganan event perubahan stok. */
class KonsumsiNotifikasiStok extends Command
{
    protected $signature = 'notifikasi:stok {--batas=5 : Stok di bawah atau sama dengan angka ini dianggap menipis}';
    protected $description = 'Menampilkan peringatan ketika stok menipis';

    public function handle(): int
    {
        $koneksi = RabbitMQ::koneksi();
        $channel = $koneksi->channel();
        RabbitMQ::siapkanTopologi($channel);

        $this->info('Menunggu pesan di queue notifikasi.stok. Tekan Ctrl+C untuk berhenti.');

        $channel->basic_consume('notifikasi.stok', '', false, false, false, false,
            function (AMQPMessage $pesan) {
                $event = json_decode($pesan->getBody(), true);
                if (! isset($event['sku'], $event['stok_sesudah'])) {
                    // Queue ini tidak punya dead letter: pesan yang tidak bisa dibaca dibuang.
                    $this->error('Pesan tidak valid dibuang: '.$pesan->getBody());
                    $pesan->nack(false);
                    return;
                }

                $pesanTeks = "[{$pesan->getRoutingKey()}] {$event['sku']} stok sekarang {$event['stok_sesudah']}";

                if ($event['stok_sesudah'] <= (int) $this->option('batas')) {
                    $this->warn("STOK MENIPIS - {$pesanTeks}");
                } else {
                    $this->line($pesanTeks);
                }

                $pesan->ack();
            });

        $channel->consume();

        return self::SUCCESS;
    }
}
