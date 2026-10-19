<?php

namespace App\Listeners;

use App\Events\TransaksiSembakoTercatat;
use App\Messaging\RabbitPublisher;

class TerbitkanTransaksiSembako
{
    public function __construct(private RabbitPublisher $publisher) {}

    public function handle(TransaksiSembakoTercatat $event): void
    {
        $routingKey = match ($event->jenisTransaksi) {
            'penjualan_kasir' => 'transaksi.kasir',
            'pesanan_wa' => 'transaksi.wa',
            'kulakan_gudang' => 'logistik.kulakan',
            'retur_rusak' => 'logistik.retur',
            default => 'transaksi.umum',
        };

        $this->publisher->publish($routingKey, $event->payload(), $event->id, 'TransaksiSembakoTercatat');
    }
}
