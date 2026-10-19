<?php

namespace App\Pembukuan;

use App\Models\BukuKasWarung;
use Illuminate\Support\Carbon;

class RekamBukuKas
{
    private const FIELD_WAJIB = ['kode_barang', 'perubahan', 'jenis_transaksi', 'harga', 'terjadi_pada'];

    public function __invoke(array $event, string $messageId): bool
    {
        if ($messageId === '') {
            throw new PesanTransaksiTidakValid('Pesan transaksi tidak memiliki message_id unik.');
        }

        foreach (self::FIELD_WAJIB as $field) {
            if (! array_key_exists($field, $event)) {
                throw new PesanTransaksiTidakValid("Field wajib {$field} tidak ditemukan pada payload event.");
            }
        }

        if (! in_array($event['jenis_transaksi'], ['penjualan_kasir', 'pesanan_wa'], true)) {
            return false;
        }

        $qtyKeluar = abs((int) $event['perubahan']);
        $harga = (int) $event['harga'];

        $kas = BukuKasWarung::firstOrCreate(
            ['id_event_transaksi' => $messageId],
            [
                'kode_barang' => $event['kode_barang'],
                'qty_keluar' => $qtyKeluar,
                'harga_eceran' => $harga,
                'total_omzet' => $qtyKeluar * $harga,
                'kanal_transaksi' => $event['jenis_transaksi'] === 'pesanan_wa' ? 'whatsapp' : 'kasir_pos',
                'terjadi_pada' => Carbon::parse($event['terjadi_pada']),
            ]
        );

        return $kas->wasRecentlyCreated;
    }
}
