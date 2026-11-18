<?php

namespace App\Services\Wbs;

use App\Enums\JenisLaporan;
use App\Enums\KedudukanTerlapor;
use App\Enums\Peran;
use App\Models\Laporan;
use App\Models\TerlaporWbs;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Perutean akses laporan WBS menurut matriks penerima (Rancangan r1 Bagian 3.1, Bagian 9, UC-02 2a; F-19; aturan
 * proyek no. 2 dan 3). Tiga lapis:
 *
 * 1. Kedudukan terlapor yang dipilih pelapor (atau dikoreksi penelaah) menentukan peran penelaah.
 * 2. Lapis kedua berdasarkan nama terlapor: bila nama pejabat yang berperan di sistem disebut sebagai terlapor,
 *    kedudukan efektif dinaikkan ke kedudukan yang tersirat dari perannya (anggota Tim WBS -> SPI/Tim WBS,
 *    Dewan Pengawas -> Pengawas, Pembina -> Pembina), dan orang yang disebut itu sendiri tidak pernah mendapat
 *    akses. Nama terlapor terenkripsi, sehingga pencocokan dilakukan di aplikasi, bukan di SQL.
 * 3. Investigator yang ditunjuk penelaah ikut mendapat akses (anggota tim yang tidak terlibat, UC-13).
 *
 * Super Admin dan peran di luar matriks tidak pernah mendapat akses.
 */
class AksesWbs
{
    /**
     * Kedudukan yang menentukan perutean: yang tertinggi antara pilihan pelapor dan yang tersirat dari nama terlapor.
     */
    /**
     * @param  Collection<int, User>|null  $pejabat  daftar pejabat berperan, bila sudah dimuat pemanggil
     */
    public static function kedudukanEfektif(Laporan $laporan, ?Collection $pejabat = null): KedudukanTerlapor
    {
        $kedudukan = $laporan->kedudukan_terlapor ?? KedudukanTerlapor::TidakTahu;

        foreach (self::pejabatDisebut($laporan, $pejabat) as $orang) {
            $tersirat = KedudukanTerlapor::tersiratDariPeran($orang->peran);

            if ($tersirat !== null && $tersirat->tingkat() > $kedudukan->tingkat()) {
                $kedudukan = $tersirat;
            }
        }

        return $kedudukan;
    }

    /**
     * @param  Collection<int, User>|null  $pejabat
     */
    public static function bolehBuka(User $user, Laporan $laporan, ?Collection $pejabat = null): bool
    {
        if (! $laporan->isWbs() || self::disebutSebagaiTerlapor($user, $laporan)) {
            return false;
        }

        if ($laporan->investigator_user_id !== null && $laporan->investigator_user_id === $user->getKey()) {
            return true;
        }

        return $user->peran !== null && in_array($user->peran, self::kedudukanEfektif($laporan, $pejabat)->peranPenelaah(), true);
    }

    /**
     * Penelaah utama (Ketua Tim WBS atau penelaah pengganti): telaah, koreksi kedudukan, penunjukan investigator,
     * dan pembukaan identitas pelapor.
     */
    public static function penelaahUtama(User $user, Laporan $laporan): bool
    {
        return self::bolehBuka($user, $laporan)
            && $user->peran !== null
            && in_array($user->peran, self::kedudukanEfektif($laporan)->peranPenelaahUtama(), true);
    }

    /**
     * Id laporan WBS yang boleh dibuka pengguna, untuk scope daftar Ruang Kerja WBS dan log audit.
     *
     * @return list<int>
     */
    public static function idTerbukaBagi(User $user): array
    {
        if ($user->peran === null || (KedudukanTerlapor::nilaiTerbukaBagi($user->peran) === [] && ! self::bisaJadiInvestigator($user))) {
            return [];
        }

        $pejabat = self::pejabat();

        return Laporan::query()
            ->where('jenis', JenisLaporan::Wbs->value)
            ->with('terlapor')
            ->get()
            ->filter(fn (Laporan $laporan): bool => self::bolehBuka($user, $laporan, $pejabat))
            ->map(fn (Laporan $laporan): int => $laporan->getKey())
            ->values()
            ->all();
    }

    /**
     * Penerima hasil menurut matriks (US-21): penerima utama, atau penerima tembusan bila $denganTembusan. Pejabat yang
     * disebut sebagai terlapor tidak pernah menjadi penerima.
     */
    public static function penerimaHasil(User $user, Laporan $laporan, bool $denganTembusan = true): bool
    {
        if (! $laporan->isWbs() || $user->peran === null || self::disebutSebagaiTerlapor($user, $laporan)) {
            return false;
        }

        $kedudukan = self::kedudukanEfektif($laporan);
        $peran = [...$kedudukan->peranPenerimaHasil(), ...($denganTembusan ? $kedudukan->peranTembusanHasil() : [])];

        return in_array($user->peran, $peran, true);
    }

    /**
     * Laporan yang sudah sampai ke penerima hasil: hasil sudah dikirim, atau perpanjangan pernah diajukan.
     */
    public static function sudahUntukPenerima(Laporan $laporan): bool
    {
        return $laporan->kesimpulan !== null || $laporan->perpanjangan_diajukan_pada !== null || $laporan->perpanjangan_disetujui_oleh !== null;
    }

    /**
     * Id laporan untuk halaman Hasil WBS untuk Saya (H-16).
     *
     * @return list<int>
     */
    public static function idHasilUntuk(User $user): array
    {
        if (! in_array($user->peran, [Peran::DewanPengawas, Peran::KetuaPembina, Peran::Pembina, Peran::KetuaUmum], true)) {
            return [];
        }

        return Laporan::query()
            ->where('jenis', JenisLaporan::Wbs->value)
            ->where(fn ($query) => $query->whereNotNull('kesimpulan')->orWhereNotNull('perpanjangan_diajukan_pada')->orWhereNotNull('perpanjangan_disetujui_oleh'))
            ->with('terlapor')
            ->get()
            ->filter(fn (Laporan $laporan): bool => self::penerimaHasil($user, $laporan, denganTembusan: $laporan->kesimpulan !== null))
            ->map(fn (Laporan $laporan): int => $laporan->getKey())
            ->values()
            ->all();
    }

    /**
     * Penerima hasil yang aktif, untuk notifikasi hasil (dengan tembusan) atau pengajuan perpanjangan (tanpa tembusan).
     *
     * @return Collection<int, User>
     */
    public static function penerimaHasilAktif(Laporan $laporan, bool $denganTembusan): Collection
    {
        $kedudukan = self::kedudukanEfektif($laporan);
        $peran = [...$kedudukan->peranPenerimaHasil(), ...($denganTembusan ? $kedudukan->peranTembusanHasil() : [])];

        return User::query()
            ->whereIn('peran', array_map(fn (Peran $peran): string => $peran->value, $peran))
            ->whereNull('terkunci_pada')
            ->get()
            ->reject(fn (User $user): bool => self::disebutSebagaiTerlapor($user, $laporan))
            ->values();
    }

    /**
     * Penelaah utama yang aktif untuk laporan ini, penerima notifikasi laporan baru.
     *
     * @return Collection<int, User>
     */
    public static function penelaahAktif(Laporan $laporan): Collection
    {
        $peran = array_map(fn ($peran): string => $peran->value, self::kedudukanEfektif($laporan)->peranPenelaahUtama());

        return User::query()
            ->whereIn('peran', $peran)
            ->whereNull('terkunci_pada')
            ->get()
            ->reject(fn (User $user): bool => self::disebutSebagaiTerlapor($user, $laporan))
            ->values();
    }

    /**
     * Calon investigator: anggota atau Ketua Tim WBS yang aktif dan tidak disebut sebagai terlapor. Ketua Tim WBS
     * tidak termasuk bila laporan tertutup baginya menurut matriks.
     *
     * @return Collection<int, User>
     */
    public static function calonInvestigator(Laporan $laporan): Collection
    {
        $tertutupBagiKetua = self::kedudukanEfektif($laporan)->tertutupBagiKetuaTimWbs();

        return User::query()
            ->whereIn('peran', array_filter([
                Peran::AnggotaTimWbs->value,
                $tertutupBagiKetua ? null : Peran::KetuaTimWbs->value,
            ]))
            ->whereNull('terkunci_pada')
            ->orderBy('name')
            ->get()
            ->reject(fn (User $user): bool => self::disebutSebagaiTerlapor($user, $laporan))
            ->values();
    }

    public static function disebutSebagaiTerlapor(User $user, Laporan $laporan): bool
    {
        $nama = self::normalkan((string) $user->name);

        if ($nama === '') {
            return false;
        }

        return $laporan->terlapor->contains(fn (TerlaporWbs $terlapor): bool => self::namaCocok($nama, self::normalkan((string) $terlapor->nama)));
    }

    /**
     * Pejabat berperan yang namanya disebut sebagai terlapor.
     *
     * @param  Collection<int, User>|null  $pejabat
     * @return Collection<int, User>
     */
    private static function pejabatDisebut(Laporan $laporan, ?Collection $pejabat): Collection
    {
        if ($laporan->terlapor->isEmpty()) {
            return collect();
        }

        return ($pejabat ?? self::pejabat())->filter(fn (User $user): bool => self::disebutSebagaiTerlapor($user, $laporan))->values();
    }

    /**
     * @return Collection<int, User>
     */
    private static function pejabat(): Collection
    {
        return User::query()->whereNotNull('peran')->get(['id', 'name', 'peran']);
    }

    private static function bisaJadiInvestigator(User $user): bool
    {
        return Laporan::query()->where('investigator_user_id', $user->getKey())->exists();
    }

    /**
     * Nama sama persis, atau nama lengkap pejabat muncul utuh di dalam nama terlapor (mis. "Pak Budi Santoso, S.Pd.").
     */
    private static function namaCocok(string $namaPejabat, string $namaTerlapor): bool
    {
        return $namaTerlapor !== '' && ($namaPejabat === $namaTerlapor || str_contains(" {$namaTerlapor} ", " {$namaPejabat} "));
    }

    private static function normalkan(string $nama): string
    {
        $nama = Str::of(Str::ascii($nama))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish();

        return (string) $nama;
    }
}
