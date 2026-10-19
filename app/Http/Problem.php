<?php

namespace App\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pembuat respons galat dalam format Problem Details (RFC 9457).
 */
class Problem
{
    public static function response(
        Request $request,
        int $status,
        string $slug,
        string $title,
        string $detail,
        array $tambahan = []
    ): JsonResponse {
        return response()->json(array_merge([
            'type' => "https://warungibubeti.test/problem/{$slug}",
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => '/'.$request->path(),
        ], $tambahan), $status, ['Content-Type' => 'application/problem+json']);
    }
}
