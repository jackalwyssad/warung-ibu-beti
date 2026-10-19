<?php

namespace Tests\Feature;

use App\Models\BukuKasWarung;
use App\Models\RekapHarianWarung;
use App\Pembukuan\PesanTransaksiTidakValid;
use App\Services\BukuKasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BukuKasConsumerTest extends TestCase
{
    use RefreshDatabase;

    private function payloadContoh(array $ubah = []): array
    {
        return array_merge([
            'kode_barang' => 'SBK-001',
            'jenis_transaksi' => 'penjualan_kasir',
            'omzet' => 288000,
            'modal' => 248000,
            'laba_kotor' => 40000,
            'terjadi_pada' => '2026-10-19T10:00:00+07:00',
        ], $ubah);
    }

    public function test_consumer_mencatat_omzet_modal_laba_dan_rekap_harian(): void
    {
        $service = new BukuKasService();
        $baru = $service->rekamTransaksi($this->payloadContoh(), 'msg-pos-001');

        $this->assertTrue($baru);

        $kas = BukuKasWarung::sole();
        $this->assertSame('SBK-001', $kas->kode_referensi);
        $this->assertSame(288000, $kas->omzet);
        $this->assertSame(248000, $kas->modal);
        $this->assertSame(40000, $kas->laba_kotor);

        // Periksa rekap harian
        $rekap = RekapHarianWarung::whereDate('tanggal', '2026-10-19')->first();
        $this->assertNotNull($rekap);
        $this->assertSame(1, $rekap->total_transaksi);
        $this->assertSame(288000, $rekap->total_omzet);
        $this->assertSame(40000, $rekap->total_laba_kotor);
    }

    public function test_pesan_event_yang_sama_tidak_menduplikasi_rekap_kas_harian(): void
    {
        $service = new BukuKasService();
        $payload = $this->payloadContoh();

        $service->rekamTransaksi($payload, 'msg-pos-001');
        $kedua = $service->rekamTransaksi($payload, 'msg-pos-001');

        $this->assertFalse($kedua);
        $this->assertSame(1, BukuKasWarung::count());

        $rekap = RekapHarianWarung::whereDate('tanggal', '2026-10-19')->first();
        $this->assertSame(1, $rekap->total_transaksi);
        $this->assertSame(288000, $rekap->total_omzet);
    }

    public function test_pesan_korup_tanpa_omzet_ditolak_dengan_exception_ke_dlq(): void
    {
        $this->expectException(PesanTransaksiTidakValid::class);

        $service = new BukuKasService();
        $payload = $this->payloadContoh();
        unset($payload['omzet']);

        $service->rekamTransaksi($payload, 'msg-err-99');
    }
}
