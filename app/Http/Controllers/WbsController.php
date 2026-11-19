<?php

namespace App\Http\Controllers;

use App\Enums\JenisBerkas;
use App\Enums\JenisLaporan;
use App\Enums\KedudukanTerlapor;
use App\Enums\SumberLaporan;
use App\Events\LaporanWbsPerluDitelaah;
use App\Http\Requests\StoreWbsRequest;
use App\Models\Kategori;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\Pengaturan;
use App\Models\Unit;
use App\Services\Lampiran\PelampirUnggahan;
use App\Services\WaktuLokal;
use App\Services\Wbs\PembuatLaporanWbs;
use App\Services\Wbs\TokenFormulirWbs;
use App\Services\Wbs\TokenUnduhPdfWbs;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kanal WBS untuk pelapor: H-03 Jaminan Kerahasiaan, H-04 Formulir WBS, dan H-05 Bukti Lapor WBS (US-05,
 * US-06, US-07; UC-02, UC-03; F-03, F-05). Semua route berjalan tanpa sesi, cookie, CSRF, dan IP (aturan
 * proyek no. 5). Kiriman formulir dijawab JSON berisi data bukti lapor; halaman menampilkannya sekali dari
 * memori browser, sehingga kode lacak tidak pernah ada di URL, sesi, maupun riwayat browser.
 */
class WbsController extends Controller
{
    public function jaminan(): Response
    {
        return Inertia::render('Wbs/Jaminan', [
            'berkasJaminan' => $this->pathBerkasJaminan() !== null ? route('wbs.jaminan.berkas') : null,
            'saluran' => [
                'surel' => Pengaturan::nilai(Pengaturan::WBS_SALURAN_SUREL),
                'telepon' => Pengaturan::nilai(Pengaturan::WBS_SALURAN_TELEPON),
                'surat' => Pengaturan::nilai(Pengaturan::WBS_SALURAN_SURAT),
            ],
        ]);
    }

    /**
     * Berkas PDF jaminan kerahasiaan untuk file viewer di H-03, dari disk privat (dikelola di H-19).
     */
    public function berkasJaminan(): StreamedResponse
    {
        $path = $this->pathBerkasJaminan() ?? abort(404);

        return Storage::disk('local')->response($path, 'jaminan-kerahasiaan-wbs.pdf', [
            'Content-Type' => 'application/pdf',
        ], 'inline');
    }

    /**
     * Formulir hanya terbuka lewat tombol "Saya mengerti, lanjut melapor" di H-03 (US-06); kunjungan langsung
     * diarahkan ke halaman jaminan kerahasiaan. Penandanya ada di URL karena jalur ini tanpa sesi dan cookie.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->boolean('mengerti')) {
            return redirect()->route('wbs');
        }

        return Inertia::render('Wbs/Formulir', [
            'token' => TokenFormulirWbs::terbitkan(),
            'masaTokenMenit' => TokenFormulirWbs::masaMenit(),
            'unit' => Unit::query()->where('aktif', true)->orderBy('id')->get(['id', 'nama']),
            'kategori' => Kategori::query()->where('jenis', JenisLaporan::Wbs)->orderBy('id')->get(['id', 'nama']),
            'kedudukan' => collect(KedudukanTerlapor::cases())
                ->map(fn (KedudukanTerlapor $kedudukan): array => ['value' => $kedudukan->value, 'label' => $kedudukan->getLabel()])
                ->all(),
            'kontakDarurat' => Unit::query()
                ->where('aktif', true)
                ->whereNotNull('kontak_darurat')
                ->where('kontak_darurat', '!=', '')
                ->orderBy('id')
                ->get(['nama', 'kontak_darurat'])
                ->map(fn (Unit $unit): array => ['unit' => $unit->nama, 'kontak' => $unit->kontak_darurat])
                ->all(),
            'kontakDaruratCadangan' => Unit::KONTAK_DARURAT_CADANGAN,
            'targetTelaah' => (int) config('sigap.wbs.telaah_hari_kerja'),
            'kataKunciKekerasan' => config('sigap.wbs.kata_kunci_kekerasan'),
            'maksTerlapor' => (int) config('sigap.wbs.maks_terlapor'),
            'aturanLampiran' => self::aturanLampiran(),
        ]);
    }

    /**
     * Token formulir baru untuk halaman formulir yang masih terbuka (diminta berkala oleh browser).
     */
    public function token(): JsonResponse
    {
        return response()->json(['token' => TokenFormulirWbs::terbitkan()]);
    }

    /**
     * Menyimpan laporan WBS berstatus Diterima. Isian sensitif terenkripsi lewat cast model; lampiran dienkripsi
     * dan dibersihkan metadatanya lewat queue. Perutean akses menurut matriks tersimpan di kedudukan_terlapor.
     * Batas telaah 5 hari kerja dihitung sejak diterima. Tidak ada surel ke pelapor; penelaah menurut matriks diberi
     * tahu tanpa isi laporan (LaporanWbsPerluDitelaah).
     */
    public function store(StoreWbsRequest $request, PelampirUnggahan $pelampir, PembuatLaporanWbs $pembuat): JsonResponse
    {
        $data = $request->validated();

        $laporan = DB::transaction(function () use ($data, $pelampir, $pembuat): Laporan {
            $laporan = $pembuat->buat($data, SumberLaporan::Laman, $data['pin']);
            $pelampir->lampirkan($laporan, array_values($data['lampiran'] ?? []));

            return $laporan;
        });

        return response()->json([
            'kodeLacak' => $laporan->kode_lacak,
            'tokenPdf' => TokenUnduhPdfWbs::terbitkan($laporan),
            'masaUnduhPdfMenit' => TokenUnduhPdfWbs::masaMenit(),
            'lampiran' => $laporan->lampiran()->get()->map(fn (Lampiran $lampiran): string => $lampiran->nama_asli)->all(),
        ], 201);
    }

    /**
     * PDF bukti lapor WBS lewat token sekali pakai (dikirim di badan POST, bukan URL). Isi PDF sama aturannya
     * dengan pengaduan: kode lacak, kanal, waktu kirim, alamat Lacak, dan pengingat bahwa PIN tidak tercantum.
     */
    public function pdf(Request $request): HttpResponse|JsonResponse
    {
        $data = TokenUnduhPdfWbs::pakai($request->input('token'));

        if ($data === null) {
            return response()->json([
                'message' => 'PDF bukti lapor sudah diunduh atau masa unduhnya ('.TokenUnduhPdfWbs::masaMenit().' menit) sudah lewat. Catat kode lacak di layar.',
            ], 410);
        }

        return Pdf::loadView('pdf.bukti-lapor', [
            'kodeLacak' => $data['kode_lacak'],
            'wbs' => true,
            'dikirimPada' => WaktuLokal::tampilkan(now()->setTimestamp($data['dikirim_pada'])),
            'tautanLacak' => route('lacak'),
        ])->download('bukti-lapor-sigap.pdf');
    }

    /**
     * @return array{maks_berkas: int, maks_total_mb: int, jenis: list<array{label: string, maks_mb: int}>}
     */
    public static function aturanLampiran(): array
    {
        return [
            'maks_berkas' => (int) config('sigap.lampiran.maks_berkas'),
            'maks_total_mb' => (int) config('sigap.lampiran.maks_total_mb'),
            'jenis' => collect(JenisBerkas::cases())
                ->filter(fn (JenisBerkas $jenis): bool => $jenis->bolehUntuk(JenisLaporan::Wbs))
                ->map(fn (JenisBerkas $jenis): array => ['label' => $jenis->getLabel(), 'maks_mb' => intdiv($jenis->maksByte(), 1024 * 1024)])
                ->values()
                ->all(),
        ];
    }

    private function pathBerkasJaminan(): ?string
    {
        $path = Pengaturan::nilai(Pengaturan::WBS_BERKAS_JAMINAN);

        return $path !== null && str_starts_with($path, 'pengaturan/') && ! str_contains($path, '..')
            && Storage::disk('local')->exists($path) ? $path : null;
    }

    private static function teksAtauNull(mixed $nilai): ?string
    {
        return is_string($nilai) && trim($nilai) !== '' ? trim($nilai) : null;
    }
}
