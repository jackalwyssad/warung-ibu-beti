<?php

namespace App\Console\Commands;

use App\Messaging\RabbitMQ;
use App\Pembukuan\CatatPenjualan;
use App\Pembukuan\PesanTidakValid;
use Illuminate\Console\Command;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class KonsumsiPenjualan extends Command
{
    protected $signature = 'pembukuan:konsumsi';
    protected $description = 'Mengonsumsi event penjualan dari RabbitMQ dan mencatatnya ke jurnal';

    public function handle(CatatPenjualan $catat): int
    {
        $koneksi = RabbitMQ::koneksi();
        $channel = $koneksi->channel();
        RabbitMQ::siapkanTopologi($channel);

        // Ambil satu pesan dulu, ack, baru ambil berikutnya.
        $channel->basic_qos(0, 1, false);
        $this->info('Menunggu pesan di queue pembukuan.penjualan. Tekan Ctrl+C untuk berhenti.');

        $channel->basic_consume('pembukuan.penjualan', '', false, false, false, false,
            function (AMQPMessage $pesan) use ($catat) {
                try {
                    $event = json_decode($pesan->getBody(), true, 512, JSON_THROW_ON_ERROR);
                    $messageId = $pesan->has('message_id') ? $pesan->get('message_id') : '';
                    $baru = $catat($event, $messageId);

                    $this->line(($baru ? 'Dicatat : ' : 'Dilewati : ').$pesan->getBody());
                    $pesan->ack();
                } catch (PesanTidakValid|JsonException $e) {
                    // Tidak akan pernah berhasil: jangan dikembalikan ke queue, kirim ke dead letter.
                    $this->error('Ditolak ke DLQ: '.$e->getMessage());
                    $pesan->nack(false);
                } catch (Throwable $e) {
                    // Mungkin sementara (misalnya basis data mati): coba sekali lagi, lalu ke dead letter.
                    $this->error('Gagal: '.$e->getMessage());
                    $pesan->nack(! $pesan->isRedelivered());
                }
            });

        $channel->consume();

        return self::SUCCESS;
    }
}
