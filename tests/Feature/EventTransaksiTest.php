<?php

namespace Tests\Feature;

use App\Events\TransaksiSembakoTercatat;
use App\Jobs\SinkronkanKatalogWhatsApp;
use App\Listeners\TerbitkanTransaksiSembako;
use App\Messaging\RabbitPublisher;
use App\Models\Barang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class EventTransaksiTest extends TestCase
{
    use RefreshDatabase;

    private function barangContoh(int $stok = 20): Barang
    {
        return Barang::create([
            'kode_barang' => 'SBK-001',
            'nama' => 'Beras Rojo Lele 5kg',
            'kategori' => 'sembako',
            'harga' => 72000,
            'stok' => $stok,
        ]);
    }

    public function test_transaksi_kasir_menerbitkan_event_transaksi_sembako(): void
    {
        Event::fake([TransaksiSembakoTercatat::class]);
        Queue::fake();

        $this->barangContoh(stok: 20);

        $this->postJson('/api/v1/barang/SBK-001/catat-transaksi', [
            'perubahan' => -5,
            'jenis_transaksi' => 'penjualan_kasir',
        ])->assertOk();

        Event::assertDispatched(TransaksiSembakoTercatat::class, function (TransaksiSembakoTercatat $e) {
            return $e->kodeBarang === 'SBK-001'
                && $e->perubahan === -5
                && $e->jenisTransaksi === 'penjualan_kasir'
                && $e->stokSesudah === 15
                && $e->harga === 72000;
        });
    }

    public function test_transaksi_yang_ditolak_tidak_menerbitkan_event(): void
    {
        Event::fake([TransaksiSembakoTercatat::class]);
        Queue::fake();

        $this->barangContoh(stok: 2);

        $this->postJson('/api/v1/barang/SBK-001/catat-transaksi', [
            'perubahan' => -5,
            'jenis_transaksi' => 'penjualan_kasir',
        ])->assertStatus(409);

        Event::assertNotDispatched(TransaksiSembakoTercatat::class);
    }

    public function test_listener_menerbitkan_ke_rabbitmq_dengan_routing_key_sesuai(): void
    {
        $event = new TransaksiSembakoTercatat('SBK-001', -5, 'penjualan_kasir', 15, 72000);

        $this->mock(RabbitPublisher::class, function ($mock) use ($event) {
            $mock->shouldReceive('publish')->once()->with(
                'transaksi.kasir',
                Mockery::on(fn (array $data) => $data['kode_barang'] === 'SBK-001' && $data['stok_sesudah'] === 15),
                $event->id,
                'TransaksiSembakoTercatat'
            );
        });

        app(TerbitkanTransaksiSembako::class)->handle($event);
    }

    public function test_transaksi_mengantrekan_sinkronisasi_whatsapp_katalog(): void
    {
        Event::fake([TransaksiSembakoTercatat::class]);
        Queue::fake();

        $this->barangContoh(stok: 20);

        $this->postJson('/api/v1/barang/SBK-001/catat-transaksi', [
            'perubahan' => 10,
            'jenis_transaksi' => 'kulakan_gudang',
        ])->assertOk();

        Queue::assertPushedOn(
            'antrean-whatsapp',
            SinkronkanKatalogWhatsApp::class,
            fn (SinkronkanKatalogWhatsApp $job) => $job->kodeBarang === 'SBK-001' && $job->stok === 30
        );
    }
}
