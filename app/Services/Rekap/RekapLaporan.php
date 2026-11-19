<?php

namespace App\Services\Rekap;

use App\Enums\JenisLaporan;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Pengalihan;
use App\Models\RiwayatStatus;
use App\Models\Unit;
use App\Models\User;
use App\Services\WaktuLokal;
use App\Services\Wbs\AksesWbs;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Angka dashboard (H-10, US-22) dan Rekap (H-18, US-23). Semua angka dihitung dari query scope yang sama dengan hak
 * akses (Laporan::rekapPengaduanBagi, rekapWbsBagi, wbsTerbukaBagi, AksesWbs, PengalihanPolicy), bukan disaring di
 * tampilan. Rekap tidak pernah memuat isi laporan, identitas pelapor, terlapor, atau catatan internal: hanya jumlah.
 */
class RekapLaporan
{
    /**
     * Jumlah WBS per kategori per triwulan disembunyikan bila kurang dari angka ini (Bagian 2 A7, F-13).
     */
    public const MINIMAL_WBS = 3;

    /**
     * Status pengaduan yang masih berjalan dan punya batas waktu selesai.
     *
     * @var list<StatusLaporan>
     */
    public const STATUS_PENGADUAN_BERJALAN = [StatusLaporan::Diproses, StatusLaporan::MenungguKonfirmasi];

    /**
     * @var list<StatusLaporan>
     */
    public const STATUS_WBS_BERJALAN = [
        StatusLaporan::Diterima, StatusLaporan::Ditelaah, StatusLaporan::Diinvestigasi, StatusLaporan::DiinvestigasiDiperpanjang,
    ];

    public static function bolehRekapPengaduan(User $user): bool
    {
        return in_array($user->peran, [Peran::AdminPengaduan, Peran::PicUnit, ...Laporan::PERAN_REKAP_AGREGAT], true);
    }

    public static function bolehRekapWbs(User $user): bool
    {
        return in_array($user->peran, Laporan::PERAN_REKAP_AGREGAT, true);
    }

    /**
     * H-18 Rekap dan Ekspor: Admin, Pimpinan Yayasan, dan Penerima Hasil (TOR H-18, Rancangan Bagian 11 no. 7).
     */
    public static function bolehBukaRekap(User $user): bool
    {
        return in_array($user->peran, [Peran::AdminPengaduan, ...Laporan::PERAN_REKAP_AGREGAT], true);
    }

    /**
     * Ringkasan dashboard sesuai peran: laporan baru, lewat batas waktu, dan menunggu tindakan (H-10, US-22).
     *
     * @return list<array{label: string, nilai: int, keterangan: string}>
     */
    public function ringkasan(User $user): array
    {
        $sekarang = now();

        return match (true) {
            $user->peran === Peran::AdminPengaduan => [
                self::kartu('Pengaduan baru', $this->pengaduan($user)->where('status', StatusLaporan::Diterima->value)->count(), 'Menunggu verifikasi.'),
                self::kartu('Menunggu disposisi', $this->pengaduan($user)->where('status', StatusLaporan::Terverifikasi->value)->count(), 'Sudah diverifikasi, belum diteruskan ke unit.'),
                self::kartu('Lewat batas waktu', $this->pengaduanLewatBatas($user, $sekarang), 'Masih berjalan dan melewati batas waktu selesai.'),
            ],
            $user->peran === Peran::PicUnit => [
                self::kartu('Pengaduan baru', $this->pengaduan($user)->where('status', StatusLaporan::Diproses->value)
                    ->whereDoesntHave('disposisi', fn (Builder $disposisi) => $disposisi->whereNotNull('tindak_lanjut'))->count(), 'Didisposisikan, tindak lanjut belum diisi.'),
                self::kartu('Menunggu tindakan Anda', $this->pengaduan($user)->where('status', StatusLaporan::Diproses->value)->count(), 'Sedang diproses unit Anda.'),
                self::kartu('Lewat batas waktu', $this->pengaduanLewatBatas($user, $sekarang), 'Masih berjalan dan melewati batas waktu selesai.'),
            ],
            in_array($user->peran, [Peran::KetuaTimWbs, Peran::AnggotaTimWbs], true) => $this->ringkasanTimWbs($user, $sekarang),
            in_array($user->peran, Laporan::PERAN_REKAP_AGREGAT, true) => $this->ringkasanPimpinan($user, $sekarang),
            in_array($user->peran, [Peran::KepalaSekolah, Peran::KomiteEtik], true) => $this->ringkasanPengalihan($user),
            $user->peran === Peran::SuperAdmin => [
                self::kartu('Akun terkunci', User::query()->whereNotNull('terkunci_pada')->count(), 'Menunggu dibuka Super Admin.'),
            ],
            default => [],
        };
    }

    /**
     * Grafik pengaduan per unit dan per bulan (H-10). Kunci luar: nama unit; nilai: jumlah per bulan 1–12 (WITA).
     *
     * @return array<string, array<int, int>>
     */
    public function pengaduanPerUnitPerBulan(User $user, int $tahun): array
    {
        [$awal, $akhir] = self::rentangTahun($tahun);
        $hasil = [];

        $this->pengaduan($user)
            ->whereBetween('dibuat_pada', [$awal, $akhir])
            ->with('unit:id,nama')
            ->get(['id', 'unit_id', 'dibuat_pada'])
            ->each(function (Laporan $laporan) use (&$hasil): void {
                $unit = $laporan->unit->nama ?? '-';
                $hasil[$unit] ??= array_fill(1, 12, 0);
                $hasil[$unit][WaktuLokal::keLokal($laporan->dibuat_pada)->month]++;
            });

        ksort($hasil);

        return $hasil;
    }

    /**
     * Jumlah WBS per kategori per triwulan (A7, F-13). Sel yang kurang dari 3 (termasuk 0) bernilai null dan tampil
     * sebagai "< 3"; tidak ada total baris atau kolom, agar sel tersembunyi tidak dapat dihitung balik dari selisih.
     * Semua kategori WBS selalu tercantum, sehingga ada tidaknya baris juga tidak membuka informasi.
     *
     * @return list<array{kategori: string, triwulan: array<int, int|null>}>
     */
    public function wbsPerKategoriPerTriwulan(User $user, int $tahun): array
    {
        if (! self::bolehRekapWbs($user)) {
            return [];
        }

        [$awal, $akhir] = self::rentangTahun($tahun);
        $jumlah = [];

        Laporan::query()->rekapWbsBagi($user)
            ->whereBetween('dibuat_pada', [$awal, $akhir])
            ->get(['id', 'kategori_id', 'dibuat_pada'])
            ->each(function (Laporan $laporan) use (&$jumlah): void {
                $triwulan = WaktuLokal::keLokal($laporan->dibuat_pada)->quarter;
                $jumlah[$laporan->kategori_id][$triwulan] = ($jumlah[$laporan->kategori_id][$triwulan] ?? 0) + 1;
            });

        return Kategori::query()
            ->where('jenis', JenisLaporan::Wbs->value)
            ->orderBy('id')
            ->get(['id', 'nama'])
            ->map(fn (Kategori $kategori): array => [
                'kategori' => $kategori->nama,
                'triwulan' => collect(range(1, 4))->mapWithKeys(function (int $triwulan) use ($jumlah, $kategori): array {
                    $nilai = $jumlah[$kategori->id][$triwulan] ?? 0;

                    return [$triwulan => $nilai >= self::MINIMAL_WBS ? $nilai : null];
                })->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Rekap pengaduan per unit dan kategori untuk periode (H-18): jumlah, selesai, rata-rata hari penyelesaian (sejak
     * masuk sampai Selesai), dan jumlah yang lewat batas waktu (selesai terlambat atau masih berjalan melewati batas).
     * Pengaduan berstatus Dipindah ke WBS tidak dihitung: penanganannya pindah ke kanal WBS (dan terhitung di rekap
     * WBS), sehingga tidak boleh tampak sebagai pengaduan yang belum selesai. Jumlahnya sengaja tidak ditampilkan per
     * unit agar angka kecil tidak membuka adanya laporan WBS.
     *
     * @param  array{dari: string, sampai: string, unit_id?: int|string|null, kategori_id?: int|string|null}  $filter
     * @return list<array{unit: string, kategori: string, jumlah: int, selesai: int, rata_rata_hari: float|null, lewat_sla: int}>
     */
    public function rekapPengaduan(User $user, array $filter): array
    {
        $sekarang = now();
        $laporan = $this->pengaduan($user)
            ->whereNot('status', StatusLaporan::DipindahKeWbs->value)
            ->whereBetween('dibuat_pada', [WaktuLokal::awalHari($filter['dari']), WaktuLokal::akhirHari($filter['sampai'])])
            ->when(filled($filter['unit_id'] ?? null), fn (Builder $query) => $query->where('unit_id', $filter['unit_id']))
            ->when(filled($filter['kategori_id'] ?? null), fn (Builder $query) => $query->where('kategori_id', $filter['kategori_id']))
            ->with(['unit:id,nama', 'kategori:id,nama'])
            ->get(['id', 'unit_id', 'kategori_id', 'status', 'batas_waktu', 'dibuat_pada']);

        $waktuSelesai = RiwayatStatus::query()
            ->whereIn('laporan_id', $laporan->modelKeys())
            ->where('status_baru', StatusLaporan::Selesai->value)
            ->get(['laporan_id', 'waktu'])
            ->groupBy('laporan_id')
            ->map(fn (Collection $riwayat): CarbonImmutable => CarbonImmutable::parse($riwayat->max('waktu')));

        return $laporan
            ->groupBy(fn (Laporan $item): string => ($item->unit->nama ?? '-').'|'.($item->kategori->nama ?? 'Belum dikategorikan'))
            ->sortKeys()
            ->map(function (Collection $kelompok, string $kunci) use ($waktuSelesai, $sekarang): array {
                [$unit, $kategori] = explode('|', $kunci, 2);
                $durasi = $kelompok
                    ->filter(fn (Laporan $item): bool => $item->status === StatusLaporan::Selesai && $waktuSelesai->has($item->getKey()))
                    ->map(fn (Laporan $item): float => $item->dibuat_pada->diffInMinutes($waktuSelesai[$item->getKey()]) / 1440);

                return [
                    'unit' => $unit,
                    'kategori' => $kategori,
                    'jumlah' => $kelompok->count(),
                    'selesai' => $kelompok->where('status', StatusLaporan::Selesai)->count(),
                    'rata_rata_hari' => $durasi->isEmpty() ? null : round($durasi->avg(), 1),
                    'lewat_sla' => $kelompok->filter(fn (Laporan $item): bool => self::lewatSla($item, $waktuSelesai[$item->getKey()] ?? null, $sekarang))->count(),
                ];
            })
            ->values()
            ->all();
    }

    /** Catatan di bawah tabel rekap pengaduan (layar, PDF, Excel). */
    public const CATATAN_PINDAH_WBS = 'Pengaduan yang dipindah ke WBS tidak dihitung.';

    /**
     * Angka desimal format Indonesia (koma), misalnya 0,2; "-" bila kosong.
     */
    public static function angka(?float $nilai): string
    {
        return $nilai === null ? '-' : number_format($nilai, 1, ',', '.');
    }

    /**
     * Unit yang boleh dipilih di filter rekap: Admin Sekolah dan PIC hanya unitnya.
     *
     * @return array<int, string>
     */
    public static function pilihanUnit(User $user): array
    {
        return Unit::query()
            ->when($user->unit_id !== null && in_array($user->peran, [Peran::AdminPengaduan, Peran::PicUnit], true), fn (Builder $query) => $query->whereKey($user->unit_id))
            ->orderBy('id')
            ->pluck('nama', 'id')
            ->all();
    }

    /**
     * @return Builder<Laporan>
     */
    private function pengaduan(User $user): Builder
    {
        return Laporan::query()->rekapPengaduanBagi($user);
    }

    private function pengaduanLewatBatas(User $user, CarbonInterface $sekarang): int
    {
        return $this->pengaduan($user)
            ->whereIn('status', array_map(fn (StatusLaporan $status): string => $status->value, self::STATUS_PENGADUAN_BERJALAN))
            ->where('batas_waktu', '<', $sekarang)
            ->count();
    }

    private static function lewatSla(Laporan $laporan, ?CarbonImmutable $selesai, CarbonInterface $sekarang): bool
    {
        if ($laporan->batas_waktu === null) {
            return false;
        }

        if ($laporan->status === StatusLaporan::Selesai) {
            return $selesai !== null && $selesai->greaterThan($laporan->batas_waktu);
        }

        return in_array($laporan->status, self::STATUS_PENGADUAN_BERJALAN, true) && $laporan->batas_waktu->lessThan($sekarang);
    }

    /**
     * @return list<array{label: string, nilai: int, keterangan: string}>
     */
    private function ringkasanTimWbs(User $user, CarbonInterface $sekarang): array
    {
        $wbs = fn (): Builder => Laporan::query()->wbsTerbukaBagi($user);
        $berjalan = array_map(fn (StatusLaporan $status): string => $status->value, self::STATUS_WBS_BERJALAN);

        return [
            self::kartu('Laporan WBS baru', $wbs()->where('status', StatusLaporan::Diterima->value)->count(), 'Belum mulai ditelaah.'),
            self::kartu('Sedang ditangani', $wbs()->whereIn('status', array_slice($berjalan, 1))->count(), 'Ditelaah atau diinvestigasi.'),
            self::kartu('Lewat batas waktu', $wbs()->whereIn('status', $berjalan)->where('batas_waktu', '<', $sekarang)->count(), 'Tahap berjalan melewati tenggatnya.'),
        ];
    }

    /**
     * @return list<array{label: string, nilai: int, keterangan: string}>
     */
    private function ringkasanPimpinan(User $user, CarbonInterface $sekarang): array
    {
        $kartu = [
            self::kartu('Pengaduan baru', $this->pengaduan($user)->where('status', StatusLaporan::Diterima->value)->count(), 'Seluruh unit, menunggu verifikasi.'),
            self::kartu('Pengaduan diproses', $this->pengaduan($user)->whereIn('status', array_map(fn (StatusLaporan $status): string => $status->value, self::STATUS_PENGADUAN_BERJALAN))->count(), 'Seluruh unit.'),
            self::kartu('Pengaduan lewat batas waktu', $this->pengaduanLewatBatas($user, $sekarang), 'Seluruh unit.'),
        ];

        $idHasil = AksesWbs::idHasilUntuk($user);

        if ($idHasil !== []) {
            $kartu[] = self::kartu('Hasil WBS menunggu tindak lanjut', Laporan::query()->whereIn('id', $idHasil)
                ->whereNotNull('kesimpulan')->whereNull('hasil_ditindaklanjuti_pada')->count(), 'Buka Hasil WBS untuk Saya.');
        }

        return $kartu;
    }

    /**
     * @return list<array{label: string, nilai: int, keterangan: string}>
     */
    private function ringkasanPengalihan(User $user): array
    {
        $terlihat = Pengalihan::query()->with('laporan.terlapor')->get()->filter(fn (Pengalihan $pengalihan): bool => $user->can('view', $pengalihan));

        return [
            self::kartu('Pengalihan belum dikonfirmasi', $terlihat->whereNull('diterima_pada')->count(), 'Konfirmasi terima di Laporan Dialihkan.'),
            self::kartu('Pengalihan diterima', $terlihat->whereNotNull('diterima_pada')->count(), 'Sudah dikonfirmasi.'),
        ];
    }

    /**
     * @return array{label: string, nilai: int, keterangan: string}
     */
    private static function kartu(string $label, int $nilai, string $keterangan): array
    {
        return ['label' => $label, 'nilai' => $nilai, 'keterangan' => $keterangan];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function rentangTahun(int $tahun): array
    {
        return [WaktuLokal::awalHari("{$tahun}-01-01"), WaktuLokal::akhirHari("{$tahun}-12-31")];
    }
}
