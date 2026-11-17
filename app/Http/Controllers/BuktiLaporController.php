<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-05 Bukti Lapor, versi sementara (US-07 lengkap di Hari 5). Kode lacak dibawa lewat flash sesi,
 * bukan URL, dan hanya tampil sekali setelah pengiriman.
 */
class BuktiLaporController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $bukti = $request->session()->get('bukti_lapor');

        if (! is_array($bukti) || ! isset($bukti['kode_lacak'])) {
            return redirect()->route('beranda');
        }

        return Inertia::render('BuktiLapor', [
            'kodeLacak' => $bukti['kode_lacak'],
            'judul' => $bukti['judul'] ?? null,
            'lampiran' => $bukti['lampiran'] ?? [],
        ]);
    }
}
