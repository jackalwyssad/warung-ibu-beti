<?php

namespace App\Console\Commands;

use App\Messaging\RabbitMQ;
use App\Pembukuan\PesanTransaksiTidakValid;
use App\Services\BukuKasService;
use Illuminate\Console\Command;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class KonsumsiBukuKasCommand extends Command
{
    protected $signature = 'warung:catat-kas';
    protected $description = 'Mengonsumsi event transaksi sembako & kasbon, menghitung laba kotor, dan merekap kas harian';

    public function handle(BukuKasService $service): int
    {
        $koneksi = RabbitMQ::koneksi();
        $channel = $koneksi->channel();
        RabbitMQ::siapkanTopologi($channel);

        $channel->basic_qos(0, 1, false);
        $this->info('Menunggu event di antrean kasir.buku-kas. Tekan Ctrl+C untuk berhenti.');

        $channel->basic_consume(
            'kasir.buku-kas',
            '',
            false,
            false,
            false,
            false,
            function (AMQPMessage $pesan) use ($service) {
                try {
                    $event = json_decode($pesan->getBody(), true, 512, JSON_THROW_ON_ERROR);
                    $messageId = $pesan->has('message_id') ? $pesan->get('message_id') : ($event['id'] ?? '');

                    $baru = $service->rekamTransaksi($event, $messageId);
                    $this->line(($baru ? 'Tercatat Buku Kas & Rekap : ' : 'Dilewati (Duplikat) : ') . $pesan->getBody());
                    $pesan->ack();
                } catch (PesanTransaksiTidakValid | JsonException $e) {
                    $this->error('Pesan korup dialihkan ke DLQ: ' . $e->getMessage());
                    $pesan->nack(false);
                } catch (Throwable $e) {
                    $this->error('Gangguan sistem: ' . $e->getMessage());
                    $pesan->nack(! $pesan->isRedelivered());
                }
            }
        );

        $channel->consume();
        return self::SUCCESS;
    }
}
