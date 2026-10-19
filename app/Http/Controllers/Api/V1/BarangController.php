<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\TransaksiSembakoTercatat;
use App\Exceptions\PersediaanSembakoDefisit;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatatTransaksiBarangRequest;
use App\Http\Requests\TambahBarangRequest;
use App\Http\Resources\BarangResource;
use App\Jobs\SinkronkanKatalogWhatsApp;
use App\Models\Barang;
use Illuminate\Support\Facades\DB;

class BarangController extends Controller
{
    // GET /api/v1/barang
    public function index()
    {
        return BarangResource::collection(Barang::orderBy('kode_barang')->get());
    }

    // POST /api/v1/barang
    public function store(TambahBarangRequest $request)
    {
        $barang = Barang::create($request->validated() + ['stok' => 0]);

        return (new BarangResource($barang))
            ->response()
            ->setStatusCode(201)
            ->header('Location', url("/api/v1/barang/{$barang->kode_barang}"));
    }

    // GET /api/v1/barang/{kode_barang}
    public function show(Barang $barang)
    {
        return new BarangResource($barang);
    }

    // POST /api/v1/barang/{kode_barang}/catat-transaksi
    public function catatTransaksi(CatatTransaksiBarangRequest $request, Barang $barang)
    {
        $perubahan = $request->integer('perubahan');
        $jenisTransaksi = $request->input('jenis_transaksi');

        $barang = DB::transaction(function () use ($barang, $perubahan) {
            // Pessimistic lock baris barang agar aman dari race condition
            $terkini = Barang::whereKey($barang->id)->lockForUpdate()->first();

            if ($terkini->stok + $perubahan < 0) {
                throw new PersediaanSembakoDefisit($terkini->kode_barang, $terkini->stok, abs($perubahan));
            }

            $terkini->increment('stok', $perubahan);
            return $terkini->refresh();
        });

        // 1. Terbitkan Domain Event Asinkron ke RabbitMQ
        TransaksiSembakoTercatat::dispatch(
            $barang->kode_barang,
            $perubahan,
            $jenisTransaksi,
            $barang->stok,
            $barang->harga
        );

        // 2. Antrekan Task Sinkronisasi WhatsApp Bot via Laravel Queue
        SinkronkanKatalogWhatsApp::dispatch($barang->kode_barang, $barang->stok)
            ->onQueue('antrean-whatsapp');

        return new BarangResource($barang);
    }
}
