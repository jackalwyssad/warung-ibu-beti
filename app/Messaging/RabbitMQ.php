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
     * Menyiapkan exchange, queue, dan binding untuk Warung Ibu Beti.
     */
    public static function siapkanTopologi(AMQPChannel $channel): void
    {
        $exchange = config('rabbitmq.exchange', 'warung.events');

        // Exchange event milik warung-ibu-beti (topic)
        $channel->exchange_declare($exchange, 'topic', false, true, false);

        // Dead letter: exchange + queue tempat pesan yang ditolak dikumpulkan
        $channel->exchange_declare('warung.dlx', 'fanout', false, true, false);
        $channel->queue_declare('pembukuan.penjualan.dlq', false, true, false, false);
        $channel->queue_bind('pembukuan.penjualan.dlq', 'warung.dlx');

        // Queue pembukuan: hanya menerima penjualan; pesan yang ditolak dialihkan ke warung.dlx
        $channel->queue_declare('pembukuan.penjualan', false, true, false, false, false,
            new AMQPTable(['x-dead-letter-exchange' => 'warung.dlx']));
        $channel->queue_bind('pembukuan.penjualan', $exchange, 'stok.penjualan');

        // Queue notifikasi stok: menerima semua perubahan stok (wildcard stok.*)
        $channel->queue_declare('notifikasi.stok', false, true, false, false);
        $channel->queue_bind('notifikasi.stok', $exchange, 'stok.*');
    }
}
