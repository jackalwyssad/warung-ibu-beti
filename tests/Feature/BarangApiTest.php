<?php

namespace Tests\Feature;

use App\Models\Barang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Event::fake([\App\Events\TransaksiSembakoTercatat::class]);
        \Illuminate\Support\Facades\Queue::fake();
    }

    private function barangContoh(int $stok = 15): Barang
    {
        return Barang::create([
            'kode_barang' => 'SBK-001',
            'nama' => 'Beras Rojo Lele 5kg',
            'kategori' => 'sembako',
            'harga' => 72000,
            'stok' => $stok,
        ]);
    }

    public function test_daftar_barang_mengembalikan_200_dan_larik_data(): void
    {
        $this->barangContoh();

        $this->getJson('/api/v1/barang')
            ->assertOk()
            ->assertJsonStructure(['data' => [['kode_barang', 'nama', 'kategori', 'harga', 'stok', 'tersedia', 'diperbarui_pada']]]);
    }

    public function test_menambah_barang_sembako_mengembalikan_201_dan_header_location(): void
    {
        $this->postJson('/api/v1/barang', [
            'kode_barang' => 'MYK-002',
            'nama' => 'Minyak Goreng Tropical 2L',
            'kategori' => 'sembako',
            'harga' => 36000,
        ])
            ->assertCreated()
            ->assertHeader('Location', url('/api/v1/barang/MYK-002'))
            ->assertJsonPath('data.stok', 0);
    }

    public function test_barang_tanpa_nama_ditolak_dengan_422_problem_json(): void
    {
        $this->postJson('/api/v1/barang', [
            'kode_barang' => 'MYK-002',
            'harga' => 36000,
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

    public function test_catat_transaksi_kasir_mengurangi_persediaan(): void
    {
        $this->barangContoh(stok: 15);

        $this->postJson('/api/v1/barang/SBK-001/catat-transaksi', [
            'perubahan' => -4,
            'jenis_transaksi' => 'penjualan_kasir',
        ])
            ->assertOk()
            ->assertJsonPath('data.stok', 11);
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
    }
}
