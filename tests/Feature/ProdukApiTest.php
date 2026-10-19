<?php

namespace Tests\Feature;

use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setiap test di bawah ini menerjemahkan satu janji pada openapi.yaml
 * menjadi pemeriksaan yang bisa dijalankan (Warung Ibu Beti).
 */
class ProdukApiTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        // Event dan Queue ditahan di test agar tidak mencoba menghubungi RabbitMQ
        \Illuminate\Support\Facades\Event::fake([\App\Events\StokDisesuaikan::class]);
        \Illuminate\Support\Facades\Queue::fake();
    }


    private function produkContoh(int $stok = 10): Produk
    {
        return Produk::create([
            'sku' => 'BRG-001',
            'nama' => 'Beras Pandan Wangi 5kg',
            'harga' => 75000,
            'stok' => $stok,
        ]);
    }

    public function test_daftar_produk_mengembalikan_200_dan_larik_data(): void
    {
        $this->produkContoh();

        $this->getJson('/api/v1/produk')
            ->assertOk()
            ->assertJsonStructure(['data' => [['sku', 'nama', 'harga', 'stok', 'diperbarui_pada']]]);
    }

    public function test_menambah_produk_mengembalikan_201_dan_header_location(): void
    {
        $this->postJson('/api/v1/produk', [
            'sku' => 'MYK-002',
            'nama' => 'Minyak Goreng 2L',
            'harga' => 34000,
        ])
            ->assertCreated()
            ->assertHeader('Location', url('/api/v1/produk/MYK-002'))
            ->assertJsonPath('data.stok', 0);
    }

    public function test_produk_tanpa_nama_ditolak_dengan_422_problem_json(): void
    {
        $this->postJson('/api/v1/produk', ['sku' => 'MYK-002', 'harga' => 34000])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 422)
            ->assertJsonStructure(['type', 'title', 'status', 'detail', 'errors' => ['nama']]);
    }

    public function test_sku_yang_tidak_ada_mengembalikan_404_problem_json(): void
    {
        $this->getJson('/api/v1/produk/XXX-999')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 404);
    }

    public function test_penyesuaian_stok_mengubah_stok(): void
    {
        $this->produkContoh(stok: 10);

        $this->postJson('/api/v1/produk/BRG-001/penyesuaian-stok', [
            'perubahan' => -3,
            'alasan' => 'penjualan',
        ])
            ->assertOk()
            ->assertJsonPath('data.stok', 7);
    }

    public function test_pengurangan_melebihi_stok_ditolak_dengan_409(): void
    {
        $this->produkContoh(stok: 3);

        $this->postJson('/api/v1/produk/BRG-001/penyesuaian-stok', [
            'perubahan' => -5,
            'alasan' => 'penjualan',
        ])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'https://warungibubeti.test/problem/stok-tidak-cukup');

        $this->assertSame(3, Produk::where('sku', 'BRG-001')->value('stok'));
    }
}
