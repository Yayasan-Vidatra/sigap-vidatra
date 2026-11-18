<?php

namespace App\Http\Controllers;

use App\Enums\AksiAudit;
use App\Enums\JenisBerkas;
use App\Enums\JenisLaporan;
use App\Enums\PengirimPesan;
use App\Enums\StatusLaporan;
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
use App\Services\Pengaduan\KonfirmasiPelapor;
use App\Services\Pengaduan\PesanLaporan;
use App\Services\WaktuLokal;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-06 Lacak Laporan dan H-07 Detail Laporan untuk pelapor pengaduan (US-08, US-09, US-13; UC-04, UC-05,
 * UC-06; F-06). Kode lacak yang tidak ada dan yang salah mendapat pesan yang sama. Setelah 5 kali gagal,
 * percobaan berikutnya wajib CAPTCHA dan menunggu jeda. Detail hanya terbuka dengan izin di sesi
 * (IzinLacak), yang berakhir setelah 15 menit tidak aktif.
 *
 * Laporan WBS dilacak lewat jalurnya sendiri tanpa sesi dan tanpa IP (Hari 9); di sini kode WBS mendapat
 * pesan gagal yang sama.
 */
class LacakController extends Controller
{
    public const PESAN_GAGAL = 'Kode lacak atau PIN tidak cocok. Periksa kembali lalu coba lagi.';

    public const PESAN_SESI_BERAKHIR = 'Sesi Lacak berakhir karena 15 menit tidak aktif atau belum dibuka. Masukkan kode lacak lagi.';

    public function create(Request $request): Response
    {
        return Inertia::render('Lacak/Masuk', [
            'perluCaptcha' => PembatasPercobaanLacak::perluCaptcha($request),
            'sisaJeda' => PembatasPercobaanLacak::sisaJeda($request),
            'pesan' => $request->session()->get('lacak_pesan'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (($jeda = PembatasPercobaanLacak::sisaJeda($request)) > 0) {
            throw ValidationException::withMessages([
                'kode' => "Terlalu banyak percobaan. Coba lagi dalam {$jeda} detik.",
            ]);
        }

        $perluCaptcha = PembatasPercobaanLacak::perluCaptcha($request);

        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30'],
            'pin' => ['nullable', 'string', 'max:20'],
            'altcha' => $perluCaptcha ? ['bail', 'required', 'string', new AltchaSah] : ['nullable'],
        ], [
            'kode.required' => 'Masukkan kode lacak.',
            'kode.max' => self::PESAN_GAGAL,
            'altcha.required' => 'Selesaikan verifikasi "Saya bukan robot" terlebih dahulu.',
        ]);

        if ($perluCaptcha && ! AltchaSah::tandaiDipakai((string) $data['altcha'])) {
            throw ValidationException::withMessages(['altcha' => 'Verifikasi "Saya bukan robot" sudah dipakai. Silakan ulangi.']);
        }

        $kode = strtoupper(trim($data['kode']));
        $laporan = Laporan::query()->where('kode_lacak', $kode)->first();

        if ($laporan === null || $laporan->jenis !== JenisLaporan::Pengaduan) {
            PembatasPercobaanLacak::catatGagal($request);

            throw ValidationException::withMessages(['kode' => self::PESAN_GAGAL]);
        }

        PembatasPercobaanLacak::bersihkan($request);
        IzinLacak::berikan($request, $laporan);
        LogAudit::catat(null, AksiAudit::PelaporMembukaLaporan, $laporan);

        return redirect()->route('lacak.show', $laporan->kode_lacak);
    }

    public function show(Request $request, string $kode): Response|RedirectResponse
    {
        return $this->denganIzin($request, $kode, fn (Laporan $laporan): Response => Inertia::render('Lacak/Detail', $this->dataDetail($laporan)));
    }

    public function kirimPesan(Request $request, string $kode, PesanLaporan $pesan): RedirectResponse
    {
        return $this->denganIzin($request, $kode, function (Laporan $laporan) use ($request, $pesan): RedirectResponse {
            $data = $request->validate([
                'isi' => ['required', 'string', 'max:'.PesanLaporan::MAKS_ISI],
                'lampiran' => ['nullable', 'array', 'max:'.config('sigap.lampiran.maks_berkas'), new UnggahanLampiranSiap(JenisLaporan::Pengaduan)],
                'lampiran.*' => ['string', 'distinct'],
            ], [
                'isi.required' => 'Tulis pesan terlebih dahulu.',
                'isi.max' => 'Pesan paling banyak :max karakter.',
                'lampiran.max' => 'Lampiran paling banyak :max berkas.',
            ]);

            return $this->jalankan(fn () => $pesan->kirimDariPelapor($laporan, $data['isi'], array_values($data['lampiran'] ?? [])), 'Pesan terkirim.', 'isi');
        });
    }

    public function konfirmasi(Request $request, string $kode, KonfirmasiPelapor $konfirmasi): RedirectResponse
    {
        return $this->denganIzin($request, $kode, fn (Laporan $laporan): RedirectResponse => $this->jalankan(
            fn () => $konfirmasi->konfirmasi($laporan),
            'Terima kasih. Pengaduan Anda dinyatakan selesai.',
            'tanggapan',
        ));
    }

    public function tolak(Request $request, string $kode, KonfirmasiPelapor $konfirmasi): RedirectResponse
    {
        return $this->denganIzin($request, $kode, function (Laporan $laporan) use ($request, $konfirmasi): RedirectResponse {
            $data = $request->validate(
                ['alasan' => ['required', 'string', 'max:1000']],
                ['alasan.required' => 'Tuliskan alasan Anda menolak hasilnya.', 'alasan.max' => 'Alasan paling banyak :max karakter.'],
            );

            return $this->jalankan(fn () => $konfirmasi->tolakHasil($laporan, $data['alasan']), 'Tanggapan Anda terkirim. Pengaduan kembali ditangani.', 'alasan');
        });
    }

    public function nilai(Request $request, string $kode, KonfirmasiPelapor $konfirmasi): RedirectResponse
    {
        return $this->denganIzin($request, $kode, function (Laporan $laporan) use ($request, $konfirmasi): RedirectResponse {
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
    private function denganIzin(Request $request, string $kode, Closure $lanjut): mixed
    {
        $laporan = IzinLacak::laporan($request, strtoupper($kode));

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

        return [
            'kodeLacak' => $laporan->kode_lacak,
            'judul' => $laporan->judul,
            'unit' => $laporan->unit?->nama,
            'kategori' => $laporan->kategori?->nama,
            'dibuatPada' => WaktuLokal::tampilkan($laporan->dibuat_pada),
            'status' => [
                'nilai' => $laporan->status->value,
                'label' => $laporan->status->getLabel(),
                'penjelasan' => $laporan->status->penjelasanUntukPelapor(),
            ],
            'riwayat' => $laporan->riwayatStatus->sortBy('id')->values()->map(fn (RiwayatStatus $riwayat): array => [
                'id' => $riwayat->id,
                'waktu' => WaktuLokal::tampilkan($riwayat->waktu, 'd F Y, H.i'),
                'status' => $riwayat->status_baru->getLabel(),
                'catatan' => $riwayat->tampil_ke_pelapor ? $riwayat->catatan : null,
            ])->all(),
            'pesan' => $laporan->pesanUntukPelapor->sortBy('id')->values()->map(fn (Pesan $pesan): array => [
                'id' => $pesan->id,
                'dari' => match ($pesan->pengirim) {
                    PengirimPesan::Pelapor => 'Anda',
                    PengirimPesan::Petugas => 'Petugas',
                    PengirimPesan::Sistem => 'Sistem',
                },
                'milikPelapor' => $pesan->pengirim === PengirimPesan::Pelapor,
                'isi' => $pesan->isi,
                'waktu' => WaktuLokal::tampilkan($pesan->dibuat_pada, 'd F Y, H.i'),
                'lampiran' => $pesan->lampiran->map(fn (Lampiran $lampiran): string => $lampiran->nama_asli)->all(),
            ])->all(),
            'bolehMenanggapi' => $laporan->status === StatusLaporan::MenungguKonfirmasi,
            'batasTanggapan' => ($batas = KonfirmasiPelapor::batasTanggapan($laporan))
                ? WaktuLokal::tampilkan($batas->subSecond(), 'l, d F Y, H.i')
                : null,
            'bolehMenilai' => KonfirmasiPelapor::bisaDinilai($laporan),
            'penilaian' => $laporan->penilaian,
            'masaSesiMenit' => IzinLacak::masaMenit(),
            'berhasil' => session('lacak_berhasil'),
            'aturanLampiran' => [
                'maks_berkas' => (int) config('sigap.lampiran.maks_berkas'),
                'maks_total_mb' => (int) config('sigap.lampiran.maks_total_mb'),
                'jenis' => collect(JenisBerkas::cases())
                    ->filter(fn (JenisBerkas $jenis): bool => $jenis->bolehUntuk(JenisLaporan::Pengaduan))
                    ->map(fn (JenisBerkas $jenis): array => ['label' => $jenis->getLabel(), 'maks_mb' => intdiv($jenis->maksByte(), 1024 * 1024)])
                    ->values()
                    ->all(),
            ],
        ];
    }
}
