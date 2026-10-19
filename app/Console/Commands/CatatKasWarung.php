<?php

namespace App\Console\Commands;

use App\Messaging\RabbitMQ;
use App\Pembukuan\PesanTransaksiTidakValid;
use App\Pembukuan\RekamBukuKas;
use Illuminate\Console\Command;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class CatatKasWarung extends Command
{
    protected $signature = 'warung:catat-kas';
    protected $description = 'Mengonsumsi event transaksi sembako dan mencatat omzet ke buku kas warung';

    public function handle(RekamBukuKas $rekam): int
    {
        $koneksi = RabbitMQ::koneksi();
        $channel = $koneksi->channel();
        RabbitMQ::siapkanTopologi($channel);

        $channel->basic_qos(0, 1, false);
        $this->info('Menunggu pesan transaksi di antrean kasir.buku-kas. Tekan Ctrl+C untuk berhenti.');

        $channel->basic_consume(
            'kasir.buku-kas',
            '',
            false,
            false,
            false,
            false,
            function (AMQPMessage $pesan) use ($rekam) {
                try {
                    $event = json_decode($pesan->getBody(), true, 512, JSON_THROW_ON_ERROR);
                    $messageId = $pesan->has('message_id') ? $pesan->get('message_id') : '';

                    $baru = $rekam($event, $messageId);
                    $this->line(($baru ? 'Tercatat Kas : ' : 'Dilewati (Duplikat) : ') . $pesan->getBody());
                    $pesan->ack();
                } catch (PesanTransaksiTidakValid | JsonException $e) {
                    $this->error('Pesan korup dialihkan ke DLQ: ' . $e->getMessage());
                    $pesan->nack(false);
                } catch (Throwable $e) {
                    $this->error('Galat sistem kasir: ' . $e->getMessage());
                    $pesan->nack(! $pesan->isRedelivered());
                }
            }
        );

        $channel->consume();
        return self::SUCCESS;
    }
}
