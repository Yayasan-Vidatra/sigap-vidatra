<?php

namespace App\Http\Controllers;

use App\Services\Pemantauan\PemeriksaKesehatan;
use Illuminate\Http\JsonResponse;

/**
 * GET /kesehatan untuk pemantau uptime dan deploy/pantau/cek-kesehatan.sh (NF-04): 200 bila semua pemeriksaan
 * lolos, 503 bila ada yang gagal. Tanpa sesi dan cookie; isinya hanya nama pemeriksaan dan lolos/gagal.
 */
class KesehatanController extends Controller
{
    public function __invoke(PemeriksaKesehatan $pemeriksa): JsonResponse
    {
        $cek = $pemeriksa->periksa();
        $sehat = ! in_array(false, $cek, true);

        return response()
            ->json(['status' => $sehat ? 'ok' : 'gagal', 'cek' => $cek], $sehat ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
