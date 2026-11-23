<?php

namespace App\Http\Controllers;

use App\Enums\AksiAudit;
use App\Enums\JenisLaporan;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Rules\AltchaSah;
use App\Services\Lacak\PembatasPercobaanLacakWbs;
use App\Services\Lacak\TokenLacakWbs;
use App\Services\Pengaduan\PesanLaporan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lacak WBS (/wbs/lacak; US-08, US-09, UC-04, UC-05; revisi 20-11 di log deviasi): dipisah dari Lacak Pengaduan dan
 * berjalan tanpa sesi, cookie, CSRF, dan IP seperti seluruh jalur /wbs* (aturan proyek no. 5).
 *
 * Kode lacak dan PIN dikirim lewat permintaan HTTP biasa; jawaban berisi data detail dan token Lacak bertanda tangan
 * (TokenLacakWbs) yang hanya disimpan di memori halaman. Kode yang tidak ada, PIN salah, dan PIN kosong mendapat
 * pesan yang sama; PIN tetap dicocokkan dengan hash tiruan bila kode tidak ada. Pembatasan percobaan memakai
 * penghitung per kode dan global tanpa IP (PembatasPercobaanLacakWbs); kebutuhan CAPTCHA dan jeda dikembalikan di
 * jawaban, bukan di sesi.
 */
class LacakWbsController extends Controller
{
    public const PESAN_GAGAL = 'Kode lacak atau PIN tidak cocok. Periksa kembali lalu coba lagi.';

    public const PESAN_SESI_BERAKHIR = 'Halaman Lacak ditutup karena 15 menit tidak aktif. Masukkan kode lacak dan PIN lagi.';

    private static ?string $hashTiruan = null;

    public function create(): Response
    {
        return Inertia::render('Wbs/Lacak', [
            'masaSesiMenit' => TokenLacakWbs::masaMenit(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $kode = strtoupper(trim((string) $request->input('kode', '')));

        if (($jeda = PembatasPercobaanLacakWbs::sisaJeda($kode)) > 0) {
            return $this->gagal($kode, "Terlalu banyak percobaan. Coba lagi dalam {$jeda} detik.");
        }

        $perluCaptcha = PembatasPercobaanLacakWbs::perluCaptcha($kode);

        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30'],
            'pin' => ['nullable', 'string', 'max:20'],
            'altcha' => $perluCaptcha ? ['bail', 'required', 'string', new AltchaSah] : ['nullable'],
        ], [
            'kode.required' => 'Masukkan kode lacak.',
            'kode.max' => self::PESAN_GAGAL,
            'pin.max' => self::PESAN_GAGAL,
            'altcha.required' => 'Selesaikan verifikasi "Saya bukan robot" terlebih dahulu.',
        ]);

        if ($perluCaptcha && ! AltchaSah::tandaiDipakai((string) $data['altcha'])) {
            return $this->gagal($kode, 'Verifikasi "Saya bukan robot" sudah dipakai. Silakan ulangi.', 'altcha');
        }

        $laporan = Laporan::query()->where('kode_lacak', $kode)->where('jenis', JenisLaporan::Wbs->value)->first();
        $hash = $laporan?->getAttributes()['pin_hash'] ?? null;
        $pin = is_string($data['pin'] ?? null) ? $data['pin'] : '';
        $cocok = Hash::check($pin, is_string($hash) ? $hash : self::hashTiruan()) && $laporan !== null && $hash !== null && $pin !== '';

        if (! $cocok) {
            PembatasPercobaanLacakWbs::catatGagal($kode);

            return $this->gagal($kode, self::PESAN_GAGAL);
        }

        PembatasPercobaanLacakWbs::bersihkan($kode);
        LogAudit::catat(null, AksiAudit::PelaporMembukaLaporan, $laporan);

        return response()->json(LacakController::dataDetail($laporan));
    }

    /**
     * Data terbaru laporan untuk halaman yang masih terbuka (memperpanjang masa token).
     */
    public function show(Request $request): JsonResponse
    {
        $laporan = TokenLacakWbs::laporan($request->input('token'));

        return $laporan === null
            ? response()->json(['message' => self::PESAN_SESI_BERAKHIR], 419)
            : response()->json(LacakController::dataDetail($laporan));
    }

    /**
     * Pesan lanjutan pelapor (UC-05) beserta lampiran dan tautan Google Drive. Aturannya sama dengan Lacak Pengaduan.
     */
    public function kirimPesan(Request $request, PesanLaporan $pesan): JsonResponse
    {
        $laporan = TokenLacakWbs::laporan($request->input('token'));

        if ($laporan === null) {
            return response()->json(['message' => self::PESAN_SESI_BERAKHIR], 419);
        }

        $data = $request->validate(LacakController::aturanPesan($request, $laporan), LacakController::pesanValidasiPesan());

        try {
            $pesan->kirimDariPelapor($laporan, $data['isi'], array_values($data['lampiran'] ?? []), array_values($data['tautan'] ?? []));
        } catch (AksiPengaduanTidakSah $galat) {
            throw ValidationException::withMessages(['isi' => $galat->getMessage()]);
        }

        return response()->json([...LacakController::dataDetail($laporan->fresh() ?? $laporan), 'berhasil' => 'Pesan terkirim.']);
    }

    private function gagal(string $kode, string $pesan, string $kolom = 'kode'): JsonResponse
    {
        $errors = [$kolom => [$pesan]];

        // Kebutuhan CAPTCHA dan jeda ikut di "errors" agar terbaca formulir tanpa sesi (useHttp hanya meneruskan errors).
        if (PembatasPercobaanLacakWbs::perluCaptcha($kode)) {
            $errors['perlu_captcha'] = ['ya'];
        }

        if (($jeda = PembatasPercobaanLacakWbs::sisaJeda($kode)) > 0) {
            $errors['sisa_jeda'] = [(string) $jeda];
        }

        return response()->json(['message' => $pesan, 'errors' => $errors], 422);
    }

    private static function hashTiruan(): string
    {
        return self::$hashTiruan ??= Hash::make(Str::random(16));
    }
}
