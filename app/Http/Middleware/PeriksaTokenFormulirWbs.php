<?php

namespace App\Http\Middleware;

use App\Services\Wbs\TokenFormulirWbs;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint WBS yang mengubah data (unggah lampiran) wajib membawa token formulir yang sah di header
 * X-Token-Formulir.
 */
class PeriksaTokenFormulirWbs
{
    public function handle(Request $request, Closure $next): Response
    {
        if (TokenFormulirWbs::nonce($request->header('X-Token-Formulir')) === null) {
            return response()->json([
                'message' => 'Halaman formulir sudah terlalu lama terbuka atau laporan sudah terkirim. Muat ulang halaman, lalu unggah lagi.',
            ], 419);
        }

        return $next($request);
    }
}
