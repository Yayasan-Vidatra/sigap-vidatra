<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-01 Beranda (US-01, F-01). Kontak darurat diambil dari units.kontak_darurat yang diisi lewat
 * Pengaturan, bukan ditanam di kode (aturan proyek no. 7).
 */
class BerandaController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Beranda', [
            'kontakDarurat' => Unit::query()
                ->where('aktif', true)
                ->whereNotNull('kontak_darurat')
                ->orderBy('id')
                ->get(['nama', 'kontak_darurat'])
                ->map(fn (Unit $unit): array => ['unit' => $unit->nama, 'kontak' => $unit->kontak_darurat])
                ->all(),
        ]);
    }
}
