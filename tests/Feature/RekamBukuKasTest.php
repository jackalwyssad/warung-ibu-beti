<?php

namespace Tests\Feature;

use App\Models\BukuKasWarung;
use App\Pembukuan\PesanTransaksiTidakValid;
use App\Pembukuan\RekamBukuKas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekamBukuKasTest extends TestCase
{
    use RefreshDatabase;

    private function eventContoh(array $ubah = []): array
    {
        return array_merge([
            'kode_barang' => 'SBK-001',
            'perubahan' => -4,
            'jenis_transaksi' => 'penjualan_kasir',
            'stok_sesudah' => 11,
            'harga' => 72000,
            'terjadi_pada' => '2026-10-19T20:30:00+07:00',
        ], $ubah);
    }

    public function test_penjualan_dicatat_ke_buku_kas_warung(): void
    {
        $baru = app(RekamBukuKas::class)($this->eventContoh(), 'msg-kasir-001');
        $this->assertTrue($baru);

        $kas = BukuKasWarung::sole();
        $this->assertSame('SBK-001', $kas->kode_barang);
        $this->assertSame(4, $kas->qty_keluar);
        $this->assertSame(288000, $kas->total_omzet);
        $this->assertSame('kasir_pos', $kas->kanal_transaksi);
    }

    public function test_pesan_event_yang_sama_tidak_dicatat_dua_kali(): void
    {
        $rekam = app(RekamBukuKas::class);
        $rekam($this->eventContoh(), 'msg-kasir-001');
        $baru = $rekam($this->eventContoh(), 'msg-kasir-001');

        $this->assertFalse($baru);
        $this->assertSame(1, BukuKasWarung::count());
    }

    public function test_kulakan_gudang_bukan_penjualan_dan_dilewati(): void
    {
        $baru = app(RekamBukuKas::class)($this->eventContoh([
            'perubahan' => 20,
            'jenis_transaksi' => 'kulakan_gudang',
        ]), 'msg-gudang-002');

        $this->assertFalse($baru);
        $this->assertSame(0, BukuKasWarung::count());
    }

    public function test_pesan_tanpa_field_wajib_ditolak_ke_dlq(): void
    {
        $this->expectException(PesanTransaksiTidakValid::class);
        $event = $this->eventContoh();
        unset($event['harga']);

        app(RekamBukuKas::class)($event, 'msg-err-003');
    }

    public function test_pesan_tanpa_message_id_ditolak(): void
    {
        $this->expectException(PesanTransaksiTidakValid::class);
        app(RekamBukuKas::class)($this->eventContoh(), '');
    }
}
