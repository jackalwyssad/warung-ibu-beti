<?php

namespace App\Actions;

use App\Events\KasbonPelangganDicatat;
use App\Exceptions\BatasKasbonTerlampaui;
use App\Models\Kasbon;
use App\Models\OutboxEvent;
use App\Models\Pelanggan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProsesKasbonPelanggan
{
    public function execute(Pelanggan $pelanggan, int $nominal, string $keterangan, ?string $idempotencyKey = null): Kasbon
    {
        return DB::transaction(function () use ($pelanggan, $nominal, $keterangan, $idempotencyKey) {
            $terkini = Pelanggan::whereKey($pelanggan->id)->lockForUpdate()->first();

            if ($terkini->total_kasbon + $nominal > $terkini->limit_kasbon) {
                throw new BatasKasbonTerlampaui(
                    $terkini->id,
                    $terkini->nama,
                    $terkini->total_kasbon,
                    $nominal,
                    $terkini->limit_kasbon
                );
            }

            $msgId = $idempotencyKey ?: 'kasbon-' . Str::uuid();

            // Cek idempotensi: jika ID transaksi ini sudah ada, kembalikan record kasbon yang sudah ada
            $existing = Kasbon::where('id_transaksi_kasbon', $msgId)->first();
            if ($existing) {
                return $existing;
            }

            $kasbon = Kasbon::create([
                'pelanggan_id' => $terkini->id,
                'id_transaksi_kasbon' => $msgId,
                'nominal' => $nominal,
                'keterangan' => $keterangan,
            ]);

            $terkini->increment('total_kasbon', $nominal);
            $terkini->refresh();

            // Simpan ke Outbox Table
            $event = new KasbonPelangganDicatat(
                $terkini->id,
                $terkini->nama,
                $nominal,
                $terkini->total_kasbon,
                $terkini->limit_kasbon - $terkini->total_kasbon,
                $keterangan,
                $msgId
            );

            OutboxEvent::create([
                'message_id' => $msgId,
                'event_type' => 'KasbonPelangganDicatat',
                'routing_key' => 'kasbon.catat',
                'payload' => $event->payload(),
                'status' => 'pending',
            ]);

            return $kasbon;
        });
    }
}
