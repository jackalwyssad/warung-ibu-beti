<?php

namespace App\Listeners;

use App\Events\StokDisesuaikan;
use App\Messaging\RabbitPublisher;

class TerbitkanStokDisesuaikan
{
    public function __construct(private RabbitPublisher $publisher) {}

    public function handle(StokDisesuaikan $event): void
    {
        // Routing key: stok.penjualan, stok.restok, atau stok.koreksi
        $this->publisher->publish("stok.{$event->alasan}", $event->payload(), $event->id, 'StokDisesuaikan');
    }
}
