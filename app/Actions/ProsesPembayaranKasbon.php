<?php

namespace App\Actions;

use App\Events\KasbonPelangganDibayar;
use App\Exceptions\PembayaranKasbonMelebihiUtang;
use App\Models\Kasbon;
use App\Models\OutboxEvent;
use App\Models\Pelanggan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProsesPembayaranKasbon
{
    public function execute(Pelanggan $pelanggan, int $nominal, ?string $keterangan = null, ?string $idempotencyKey = null): Kasbon
    {
        return DB::transaction(function () use ($pelanggan, $nominal, $keterangan, $idempotencyKey) {
            $terkini = Pelanggan::whereKey($pelanggan->id)->lockForUpdate()->first();

            // Aturan Bisnis Warung: Total kasbon tidak boleh sampai minus (< 0)
            if ($nominal > $terkini->total_kasbon) {
                throw new PembayaranKasbonMelebihiUtang(
                    $terkini->id,
                    $terkini->nama,
                    $terkini->total_kasbon,
                    $nominal
                );
            }

            $msgId = $idempotencyKey ?: 'bayar-' . Str::uuid();

            // Cek idempotensi
            $existing = Kasbon::where('id_transaksi_kasbon', $msgId)->first();
            if ($existing) {
                return $existing;
            }

            $ket = $keterangan ?: 'Pembayaran / cicilan kasbon';

            $kasbon = Kasbon::create([
                'pelanggan_id' => $terkini->id,
                'id_transaksi_kasbon' => $msgId,
                'tipe' => 'bayar',
                'nominal' => $nominal,
                'keterangan' => $ket,
            ]);

            // Kurangi kasbon pelanggan (pasti >= 0 karena sudah dicek di atas)
            $terkini->decrement('total_kasbon', $nominal);
            $terkini->refresh();

            // Simpan ke Outbox Table (Outbox Pattern)
            $event = new KasbonPelangganDibayar(
                $terkini->id,
                $terkini->nama,
                $nominal,
                $terkini->total_kasbon,
                $ket,
                $msgId
            );

            OutboxEvent::create([
                'message_id' => $msgId,
                'event_type' => 'KasbonPelangganDibayar',
                'routing_key' => 'kasbon.bayar',
                'payload' => $event->payload(),
                'status' => 'pending',
            ]);

            return $kasbon;
        });
    }
}
