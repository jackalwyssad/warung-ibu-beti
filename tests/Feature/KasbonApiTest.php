<?php

namespace Tests\Feature;

use App\Models\Kasbon;
use App\Models\OutboxEvent;
use App\Models\Pelanggan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasbonApiTest extends TestCase
{
    use RefreshDatabase;

    private function pelangganContoh(int $limit = 100000, int $kasbonSaatIni = 0): Pelanggan
    {
        return Pelanggan::create([
            'nama' => 'Pak Joko Tetangga Depan',
            'nomor_wa' => '081234567890',
            'limit_kasbon' => $limit,
            'total_kasbon' => $kasbonSaatIni,
        ]);
    }

    public function test_catat_kasbon_pelanggan_berhasil_dan_buat_outbox(): void
    {
        $pelanggan = $this->pelangganContoh(limit: 100000, kasbonSaatIni: 20000);

        $this->postJson(
            "/api/v1/pelanggan/{$pelanggan->id}/kasbon",
            [
                'nominal' => 35000,
                'keterangan' => 'Ambil Beras 2kg dan Telur 1/2 kg',
            ],
            ['Idempotency-Key' => 'KB-20261019-001']
        )
            ->assertCreated()
            ->assertJsonPath('data.nominal', 35000)
            ->assertJsonPath('data.tipe', 'pinjam')
            ->assertJsonPath('data.keterangan', 'Ambil Beras 2kg dan Telur 1/2 kg');

        $this->assertSame(55000, $pelanggan->fresh()->total_kasbon);

        $this->assertDatabaseHas('outbox_events', [
            'message_id' => 'KB-20261019-001',
            'event_type' => 'KasbonPelangganDicatat',
            'routing_key' => 'kasbon.catat',
            'status' => 'pending',
        ]);
    }

    public function test_kasbon_melebihi_limit_ditolak_dengan_409_problem_details(): void
    {
        $pelanggan = $this->pelangganContoh(limit: 100000, kasbonSaatIni: 85000);

        $this->postJson(
            "/api/v1/pelanggan/{$pelanggan->id}/kasbon",
            [
                'nominal' => 25000, // 85k + 25k = 110k > limit 100k
                'keterangan' => 'Minyak Goreng 1 Dus',
            ]
        )
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 409)
            ->assertJsonPath('type', 'https://warungibubeti.test/problem/batas-kasbon-terlampaui')
            ->assertJsonPath('kasbon_aktif', 85000)
            ->assertJsonPath('limit_kasbon', 100000);

        $this->assertSame(85000, $pelanggan->fresh()->total_kasbon);
        $this->assertSame(0, Kasbon::count());
    }

    public function test_idempotensi_kasbon_dengan_key_yang_sama_tidak_menduplikasi_utang(): void
    {
        $pelanggan = $this->pelangganContoh(limit: 150000, kasbonSaatIni: 0);

        $headers = ['Idempotency-Key' => 'KB-DUP-001'];
        $body = ['nominal' => 40000, 'keterangan' => 'Tabung Gas Elpiji 3kg'];

        $res1 = $this->postJson("/api/v1/pelanggan/{$pelanggan->id}/kasbon", $body, $headers)->assertCreated();
        $res2 = $this->postJson("/api/v1/pelanggan/{$pelanggan->id}/kasbon", $body, $headers)->assertCreated();

        $this->assertSame(1, Kasbon::count());
        $this->assertSame(40000, $pelanggan->fresh()->total_kasbon);
    }

    public function test_pembayaran_kasbon_mengurangi_total_utang_dan_tercatat_outbox(): void
    {
        // Pelanggan punya utang aktif 75.000
        $pelanggan = $this->pelangganContoh(limit: 150000, kasbonSaatIni: 75000);

        // Pelanggan membayar cicilan 50.000
        $this->postJson(
            "/api/v1/pelanggan/{$pelanggan->id}/bayar-kasbon",
            [
                'nominal' => 50000,
                'keterangan' => 'Cicilan kasbon gajian minggu ke-3',
            ],
            ['Idempotency-Key' => 'BYR-20261019-001']
        )
            ->assertOk()
            ->assertJsonPath('data.nominal', 50000)
            ->assertJsonPath('data.tipe', 'bayar')
            ->assertJsonPath('data.sisa_kasbon_pelanggan', 25000);

        // Kasbon berkurang menjadi 25.000
        $this->assertSame(25000, $pelanggan->fresh()->total_kasbon);

        // Tercatat ke outbox event
        $this->assertDatabaseHas('outbox_events', [
            'message_id' => 'BYR-20261019-001',
            'event_type' => 'KasbonPelangganDibayar',
            'routing_key' => 'kasbon.bayar',
            'status' => 'pending',
        ]);
    }

    public function test_pembayaran_kasbon_melebihi_utang_ditolak_dengan_409(): void
    {
        // Pelanggan punya utang 30.000
        $pelanggan = $this->pelangganContoh(limit: 100000, kasbonSaatIni: 30000);

        // Mencoba bayar 50.000 (melebihi 30.000 -> akan minus jika diizinkan)
        $this->postJson(
            "/api/v1/pelanggan/{$pelanggan->id}/bayar-kasbon",
            [
                'nominal' => 50000,
                'keterangan' => 'Bayar lebih',
            ]
        )
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 409)
            ->assertJsonPath('type', 'https://warungibubeti.test/problem/pembayaran-kasbon-melebihi-utang')
            ->assertJsonPath('kasbon_aktif', 30000)
            ->assertJsonPath('nominal_bayar', 50000);

        // Kasbon tetap utuh 30.000, tidak minus
        $this->assertSame(30000, $pelanggan->fresh()->total_kasbon);
    }

    public function test_idempotensi_pembayaran_kasbon_tidak_memotong_dua_kali(): void
    {
        $pelanggan = $this->pelangganContoh(limit: 100000, kasbonSaatIni: 50000);

        $headers = ['Idempotency-Key' => 'BYR-DUP-01'];
        $body = ['nominal' => 20000, 'keterangan' => 'Cicilan 20rb'];

        $this->postJson("/api/v1/pelanggan/{$pelanggan->id}/bayar-kasbon", $body, $headers)->assertOk();
        $this->postJson("/api/v1/pelanggan/{$pelanggan->id}/bayar-kasbon", $body, $headers)->assertOk();

        // Hanya terpotong sekali (50.000 - 20.000 = 30.000)
        $this->assertSame(30000, $pelanggan->fresh()->total_kasbon);
        $this->assertSame(1, Kasbon::where('tipe', 'bayar')->count());
    }
}
