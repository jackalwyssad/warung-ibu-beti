<?php

namespace Tests\Feature;

use App\Models\JurnalPenjualan;
use App\Pembukuan\CatatPenjualan;
use App\Pembukuan\PesanTidakValid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatatPenjualanTest extends TestCase
{
    use RefreshDatabase;

    private function eventPenjualan(array $ubah = []): array
    {
        return array_merge([
            'sku' => 'BRG-001',
            'perubahan' => -3,
            'alasan' => 'penjualan',
            'stok_sesudah' => 7,
            'harga' => 75000,
            'terjadi_pada' => '2026-10-19T10:00:00+07:00',
        ], $ubah);
    }

    public function test_penjualan_dicatat_ke_jurnal(): void
    {
        $baru = app(CatatPenjualan::class)($this->eventPenjualan(), 'pesan-1');
        $this->assertTrue($baru);

        $jurnal = JurnalPenjualan::sole();
        $this->assertSame('BRG-001', $jurnal->sku);
        $this->assertSame(3, $jurnal->jumlah);
        $this->assertSame(225000, $jurnal->total);
    }

    public function test_pesan_yang_sama_tidak_dicatat_dua_kali(): void
    {
        $catat = app(CatatPenjualan::class);
        $catat($this->eventPenjualan(), 'pesan-1');
        $baru = $catat($this->eventPenjualan(), 'pesan-1');

        $this->assertFalse($baru);
        $this->assertSame(1, JurnalPenjualan::count());
    }

    public function test_restok_bukan_penjualan_dan_dilewati(): void
    {
        $baru = app(CatatPenjualan::class)($this->eventPenjualan(['perubahan' => 5, 'alasan' => 'restok']), 'pesan-2');

        $this->assertFalse($baru);
        $this->assertSame(0, JurnalPenjualan::count());
    }

    public function test_pesan_tanpa_field_wajib_ditolak(): void
    {
        $this->expectException(PesanTidakValid::class);
        $event = $this->eventPenjualan();
        unset($event['harga']);

        app(CatatPenjualan::class)($event, 'pesan-3');
    }

    public function test_pesan_tanpa_message_id_ditolak(): void
    {
        $this->expectException(PesanTidakValid::class);
        app(CatatPenjualan::class)($this->eventPenjualan(), '');
    }
}
