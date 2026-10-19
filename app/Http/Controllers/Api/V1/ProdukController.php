<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\StokDisesuaikan;
use App\Exceptions\StokTidakCukup;
use App\Http\Controllers\Controller;
use App\Http\Requests\PenyesuaianStokRequest;
use App\Http\Requests\TambahProdukRequest;
use App\Http\Resources\ProdukResource;
use App\Jobs\SinkronkanStokWhatsApp;
use App\Models\Produk;
use Illuminate\Support\Facades\DB;

class ProdukController extends Controller
{
    // GET /api/v1/produk
    public function index()
    {
        return ProdukResource::collection(Produk::orderBy('sku')->get());
    }

    // POST /api/v1/produk
    public function store(TambahProdukRequest $request)
    {
        $produk = Produk::create($request->validated() + ['stok' => 0]);

        return (new ProdukResource($produk))
            ->response()
            ->setStatusCode(201)
            ->header('Location', url("/api/v1/produk/{$produk->sku}"));
    }

    // GET /api/v1/produk/{sku}
    public function show(Produk $produk)
    {
        return new ProdukResource($produk);
    }

    // POST /api/v1/produk/{sku}/penyesuaian-stok
    public function sesuaikanStok(PenyesuaianStokRequest $request, Produk $produk)
    {
        $perubahan = $request->integer('perubahan');

        $produk = DB::transaction(function () use ($produk, $perubahan) {
            // Kunci baris agar dua transaksi bersamaan tidak saling menimpa.
            $terkini = Produk::whereKey($produk->id)->lockForUpdate()->first();

            if ($terkini->stok + $perubahan < 0) {
                throw new StokTidakCukup($terkini->sku, $terkini->stok, abs($perubahan));
            }

            $terkini->increment('stok', $perubahan);

            return $terkini->refresh();
        });

        // Transaksi sudah selesai: umumkan event ke RabbitMQ (Bab 3)
        StokDisesuaikan::dispatch($produk->sku, $perubahan, $request->input('alasan'), $produk->stok, $produk->harga);

        // Pekerjaan latar belakang sinkronisasi WhatsApp via Laravel Queue (Bab 8)
        SinkronkanStokWhatsApp::dispatch($produk->sku, $produk->stok)->onQueue('notifikasi-wa');

        return new ProdukResource($produk);
    }
}
