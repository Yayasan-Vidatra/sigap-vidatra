<?php

namespace App\Http\Controllers;

use App\Models\Laporan;
use App\Services\WaktuLokal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-05 Bukti Lapor (US-07). Kode lacak dibawa lewat flash sesi, bukan URL, dan halaman hanya tampil
 * sekali setelah pengiriman. PDF bukti lapor hanya bisa diunduh dari halaman ini selama
 * masa_unduh_pdf_menit setelah laporan dikirim; PDF dibuat saat diunduh dan tidak disimpan di server.
 */
class BuktiLaporController extends Controller
{
    public const KUNCI_SESI_PDF = 'bukti_lapor_pdf';

    /**
     * Mengizinkan satu sesi mengunduh PDF bukti lapor laporan ini untuk sementara.
     */
    public static function izinkanUnduhPdf(Request $request, Laporan $laporan): void
    {
        $request->session()->put(self::KUNCI_SESI_PDF, [
            'kode_lacak' => $laporan->kode_lacak,
            'jenis' => $laporan->jenis->value,
            'dikirim_pada' => $laporan->dibuat_pada->getTimestamp(),
            'kedaluwarsa' => now()->addMinutes((int) config('sigap.bukti_lapor.masa_unduh_pdf_menit'))->getTimestamp(),
        ]);
    }

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
            'masaUnduhPdfMenit' => (int) config('sigap.bukti_lapor.masa_unduh_pdf_menit'),
        ]);
    }

    /**
     * Isi PDF sengaja minimal: kode lacak, kanal, waktu kirim, alamat Lacak, dan petunjuk. Tanpa identitas
     * pelapor, kronologi, nama terlapor, atau PIN.
     */
    public function pdf(Request $request): HttpResponse
    {
        $data = $request->session()->get(self::KUNCI_SESI_PDF);

        if (! is_array($data) || ($data['kedaluwarsa'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget(self::KUNCI_SESI_PDF);

            abort(403, 'Bukti lapor hanya bisa diunduh dari halaman Bukti Lapor, paling lama '
                .config('sigap.bukti_lapor.masa_unduh_pdf_menit').' menit setelah laporan dikirim.');
        }

        return Pdf::loadView('pdf.bukti-lapor', [
            'kodeLacak' => $data['kode_lacak'],
            'wbs' => $data['jenis'] === 'wbs',
            'dikirimPada' => WaktuLokal::tampilkan(now()->setTimestamp($data['dikirim_pada'])),
            'tautanLacak' => route('lacak'),
        ])->download('bukti-lapor-sigap.pdf');
    }
}
