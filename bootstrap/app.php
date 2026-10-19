<?php

use App\Exceptions\PersediaanSembakoDefisit;
use App\Http\Problem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 422 — Validasi gagal
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return Problem::response(
                    $request,
                    422,
                    'validasi-gagal',
                    'Isi Permintaan Tidak Valid',
                    'Satu atau lebih field tidak memenuhi kontrak REST API.',
                    ['errors' => $e->errors()]
                );
            }
        });

        // 404 — Sumber daya tidak ditemukan
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return Problem::response(
                    $request,
                    404,
                    'tidak-ditemukan',
                    'Komoditas Tidak Ditemukan',
                    'Kode barang sembako atau endpoint yang diminta tidak tersedia di warung.'
                );
            }
        });

        // 409 — Persediaan sembako defisit
        $exceptions->render(function (PersediaanSembakoDefisit $e, Request $request) {
            return Problem::response(
                $request,
                409,
                'persediaan-defisit',
                'Persediaan Sembako Tidak Cukup',
                $e->getMessage(),
                ['stok_tersedia' => $e->stokTersedia]
            );
        });
    })->create();
