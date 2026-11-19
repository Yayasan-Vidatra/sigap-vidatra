<?php

namespace App\Http\Controllers;

use App\Enums\AksiAudit;
use App\Enums\JenisBerkas;
use App\Enums\JenisLaporan;
use App\Enums\PengirimPesan;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\Pesan;
use App\Models\RiwayatStatus;
use App\Rules\AltchaSah;
use App\Rules\UnggahanLampiranSiap;
use App\Services\Lacak\IzinLacak;
use App\Services\Lacak\PembatasPercobaanLacak;
use App\Services\Lacak\PembatasPercobaanLacakWbs;
use App\Services\Pengaduan\KonfirmasiPelapor;
use App\Services\Pengaduan\PesanLaporan;
use App\Services\WaktuLokal;
use App\Services\Wbs\TokenFormulirWbs;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-06 Lacak Laporan dan H-07 Detail Laporan untuk pelapor pengaduan (US-08, US-09, US-13; UC-04, UC-05,
 * UC-06; F-06). Kode lacak yang tidak ada dan yang salah mendapat pesan yang sama. Setelah 5 kali gagal,
 * percobaan berikutnya wajib CAPTCHA dan menunggu jeda. Detail di /lacak/laporan hanya terbuka dengan izin
 * di sesi (IzinLacak), yang berakhir setelah 15 menit tidak aktif. Kode lacak tidak pernah ada di URL, agar
 * tidak tersimpan di riwayat browser dan log server.
 *
 * Kode WBS dibuka bersama PIN (Hari 9). Pembatasan percobaannya memakai penghitung per kode dan global tanpa
 * IP; sesi Lacak sendiri tidak menyimpan IP maupun user agent (App\Services\Sesi\PenanganSesiTanpaJejak).
 * Detail WBS menampilkan status, riwayat (catatan bertanda tampil_ke_pelapor saja), dan pesan dengan Tim WBS.
 */
class LacakController extends Controller
{
    public const PESAN_GAGAL = 'Kode lacak atau PIN tidak cocok. Periksa kembali lalu coba lagi.';

    private const KUNCI_CAPTCHA_WBS = 'lacak_wbs_perlu_captcha';

    private const KUNCI_JEDA_WBS = 'lacak_wbs_jeda_sampai';

    private static ?string $hashTiruan = null;

    public const PESAN_SESI_BERAKHIR = 'Sesi Lacak berakhir karena 15 menit tidak aktif atau belum dibuka. Masukkan kode lacak lagi.';

    public function create(Request $request): Response
    {
        $jedaWbs = max(0, (int) $request->session()->get(self::KUNCI_JEDA_WBS, 0) - now()->getTimestamp());

        return Inertia::render('Lacak/Masuk', [
            'perluCaptcha' => PembatasPercobaanLacak::perluCaptcha($request) || $request->session()->get(self::KUNCI_CAPTCHA_WBS) === true,
            'sisaJeda' => max(PembatasPercobaanLacak::sisaJeda($request), $jedaWbs),
            'pesan' => $request->session()->get('lacak_pesan'),
        ]);
    }

    /**
     * Kode berawalan WBS- diperiksa bersama PIN dengan pembatas tanpa IP; kode lain sebagai pengaduan.
     */
    public function store(Request $request): RedirectResponse
    {
        $kode = strtoupper(trim((string) $request->input('kode', '')));

        return str_starts_with($kode, 'WBS-') ? $this->masukWbs($request, $kode) : $this->masukPengaduan($request);
    }

    private function masukPengaduan(Request $request): RedirectResponse
    {
        if (($jeda = PembatasPercobaanLacak::sisaJeda($request)) > 0) {
            throw ValidationException::withMessages([
                'kode' => "Terlalu banyak percobaan. Coba lagi dalam {$jeda} detik.",
            ]);
        }

        $perluCaptcha = PembatasPercobaanLacak::perluCaptcha($request);
        $data = $this->validasiMasuk($request, $perluCaptcha);

        $kode = strtoupper(trim($data['kode']));
        $laporan = Laporan::query()->where('kode_lacak', $kode)->first();

        if ($laporan === null || $laporan->jenis !== JenisLaporan::Pengaduan) {
            PembatasPercobaanLacak::catatGagal($request);

            throw ValidationException::withMessages(['kode' => self::PESAN_GAGAL]);
        }

        PembatasPercobaanLacak::bersihkan($request);

        return $this->bukaIzin($request, $laporan);
    }

    /**
     * Kode WBS + PIN (UC-04). Penghitung gagal per kode dan global, tanpa IP (PembatasPercobaanLacakWbs). Kode yang
     * tidak ada, PIN salah, dan PIN kosong mendapat pesan yang sama; PIN tetap dicocokkan dengan hash tiruan bila
     * kode tidak ada, agar lama pemeriksaan tidak membedakan keduanya. Kebutuhan CAPTCHA dan jeda dibawa ke
     * halaman H-06 lewat flash sesi, karena halaman itu belum tahu kode yang akan dimasukkan.
     */
    private function masukWbs(Request $request, string $kode): RedirectResponse
    {
        if (($jeda = PembatasPercobaanLacakWbs::sisaJeda($kode)) > 0) {
            $this->bawaPembatasWbs($request, $kode);

            throw ValidationException::withMessages([
                'kode' => "Terlalu banyak percobaan. Coba lagi dalam {$jeda} detik.",
            ]);
        }

        $perluCaptcha = PembatasPercobaanLacakWbs::perluCaptcha($kode);

        if ($perluCaptcha) {
            $this->bawaPembatasWbs($request, $kode);
        }

        $data = $this->validasiMasuk($request, $perluCaptcha);
        $laporan = Laporan::query()->where('kode_lacak', $kode)->where('jenis', JenisLaporan::Wbs->value)->first();
        $hash = $laporan?->getAttributes()['pin_hash'] ?? null;
        $pin = is_string($data['pin'] ?? null) ? $data['pin'] : '';
        $cocok = Hash::check($pin, is_string($hash) ? $hash : self::hashTiruan()) && $laporan !== null && $hash !== null && $pin !== '';

        if (! $cocok) {
            PembatasPercobaanLacakWbs::catatGagal($kode);
            $this->bawaPembatasWbs($request, $kode);

            throw ValidationException::withMessages(['kode' => self::PESAN_GAGAL]);
        }

        PembatasPercobaanLacakWbs::bersihkan($kode);

        return $this->bukaIzin($request, $laporan);
    }

    /**
     * @return array{kode: string, pin?: string|null, altcha?: string|null}
     */
    private function validasiMasuk(Request $request, bool $perluCaptcha): array
    {
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
            throw ValidationException::withMessages(['altcha' => 'Verifikasi "Saya bukan robot" sudah dipakai. Silakan ulangi.']);
        }

        return $data;
    }

    private function bawaPembatasWbs(Request $request, string $kode): void
    {
        if (PembatasPercobaanLacakWbs::perluCaptcha($kode)) {
            $request->session()->flash(self::KUNCI_CAPTCHA_WBS, true);
        }

        if (($jeda = PembatasPercobaanLacakWbs::sisaJeda($kode)) > 0) {
            $request->session()->flash(self::KUNCI_JEDA_WBS, now()->getTimestamp() + $jeda);
        }
    }

    private static function hashTiruan(): string
    {
        return self::$hashTiruan ??= Hash::make(Str::random(16));
    }

    private function bukaIzin(Request $request, Laporan $laporan): RedirectResponse
    {
        IzinLacak::berikan($request, $laporan);
        LogAudit::catat(null, AksiAudit::PelaporMembukaLaporan, $laporan);

        return redirect()->route('lacak.show');
    }

    public function show(Request $request): Response|RedirectResponse
    {
        return $this->denganIzin($request, fn (Laporan $laporan): Response => Inertia::render('Lacak/Detail', $this->dataDetail($laporan)));
    }

    public function kirimPesan(Request $request, PesanLaporan $pesan): RedirectResponse
    {
        return $this->denganIzin($request, function (Laporan $laporan) use ($request, $pesan): RedirectResponse {
            $data = $request->validate([
                'isi' => ['required', 'string', 'max:'.PesanLaporan::MAKS_ISI],
                'lampiran' => ['nullable', 'array', 'max:'.config('sigap.lampiran.maks_berkas'), new UnggahanLampiranSiap($laporan->jenis)],
                'lampiran.*' => ['string', 'distinct'],
            ], [
                'isi.required' => 'Tulis pesan terlebih dahulu.',
                'isi.max' => 'Pesan paling banyak :max karakter.',
                'lampiran.max' => 'Lampiran paling banyak :max berkas.',
            ]);

            return $this->jalankan(fn () => $pesan->kirimDariPelapor($laporan, $data['isi'], array_values($data['lampiran'] ?? [])), 'Pesan terkirim.', 'isi');
        });
    }

    public function konfirmasi(Request $request, KonfirmasiPelapor $konfirmasi): RedirectResponse
    {
        return $this->denganIzin($request, fn (Laporan $laporan): RedirectResponse => $this->jalankan(
            fn () => $konfirmasi->konfirmasi($laporan),
            'Terima kasih. Pengaduan Anda dinyatakan selesai.',
            'tanggapan',
        ));
    }

    public function tolak(Request $request, KonfirmasiPelapor $konfirmasi): RedirectResponse
    {
        return $this->denganIzin($request, function (Laporan $laporan) use ($request, $konfirmasi): RedirectResponse {
            $data = $request->validate(
                ['alasan' => ['required', 'string', 'max:1000']],
                ['alasan.required' => 'Tuliskan alasan Anda menolak hasilnya.', 'alasan.max' => 'Alasan paling banyak :max karakter.'],
            );

            return $this->jalankan(fn () => $konfirmasi->tolakHasil($laporan, $data['alasan']), 'Tanggapan Anda terkirim. Pengaduan kembali ditangani.', 'alasan');
        });
    }

    public function nilai(Request $request, KonfirmasiPelapor $konfirmasi): RedirectResponse
    {
        return $this->denganIzin($request, function (Laporan $laporan) use ($request, $konfirmasi): RedirectResponse {
            $data = $request->validate(
                ['nilai' => ['required', 'integer', 'between:1,5']],
                ['nilai.required' => 'Pilih penilaian 1 sampai 5.', 'nilai.between' => 'Pilih penilaian 1 sampai 5.', 'nilai.integer' => 'Pilih penilaian 1 sampai 5.'],
            );

            return $this->jalankan(fn () => $konfirmasi->beriPenilaian($laporan, (int) $data['nilai']), 'Terima kasih atas penilaian Anda.', 'nilai');
        });
    }

    public function keluar(Request $request): RedirectResponse
    {
        IzinLacak::cabut($request);

        return redirect()->route('lacak');
    }

    /**
     * @template T
     *
     * @param  Closure(Laporan): T  $lanjut
     * @return T|RedirectResponse
     */
    private function denganIzin(Request $request, Closure $lanjut): mixed
    {
        $laporan = IzinLacak::laporan($request);

        if ($laporan === null) {
            return redirect()->route('lacak')->with('lacak_pesan', self::PESAN_SESI_BERAKHIR);
        }

        return $lanjut($laporan);
    }

    /**
     * @param  Closure(): mixed  $aksi
     */
    private function jalankan(Closure $aksi, string $pesanBerhasil, string $kolomGalat): RedirectResponse
    {
        try {
            $aksi();
        } catch (AksiPengaduanTidakSah $galat) {
            return back()->withErrors([$kolomGalat => $galat->getMessage()]);
        }

        return back()->with('lacak_berhasil', $pesanBerhasil);
    }

    /**
     * Data yang terlihat pelapor. Tanpa identitas, nama petugas, catatan internal, atau isi tindak lanjut unit;
     * catatan riwayat hanya yang bertanda tampil_ke_pelapor.
     *
     * @return array<string, mixed>
     */
    private function dataDetail(Laporan $laporan): array
    {
        $laporan->load(['unit', 'kategori', 'riwayatStatus', 'pesanUntukPelapor.lampiran']);
        $wbs = $laporan->isWbs();
        // Laporan WBS pindahan (UC-09 1a): pesan yang ikut dipindah dari pengaduan dikirim sebelum laporan WBS ini
        // dibuat, sehingga pengirimnya petugas Pengaduan, bukan Tim WBS. Nama petugas tetap tidak ditampilkan.
        $pindahanPada = $wbs && $laporan->sumber === SumberLaporan::Pengaduan ? $laporan->dibuat_pada : null;
        $labelPetugas = fn (Pesan $pesan): string => match (true) {
            ! $wbs => 'Petugas',
            $pindahanPada !== null && $pesan->dibuat_pada->lessThanOrEqualTo($pindahanPada) => 'Petugas Pengaduan',
            default => 'Tim WBS',
        };

        return [
            'wbs' => $wbs,
            'kodeLacak' => $laporan->kode_lacak,
            'judul' => $laporan->judul,
            'unit' => $laporan->unit?->nama,
            'kategori' => $laporan->kategori?->nama,
            'dibuatPada' => WaktuLokal::tampilkan($laporan->dibuat_pada),
            'status' => [
                'nilai' => $laporan->status->value,
                'label' => $laporan->status->getLabel(),
                'penjelasan' => $laporan->status->penjelasanUntukPelapor($laporan->jenis),
            ],
            // Baris tanpa perubahan status (mis. koreksi kedudukan terlapor) hanya untuk petugas.
            'riwayat' => $laporan->riwayatStatus
                ->reject(fn (RiwayatStatus $riwayat): bool => $riwayat->status_lama !== null && $riwayat->status_lama === $riwayat->status_baru)
                ->sortBy('id')->values()->map(fn (RiwayatStatus $riwayat): array => [
                    'id' => $riwayat->id,
                    'waktu' => WaktuLokal::tampilkan($riwayat->waktu, 'd F Y, H.i'),
                    'status' => $riwayat->status_baru->getLabel(),
                    'catatan' => $riwayat->tampil_ke_pelapor ? $riwayat->catatan : null,
                ])->all(),
            'pesan' => $laporan->pesanUntukPelapor->sortBy('id')->values()->map(fn (Pesan $pesan): array => [
                'id' => $pesan->id,
                'dari' => match ($pesan->pengirim) {
                    PengirimPesan::Pelapor => 'Anda',
                    PengirimPesan::Petugas => $labelPetugas($pesan),
                    PengirimPesan::Sistem => 'Sistem',
                },
                'milikPelapor' => $pesan->pengirim === PengirimPesan::Pelapor,
                'isi' => $pesan->isi,
                'waktu' => WaktuLokal::tampilkan($pesan->dibuat_pada, 'd F Y, H.i'),
                'lampiran' => $pesan->lampiran->map(fn (Lampiran $lampiran): string => $lampiran->nama_asli)->all(),
            ])->all(),
            'bolehMenanggapi' => $laporan->status === StatusLaporan::MenungguKonfirmasi,
            'bolehKirimPesan' => PesanLaporan::pelaporBolehMengirim($laporan),
            'batasTanggapan' => ($batas = KonfirmasiPelapor::batasTanggapan($laporan))
                ? WaktuLokal::tampilkan($batas->subSecond(), 'l, d F Y, H.i')
                : null,
            'bolehMenilai' => KonfirmasiPelapor::bisaDinilai($laporan),
            'penilaian' => $laporan->penilaian,
            'masaSesiMenit' => IzinLacak::masaMenit(),
            'berhasil' => session('lacak_berhasil'),
            'aturanLampiran' => $wbs ? WbsController::aturanLampiran() : [
                'maks_berkas' => (int) config('sigap.lampiran.maks_berkas'),
                'maks_total_mb' => (int) config('sigap.lampiran.maks_total_mb'),
                'jenis' => collect(JenisBerkas::cases())
                    ->filter(fn (JenisBerkas $jenis): bool => $jenis->bolehUntuk(JenisLaporan::Pengaduan))
                    ->map(fn (JenisBerkas $jenis): array => ['label' => $jenis->getLabel(), 'maks_mb' => intdiv($jenis->maksByte(), 1024 * 1024)])
                    ->values()
                    ->all(),
            ],
            // Lampiran tambahan WBS diunggah lewat /wbs/lampiran/unggah (tanpa sesi), memakai token formulir WBS.
            'tokenUnggahWbs' => $wbs ? TokenFormulirWbs::terbitkan() : null,
        ];
    }
}
