<?php

namespace App\Actions;

use App\Events\PeringatanStokKritis;
use App\Events\TransaksiKasirSelesai;
use App\Exceptions\PersediaanSembakoDefisit;
use App\Models\Barang;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProsesTransaksiKasir
{
    public function execute(Barang $barang, int $perubahan, string $jenisTransaksi, ?string $idempotencyKey = null): Barang
    {
        return DB::transaction(function () use ($barang, $perubahan, $jenisTransaksi, $idempotencyKey) {
            $terkini = Barang::whereKey($barang->id)->lockForUpdate()->first();

            if ($terkini->stok + $perubahan < 0) {
                throw new PersediaanSembakoDefisit($terkini->kode_barang, $terkini->stok, abs($perubahan));
            }

            $terkini->increment('stok', $perubahan);
            $terkini->refresh();

            $msgId = $idempotencyKey ?: (string) Str::uuid();

            // 1. Simpan Domain Event ke Outbox Table (Outbox Pattern)
            if ($perubahan < 0) {
                $qty = abs($perubahan);
                $event = new TransaksiKasirSelesai(
                    $terkini->kode_barang,
                    $qty,
                    $jenisTransaksi,
                    $terkini->harga_beli,
                    $terkini->harga_jual,
                    $terkini->stok,
                    $msgId
                );

                OutboxEvent::create([
                    'message_id' => $msgId,
                    'event_type' => 'TransaksiKasirSelesai',
                    'routing_key' => $jenisTransaksi === 'pesanan_wa' ? 'transaksi.wa' : 'transaksi.kasir',
                    'payload' => $event->payload(),
                    'status' => 'pending',
                ]);
            }

            // Jika stok menipis (<= 3), buat event peringatan stok
            if ($terkini->stok <= 3) {
                $kritis = new PeringatanStokKritis($terkini->kode_barang, $terkini->nama, $terkini->stok);
                OutboxEvent::create([
                    'message_id' => (string) Str::uuid(),
                    'event_type' => 'PeringatanStokKritis',
                    'routing_key' => 'stok.kritis',
                    'payload' => $kritis->payload(),
                    'status' => 'pending',
                ]);
            }

            return $terkini;
        });
    }
}
