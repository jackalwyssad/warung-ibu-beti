<?php

namespace App\Messaging;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQ
{
    public static function koneksi(): AMQPStreamConnection
    {
        $c = config('rabbitmq');
        return new AMQPStreamConnection($c['host'], $c['port'], $c['user'], $c['password'], $c['vhost']);
    }

    /**
     * Menyiapkan topologi: Exchange Topic, Fanout DLX, antrean buku kas, dan antrean pantau stok
     */
    public static function siapkanTopologi(AMQPChannel $channel): void
    {
        // 1. Topic Exchange utama warung sembako
        $channel->exchange_declare('warung.sembako.events', 'topic', false, true, false);

        // 2. Dead Letter Exchange (Fanout) + Antrean DLQ untuk pesan gagal / korup
        $channel->exchange_declare('warung.sembako.dlx', 'fanout', false, true, false);
        $channel->queue_declare('warung.audit.dlq', false, true, false, false);
        $channel->queue_bind('warung.audit.dlq', 'warung.sembako.dlx');

        // 3. Antrean Buku Kas: Konsumen pencatat keuangan warung, dilengkapi DLX
        $channel->queue_declare(
            'kasir.buku-kas',
            false,
            true,
            false,
            false,
            false,
            new AMQPTable(['x-dead-letter-exchange' => 'warung.sembako.dlx'])
        );
        // Bind transaksi kasir dan pesanan wa (transaksi.*)
        $channel->queue_bind('kasir.buku-kas', 'warung.sembako.events', 'transaksi.*');

        // 4. Antrean Pantau Stok Kritis: Menerima seluruh pergerakan sembako
        $channel->queue_declare('gudang.stok-kritis', false, true, false, false);
        $channel->queue_bind('gudang.stok-kritis', 'warung.sembako.events', '*.*');
    }
}
