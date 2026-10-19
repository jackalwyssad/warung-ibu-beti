<?php

namespace Tests\Feature;

use App\Messaging\RabbitPublisher;
use App\Models\OutboxEvent;
use App\Services\OutboxPublisherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OutboxPatternTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_publisher_menerbitkan_event_pending_ke_rabbitmq(): void
    {
        $outbox = OutboxEvent::create([
            'message_id' => 'evt-test-123',
            'event_type' => 'TransaksiKasirSelesai',
            'routing_key' => 'transaksi.kasir',
            'payload' => [
                'kode_barang' => 'SBK-001',
                'omzet' => 144000,
                'modal' => 124000,
                'laba_kotor' => 20000,
                'terjadi_pada' => now()->toIso8601String(),
            ],
            'status' => 'pending',
        ]);

        $mockPublisher = Mockery::mock(RabbitPublisher::class);
        $mockPublisher->shouldReceive('publish')->once()->with(
            'transaksi.kasir',
            Mockery::type('array'),
            'evt-test-123',
            'TransaksiKasirSelesai'
        );

        $service = new OutboxPublisherService($mockPublisher);
        $terkirim = $service->publishPending();

        $this->assertSame(1, $terkirim);
        $this->assertSame('published', $outbox->fresh()->status);
        $this->assertNotNull($outbox->fresh()->published_at);
    }
}
