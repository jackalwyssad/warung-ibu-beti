<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProsesTransaksiKasir;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatatTransaksiBarangRequest;
use App\Http\Requests\TambahBarangRequest;
use App\Http\Resources\BarangResource;
use App\Models\Barang;
use Illuminate\Http\Request;

class BarangController extends Controller
{
    // GET /api/v1/barang?kategori=sembako
    public function index(Request $request)
    {
        $query = Barang::query();

        if ($request->has('kategori')) {
            $query->where('kategori', $request->query('kategori'));
        }

        $barang = $query->orderBy('kode_barang')->get();
        return BarangResource::collection($barang);
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
    public function catatTransaksi(CatatTransaksiBarangRequest $request, Barang $barang, ProsesTransaksiKasir $action)
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        $updated = $action->execute(
            $barang,
            $request->integer('perubahan'),
            $request->input('jenis_transaksi'),
            $idempotencyKey
        );

        return new BarangResource($updated);
    }
}
