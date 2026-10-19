<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\OutboxEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangApiTest extends TestCase
{
    use RefreshDatabase;

    private function barangContoh(int $stok = 15): Barang
    {
        return Barang::create([
            'kode_barang' => 'SBK-001',
            'nama' => 'Beras Rojo Lele 5kg',
            'kategori' => 'sembako',
            'harga_beli' => 62000,
            'harga_jual' => 72000,
            'stok' => $stok,
        ]);
    }

    public function test_daftar_barang_mengembalikan_200_dan_larik_data_dengan_filter(): void
    {
        $this->barangContoh();

        // Tambah kategori lain
        Barang::create([
            'kode_barang' => 'MNM-001',
            'nama' => 'Teh Botol Sosro',
            'kategori' => 'minuman',
            'harga_beli' => 3000,
            'harga_jual' => 4500,
            'stok' => 24,
        ]);

        $res = $this->getJson('/api/v1/barang?kategori=sembako')
            ->assertOk()
            ->assertJsonStructure(['data' => [['kode_barang', 'nama', 'kategori', 'harga_beli', 'harga_jual', 'margin_persen', 'stok', 'tersedia']]]);

        $this->assertCount(1, $res->json('data'));
        $this->assertSame('SBK-001', $res->json('data.0.kode_barang'));
        $this->assertEquals(16.1, $res->json('data.0.margin_persen')); // Version 1.1.0 feature
    }

    public function test_menambah_barang_sembako_mengembalikan_201_dan_header_location(): void
    {
        $this->postJson('/api/v1/barang', [
            'kode_barang' => 'MYK-002',
            'nama' => 'Minyak Goreng Tropical 2L',
            'kategori' => 'sembako',
            'harga_beli' => 30000,
            'harga_jual' => 36000,
        ])
            ->assertCreated()
            ->assertHeader('Location', url('/api/v1/barang/MYK-002'))
            ->assertJsonPath('data.stok', 0)
            ->assertJsonPath('data.margin_persen', 20);
    }

    public function test_barang_tanpa_nama_ditolak_dengan_422_problem_json(): void
    {
        $this->postJson('/api/v1/barang', [
            'kode_barang' => 'MYK-002',
            'harga_beli' => 30000,
            'harga_jual' => 36000,
        ])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 422)
            ->assertJsonStructure(['type', 'title', 'status', 'detail', 'errors' => ['nama']]);
    }

    public function test_kode_barang_yang_tidak_ada_mengembalikan_404_problem_json(): void
    {
        $this->getJson('/api/v1/barang/SBK-999')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 404);
    }

    public function test_catat_transaksi_kasir_mengurangi_persediaan_dan_catat_outbox(): void
    {
        $this->barangContoh(stok: 15);

        $this->postJson(
            '/api/v1/barang/SBK-001/catat-transaksi',
            [
                'perubahan' => -4,
                'jenis_transaksi' => 'penjualan_kasir',
            ],
            ['Idempotency-Key' => 'TRX-POS-20261019-01']
        )
            ->assertOk()
            ->assertJsonPath('data.stok', 11);

        $this->assertDatabaseHas('outbox_events', [
            'message_id' => 'TRX-POS-20261019-01',
            'event_type' => 'TransaksiKasirSelesai',
            'routing_key' => 'transaksi.kasir',
            'status' => 'pending',
        ]);
    }

    public function test_pengurangan_melebihi_persediaan_warung_ditolak_dengan_409(): void
    {
        $this->barangContoh(stok: 3);

        $this->postJson('/api/v1/barang/SBK-001/catat-transaksi', [
            'perubahan' => -6,
            'jenis_transaksi' => 'penjualan_kasir',
        ])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 409)
            ->assertJsonPath('type', 'https://warungibubeti.test/problem/persediaan-defisit');

        $this->assertSame(3, Barang::where('kode_barang', 'SBK-001')->value('stok'));
        $this->assertSame(0, OutboxEvent::count());
    }
}
