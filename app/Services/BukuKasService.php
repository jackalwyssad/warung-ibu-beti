<?php

namespace App\Services;

use App\Models\BukuKasWarung;
use App\Models\RekapHarianWarung;
use App\Pembukuan\PesanTransaksiTidakValid;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BukuKasService
{
    public function rekamTransaksi(array $payload, string $messageId): bool
    {
        if ($messageId === '') {
            throw new PesanTransaksiTidakValid('Pesan transaksi tidak memiliki message_id unik.');
        }

        if (! isset($payload['omzet'], $payload['modal'], $payload['laba_kotor'], $payload['terjadi_pada'])) {
            throw new PesanTransaksiTidakValid('Payload transaksi tidak lengkap (omzet/modal/laba_kotor tidak ada).');
        }

        return DB::transaction(function () use ($payload, $messageId) {
            $waktu = Carbon::parse($payload['terjadi_pada']);
            $tanggal = $waktu->format('Y-m-d');

            $bukuKas = BukuKasWarung::firstOrCreate(
                ['id_event_transaksi' => $messageId],
                [
                    'kode_referensi' => $payload['kode_barang'] ?? ($payload['nama_pelanggan'] ?? 'UMUM'),
                    'jenis_aliran' => $payload['jenis_transaksi'] ?? 'penjualan',
                    'omzet' => (int) $payload['omzet'],
                    'modal' => (int) $payload['modal'],
                    'laba_kotor' => (int) $payload['laba_kotor'],
                    'terjadi_pada' => $waktu,
                ]
            );

            if (! $bukuKas->wasRecentlyCreated) {
                return false;
            }

            $rekap = RekapHarianWarung::whereDate('tanggal', $tanggal)->first();
            if (! $rekap) {
                $rekap = RekapHarianWarung::create([
                    'tanggal' => $tanggal,
                    'total_transaksi' => 0,
                    'total_omzet' => 0,
                    'total_modal' => 0,
                    'total_laba_kotor' => 0,
                ]);
            }

            $rekap->increment('total_transaksi', 1);
            $rekap->increment('total_omzet', (int) $payload['omzet']);
            $rekap->increment('total_modal', (int) $payload['modal']);
            $rekap->increment('total_laba_kotor', (int) $payload['laba_kotor']);

            return true;
        });
    }
}
