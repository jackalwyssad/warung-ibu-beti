<?php

namespace Tests\Feature;

use App\Events\StokDisesuaikan;
use App\Jobs\SinkronkanStokWhatsApp;
use App\Listeners\TerbitkanStokDisesuaikan;
use App\Messaging\RabbitPublisher;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class EventStokTest extends TestCase
{
    use RefreshDatabase;

    private function produkContoh(int $stok = 10): Produk
    {
        return Produk::create([
            'sku' => 'BRG-001',
            'nama' => 'Beras Pandan Wangi 5kg',
            'harga' => 75000,
            'stok' => $stok,
        ]);
    }

    public function test_penyesuaian_stok_menerbitkan_event_stok_disesuaikan(): void
    {
        Event::fake([StokDisesuaikan::class]);
        Queue::fake();

        $this->produkContoh(stok: 10);

        $this->postJson('/api/v1/produk/BRG-001/penyesuaian-stok', [
            'perubahan' => -3,
            'alasan' => 'penjualan',
        ])->assertOk();

        Event::assertDispatched(StokDisesuaikan::class, fn (StokDisesuaikan $e) =>
            $e->sku === 'BRG-001'
            && $e->perubahan === -3
            && $e->alasan === 'penjualan'
            && $e->stokSesudah === 7
            && $e->harga === 75000
        );
    }

    public function test_penyesuaian_yang_ditolak_tidak_menerbitkan_event(): void
    {
        Event::fake([StokDisesuaikan::class]);
        Queue::fake();

        $this->produkContoh(stok: 3);

        $this->postJson('/api/v1/produk/BRG-001/penyesuaian-stok', [
            'perubahan' => -5,
            'alasan' => 'penjualan',
        ])->assertStatus(409);

        Event::assertNotDispatched(StokDisesuaikan::class);
    }

    public function test_listener_menerbitkan_ke_rabbitmq_dengan_routing_key_sesuai_alasan(): void
    {
        $event = new StokDisesuaikan('BRG-001', -3, 'penjualan', 7, 75000);

        $this->mock(RabbitPublisher::class, function ($mock) use ($event) {
            $mock->shouldReceive('publish')->once()->with(
                'stok.penjualan',
                Mockery::on(fn (array $data) => $data['sku'] === 'BRG-001' && $data['stok_sesudah'] === 7),
                $event->id,
                'StokDisesuaikan',
            );
        });

        app(TerbitkanStokDisesuaikan::class)->handle($event);
    }

    public function test_penyesuaian_stok_mengantrekan_sinkronisasi_whatsapp(): void
    {
        Event::fake([StokDisesuaikan::class]);
        Queue::fake();

        $this->produkContoh(stok: 10);

        $this->postJson('/api/v1/produk/BRG-001/penyesuaian-stok', [
            'perubahan' => 5,
            'alasan' => 'restok',
        ])->assertOk();

        Queue::assertPushedOn(
            'notifikasi-wa',
            SinkronkanStokWhatsApp::class,
            fn (SinkronkanStokWhatsApp $job) => $job->sku === 'BRG-001' && $job->stok === 15
        );
    }
}
