<?php

namespace App\Http\Controllers;

use App\Enums\AksiAudit;
use App\Models\LogAudit;
use App\Models\TautanBukti;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Membuka tautan Google Drive dari pelapor untuk petugas (revisi 20-11): lewat tautan bertanda tangan yang berlaku
 * 10 menit, hak aksesnya sama dengan lampiran (TautanBuktiPolicy; Super Admin tidak pernah membuka tautan WBS), dan
 * setiap pembukaan tercatat di log audit seperti unduhan lampiran. Pengalihan dikirim tanpa referrer agar alamat panel
 * tidak terkirim ke Google.
 */
class BukaTautanBuktiController extends Controller
{
    public function __invoke(Request $request, TautanBukti $tautanBukti): RedirectResponse
    {
        Gate::authorize('view', $tautanBukti);

        abort_unless(TautanBukti::alamatSah($tautanBukti->url), 404);

        LogAudit::catat($request->user(), AksiAudit::MembukaTautanBukti, $tautanBukti);

        return redirect()->away($tautanBukti->url)->withHeaders([
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
