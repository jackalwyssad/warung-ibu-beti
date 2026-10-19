<?php

namespace App\Pembukuan;

use App\Models\JurnalPenjualan;
use Illuminate\Support\Carbon;

class CatatPenjualan
{
    private const FIELD_WAJIB = ['sku', 'perubahan', 'alasan', 'harga', 'terjadi_pada'];

    /**
     * Mencatat satu event StokDisesuaikan ke jurnal penjualan.
     * Mengembalikan true bila baris jurnal baru dibuat.
     */
    public function __invoke(array $event, string $messageId): bool
    {
        if ($messageId === '') {
            throw new PesanTidakValid('Pesan tidak memiliki message_id.');
        }

        foreach (self::FIELD_WAJIB as $field) {
            if (! array_key_exists($field, $event)) {
                throw new PesanTidakValid("Field {$field} tidak ada.");
            }
        }

        // Pembukuan hanya peduli penjualan; restok dan koreksi dilewati.
        if ($event['alasan'] !== 'penjualan') {
            return false;
        }

        $jumlah = abs((int) $event['perubahan']);

        // firstOrCreate + kolom unik message_id = handler idempoten:
        // pesan yang dikirim ulang oleh broker tidak dicatat dua kali.
        $jurnal = JurnalPenjualan::firstOrCreate(
            ['message_id' => $messageId],
            [
                'sku' => $event['sku'],
                'jumlah' => $jumlah,
                'harga_satuan' => (int) $event['harga'],
                'total' => $jumlah * (int) $event['harga'],
                'terjadi_pada' => Carbon::parse($event['terjadi_pada']),
            ],
        );

        return $jurnal->wasRecentlyCreated;
    }
}
