<?php

namespace App\Http\Controllers;

use App\Enums\AksiAudit;
use App\Enums\StatusPindai;
use App\Models\Lampiran;
use App\Models\LogAudit;
use App\Services\Lampiran\PenyimpananLampiran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan lampiran untuk petugas lewat tautan bertanda tangan yang berlaku 10 menit (Rancangan r1
 * Bagian 8). Setiap unduhan tercatat di log audit (US-27). Berkas selalu dikirim sebagai attachment
 * dengan nosniff agar tidak dijalankan di browser.
 */
class UnduhLampiranController extends Controller
{
    public function __invoke(Request $request, Lampiran $lampiran, PenyimpananLampiran $penyimpanan): StreamedResponse
    {
        Gate::authorize('view', $lampiran);

        abort_if($lampiran->status_pindai === StatusPindai::Karantina, 403, 'Lampiran ini dikarantina karena terdeteksi berbahaya.');

        LogAudit::catat($request->user(), AksiAudit::MembukaLampiran, $lampiran);

        $terenkripsi = $lampiran->terenkripsi();

        return response()->streamDownload(function () use ($penyimpanan, $lampiran, $terenkripsi): void {
            $keluaran = fopen('php://output', 'wb');
            $penyimpanan->salinIsiKe($lampiran->path_acak, $terenkripsi, $keluaran);
            fclose($keluaran);
        }, $lampiran->nama_asli, [
            'Content-Type' => $lampiran->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
