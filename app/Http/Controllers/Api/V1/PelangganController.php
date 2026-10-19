<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProsesKasbonPelanggan;
use App\Actions\ProsesPembayaranKasbon;
use App\Http\Controllers\Controller;
use App\Http\Requests\BayarKasbonRequest;
use App\Http\Requests\CatatKasbonRequest;
use App\Http\Requests\TambahPelangganRequest;
use App\Http\Resources\KasbonResource;
use App\Http\Resources\PelangganResource;
use App\Models\Pelanggan;
use Illuminate\Http\Request;

class PelangganController extends Controller
{
    // GET /api/v1/pelanggan
    public function index()
    {
        return PelangganResource::collection(Pelanggan::orderBy('nama')->get());
    }

    // POST /api/v1/pelanggan
    public function store(TambahPelangganRequest $request)
    {
        $pelanggan = Pelanggan::create($request->validated());

        return (new PelangganResource($pelanggan))
            ->response()
            ->setStatusCode(201)
            ->header('Location', url("/api/v1/pelanggan/{$pelanggan->id}"));
    }

    // GET /api/v1/pelanggan/{pelanggan}
    public function show(Pelanggan $pelanggan)
    {
        return new PelangganResource($pelanggan);
    }

    // POST /api/v1/pelanggan/{pelanggan}/kasbon
    public function catatKasbon(CatatKasbonRequest $request, Pelanggan $pelanggan, ProsesKasbonPelanggan $action)
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        $kasbon = $action->execute(
            $pelanggan,
            $request->integer('nominal'),
            $request->input('keterangan'),
            $idempotencyKey
        );

        return (new KasbonResource($kasbon))
            ->response()
            ->setStatusCode(201);
    }

    // POST /api/v1/pelanggan/{pelanggan}/bayar-kasbon
    public function bayarKasbon(BayarKasbonRequest $request, Pelanggan $pelanggan, ProsesPembayaranKasbon $action)
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        $pembayaran = $action->execute(
            $pelanggan,
            $request->integer('nominal'),
            $request->input('keterangan'),
            $idempotencyKey
        );

        return (new KasbonResource($pembayaran))
            ->response()
            ->setStatusCode(200);
    }
}
