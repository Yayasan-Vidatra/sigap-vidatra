<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Laporan;
use App\Models\User;
use App\Services\Wbs\AksesWbs;

/**
 * Hak akses laporan. Setiap halaman laporan petugas memakai ability-nya sendiri agar aturan tiap
 * kanal tidak tercampur: Kotak Masuk Pengaduan (H-11) memakai lihatKotakMasukPengaduan dan
 * kelolaPengaduan; Pengaduan Unit Saya (H-12) memakai lihatPengaduanUnit dan tindakLanjutiPengaduan.
 * Ruang Kerja WBS (H-13) memakai lihatRuangKerjaWbs, bukaLaporanWbs, tanganiLaporanWbs, telaahLaporanWbs,
 * simpulkanLaporanWbs, dan catatLaporanMasuk.
 *
 * Ability bawaan Filament (viewAny, view, create, update, delete, ...) sengaja ditolak, sehingga
 * resource laporan baru tidak terbuka tanpa sengaja sebelum aturannya ditulis.
 */
class LaporanPolicy
{
    /**
     * H-11 hanya untuk Admin Pengaduan, baik yayasan maupun Admin Sekolah.
     */
    public function lihatKotakMasukPengaduan(User $user): bool
    {
        return $user->peran === Peran::AdminPengaduan;
    }

    /**
     * Membuka dan menangani satu pengaduan (verifikasi, tolak, disposisi). Aturannya sama dengan scope
     * Laporan::kotakMasukPengaduan(), sehingga daftar dan halaman detail tidak bisa berbeda.
     */
    public function kelolaPengaduan(User $user, Laporan $laporan): bool
    {
        return Laporan::query()->kotakMasukPengaduan($user)->whereKey($laporan->getKey())->exists();
    }

    /**
     * Identitas pelapor pengaduan dibutuhkan Admin Pengaduan untuk menghubungi pelapor. Identitas pelapor WBS
     * hanya untuk Ketua Tim WBS atau penelaah pengganti menurut matriks (US-16). Setiap pembukaannya dicatat di
     * log audit oleh halaman yang menampilkannya.
     */
    public function lihatIdentitasPelapor(User $user, Laporan $laporan): bool
    {
        if ($laporan->isWbs()) {
            return AksesWbs::penelaahUtama($user, $laporan);
        }

        return $this->kelolaPengaduan($user, $laporan);
    }

    /**
     * H-12 Pengaduan Unit Saya hanya untuk PIC Unit.
     */
    public function lihatPengaduanUnit(User $user): bool
    {
        return $user->peran === Peran::PicUnit && $user->unit_id !== null;
    }

    /**
     * Membuka dan menindaklanjuti pengaduan yang didisposisikan ke unit PIC (UC-10). Aturannya sama
     * dengan scope Laporan::pengaduanUnitPic().
     */
    public function tindakLanjutiPengaduan(User $user, Laporan $laporan): bool
    {
        return Laporan::query()->pengaduanUnitPic($user)->whereKey($laporan->getKey())->exists();
    }

    /**
     * H-13 Ruang Kerja WBS untuk peran yang ada di matriks penerima (A5, dan A6 sebagai penelaah pengganti).
     * Super Admin, Kepala Sekolah, dan peran lain tidak membukanya (aturan proyek no. 3).
     */
    public function lihatRuangKerjaWbs(User $user): bool
    {
        return in_array($user->peran, [Peran::KetuaTimWbs, Peran::AnggotaTimWbs, Peran::DewanPengawas, Peran::KetuaPembina, Peran::Pembina], true);
    }

    /**
     * Membuka isi laporan WBS menurut matriks penerima (Bagian 3.1, F-19), lapis kedua nama terlapor, dan penunjukan
     * investigator. Aturannya sama dengan scope Laporan::wbsTerbukaBagi(); Super Admin tidak pernah boleh.
     */
    public function bukaLaporanWbs(User $user, Laporan $laporan): bool
    {
        return AksesWbs::bolehBuka($user, $laporan);
    }

    /**
     * Pesan ke pelapor dan catatan internal (UC-05, UC-13): semua yang boleh membuka laporan.
     */
    public function tanganiLaporanWbs(User $user, Laporan $laporan): bool
    {
        return AksesWbs::bolehBuka($user, $laporan);
    }

    /**
     * Telaah, koreksi kedudukan, dan penunjukan investigator (UC-12, US-15): Ketua Tim WBS atau penelaah pengganti.
     */
    public function telaahLaporanWbs(User $user, Laporan $laporan): bool
    {
        return AksesWbs::penelaahUtama($user, $laporan);
    }

    /**
     * Kesimpulan dan rekomendasi (UC-13, US-17): penelaah utama atau investigator yang ditunjuk.
     */
    public function simpulkanLaporanWbs(User $user, Laporan $laporan): bool
    {
        return AksesWbs::penelaahUtama($user, $laporan)
            || ($laporan->investigator_user_id === $user->getKey() && AksesWbs::bolehBuka($user, $laporan));
    }

    /**
     * H-14 Catat Laporan Masuk dari surel, telepon, atau surat (UC-18, US-18): Ketua Tim WBS (SPI).
     */
    public function catatLaporanMasuk(User $user): bool
    {
        return $user->peran === Peran::KetuaTimWbs;
    }

    /**
     * H-16 Hasil WBS untuk Saya (US-21): penerima hasil menurut matriks dan Ketua Umum (tembusan).
     */
    public function lihatHasilWbs(User $user): bool
    {
        return in_array($user->peran, [Peran::DewanPengawas, Peran::KetuaPembina, Peran::Pembina, Peran::KetuaUmum], true);
    }

    /**
     * Membuka ringkasan hasil di H-16: laporan yang sudah sampai ke penerima (hasil atau pengajuan perpanjangan) dan
     * ditujukan kepadanya menurut matriks. Tembusan hanya untuk hasil, bukan pengajuan perpanjangan.
     */
    public function bukaHasilWbs(User $user, Laporan $laporan): bool
    {
        return AksesWbs::sudahUntukPenerima($laporan)
            && AksesWbs::penerimaHasil($user, $laporan, denganTembusan: $laporan->kesimpulan !== null);
    }

    /**
     * Persetujuan perpanjangan investigasi (UC-13 1a) dan penandaan tindak lanjut hasil: penerima hasil utama.
     */
    public function putuskanHasilWbs(User $user, Laporan $laporan): bool
    {
        return AksesWbs::penerimaHasil($user, $laporan, denganTembusan: false);
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Laporan $laporan): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Laporan $laporan): bool
    {
        return false;
    }

    public function delete(User $user, Laporan $laporan): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Laporan $laporan): bool
    {
        return false;
    }

    public function forceDelete(User $user, Laporan $laporan): bool
    {
        return false;
    }
}
