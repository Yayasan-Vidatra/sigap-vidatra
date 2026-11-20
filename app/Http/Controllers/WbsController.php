<?php

namespace App\Http\Controllers;

use App\Enums\JenisBerkas;
use App\Enums\JenisLaporan;
use App\Enums\KedudukanTerlapor;
use App\Enums\SumberLaporan;
use App\Http\Requests\StoreWbsRequest;
use App\Models\Kategori;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\Pengaturan;
use App\Models\TautanBukti;
use App\Models\Unit;
use App\Rules\AltchaSah;
use App\Services\Lampiran\PelampirUnggahan;
use App\Services\Lampiran\PenyimpanTautanBukti;
use App\Services\WaktuLokal;
use App\Services\Wbs\KodeAksesWbs;
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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kanal WBS internal untuk pelapor: kode akses, H-03 Jaminan Kerahasiaan, H-04 Formulir WBS, dan H-05 Bukti Lapor
 * WBS (US-05, US-06, US-07; UC-02, UC-03; F-01, F-03, F-05; revisi 20-11 di log deviasi). Semua route berjalan
 * tanpa sesi, cookie, CSRF, dan IP (aturan proyek no. 5), dan diberi noindex (LindungiJalurWbs).
 *
 * Alur dalam satu halaman: /wbs meminta kode akses (wajib ALTCHA). Kode yang benar menghasilkan token akses
 * bertanda tangan; halaman lalu menampilkan jaminan kerahasiaan, dan "Saya mengerti" mengambil data formulir
 * dengan token itu. Token hanya ada di memori halaman, tidak di URL, sesi, atau riwayat browser. Kiriman formulir
 * dijawab JSON berisi data bukti lapor yang ditampilkan sekali.
 */
class WbsController extends Controller
{
    public const PESAN_KODE_SALAH = 'Kode akses tidak sesuai.';

    public const PESAN_KANAL_TUTUP = 'Kanal belum dibuka, hubungi Tim WBS.';

    public const PESAN_TOKEN_AKSES = 'Masa akses formulir sudah habis. Masukkan kode akses lagi.';

    public function akses(): Response
    {
        return Inertia::render('Wbs/Akses', [
            'dibuka' => KodeAksesWbs::dibuka(),
            'petunjuk' => KodeAksesWbs::petunjuk(),
            'pesanTutup' => self::PESAN_KANAL_TUTUP,
        ]);
    }

    /**
     * Memeriksa kode akses. Setiap percobaan wajib ALTCHA (pengganti pembatasan per IP) dan dibatasi penghitung
     * global (throttle:kode-akses-wbs). Kode yang salah selalu mendapat pesan umum yang sama.
     */
    public function periksaKode(Request $request): JsonResponse
    {
        if (! KodeAksesWbs::dibuka()) {
            return response()->json(['message' => self::PESAN_KANAL_TUTUP], 423);
        }

        $data = $request->validate([
            'kode' => ['required', 'string', 'max:100'],
            'altcha' => ['bail', 'required', 'string', new AltchaSah],
        ], [
            'kode.required' => 'Masukkan kode akses.',
            'kode.max' => self::PESAN_KODE_SALAH,
            'altcha.required' => 'Selesaikan verifikasi "Saya bukan robot" terlebih dahulu.',
        ]);

        if (! AltchaSah::tandaiDipakai((string) $data['altcha'])) {
            throw ValidationException::withMessages(['altcha' => 'Verifikasi "Saya bukan robot" sudah dipakai. Silakan ulangi.']);
        }

        if (! KodeAksesWbs::cocok($data['kode'])) {
            throw ValidationException::withMessages(['kode' => self::PESAN_KODE_SALAH]);
        }

        return response()->json([
            'tokenAkses' => KodeAksesWbs::terbitkanToken(),
            'jaminan' => [
                'berkasJaminan' => $this->pathBerkasJaminan() !== null ? route('wbs.jaminan.berkas') : null,
                'saluran' => [
                    'surel' => Pengaturan::nilai(Pengaturan::WBS_SALURAN_SUREL),
                    'telepon' => Pengaturan::nilai(Pengaturan::WBS_SALURAN_TELEPON),
                    'surat' => Pengaturan::nilai(Pengaturan::WBS_SALURAN_SURAT),
                ],
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
     * Alamat formulir lama (/wbs/lapor) tidak lagi membuka formulir; pelapor selalu mulai dari kode akses.
     */
    public function create(): RedirectResponse
    {
        return redirect()->route('wbs');
    }

    /**
     * Data Formulir WBS setelah "Saya mengerti, lanjut melapor" (US-06), hanya dengan token akses yang sah.
     */
    public function bukaFormulir(Request $request): JsonResponse
    {
        if (! KodeAksesWbs::tokenSah($request->input('token_akses'))) {
            return response()->json(['message' => self::PESAN_TOKEN_AKSES], 419);
        }

        return response()->json([
            'token' => TokenFormulirWbs::terbitkan(),
            'tokenAkses' => KodeAksesWbs::terbitkanToken(),
            'masaTokenMenit' => TokenFormulirWbs::masaMenit(),
            'unit' => Unit::query()->where('aktif', true)->orderBy('id')->get(['id', 'nama']),
            'kategori' => Kategori::query()->where('jenis', JenisLaporan::Wbs)->orderBy('id')->get(['id', 'nama']),
            'kedudukan' => collect(KedudukanTerlapor::pilihanFormulir())
                ->map(fn (KedudukanTerlapor $kedudukan): array => ['value' => $kedudukan->value, 'label' => $kedudukan->getLabel()])
                ->all(),
            'kontakDarurat' => Unit::query()
                ->untukPengaduan()
                ->where('aktif', true)
                ->whereNotNull('kontak_darurat')
                ->where('kontak_darurat', '!=', '')
                ->orderBy('id')
                ->get(['nama', 'kontak_darurat'])
                ->map(fn (Unit $unit): array => ['unit' => $unit->nama, 'kontak' => $unit->kontak_darurat])
                ->all(),
            'jumlahUnitSekolah' => Unit::query()->untukPengaduan()->where('aktif', true)->count(),
            'kontakDaruratCadangan' => Unit::KONTAK_DARURAT_CADANGAN,
            'targetTelaah' => (int) config('sigap.wbs.telaah_hari_kerja'),
            'kataKunciKekerasan' => config('sigap.wbs.kata_kunci_kekerasan'),
            'maksTerlapor' => (int) config('sigap.wbs.maks_terlapor'),
            'aturanLampiran' => self::aturanLampiran(),
            'maksTautan' => TautanBukti::MAKS,
        ]);
    }

    /**
     * Token formulir baru untuk halaman formulir yang masih terbuka (diminta berkala oleh browser), hanya bila token
     * akses masih sah. Token akses ikut diperbarui agar formulir yang lama diisi tidak terputus.
     */
    public function token(Request $request): JsonResponse
    {
        if (! KodeAksesWbs::tokenSah($request->header('X-Token-Akses'))) {
            return response()->json(['message' => self::PESAN_TOKEN_AKSES], 419);
        }

        return response()->json(['token' => TokenFormulirWbs::terbitkan(), 'tokenAkses' => KodeAksesWbs::terbitkanToken()]);
    }

    /**
     * Menyimpan laporan WBS berstatus Diterima. Laporan selalu bernama (revisi 20-11). Isian sensitif terenkripsi
     * lewat cast model; lampiran dienkripsi dan dibersihkan metadatanya lewat queue; tautan Google Drive disimpan
     * terenkripsi. Perutean akses menurut matriks tersimpan di kedudukan_terlapor. Tidak ada surel ke pelapor;
     * penelaah menurut matriks diberi tahu tanpa isi laporan (LaporanWbsPerluDitelaah).
     */
    public function store(StoreWbsRequest $request, PelampirUnggahan $pelampir, PembuatLaporanWbs $pembuat, PenyimpanTautanBukti $tautan): JsonResponse
    {
        $data = [...$request->validated(), 'mode' => 'bernama'];

        $laporan = DB::transaction(function () use ($data, $pelampir, $pembuat, $tautan): Laporan {
            $laporan = $pembuat->buat($data, SumberLaporan::Laman, $data['pin']);
            $pelampir->lampirkan($laporan, array_values($data['lampiran'] ?? []));
            $tautan->simpan($laporan, array_values($data['tautan'] ?? []));

            return $laporan;
        });

        return response()->json([
            'kodeLacak' => $laporan->kode_lacak,
            'tokenPdf' => TokenUnduhPdfWbs::terbitkan($laporan),
            'masaUnduhPdfMenit' => TokenUnduhPdfWbs::masaMenit(),
            'lampiran' => $laporan->lampiran()->get()->map(fn (Lampiran $lampiran): string => $lampiran->nama_asli)->all(),
            'jumlahTautan' => $laporan->tautanBukti()->count(),
        ], 201);
    }

    /**
     * PDF bukti lapor WBS lewat token sekali pakai (dikirim di badan POST, bukan URL). Isi PDF: kode lacak, kanal,
     * waktu kirim, alamat Lacak WBS, dan pengingat bahwa PIN tidak tercantum.
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
            'tautanLacak' => route('wbs.lacak'),
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
}
