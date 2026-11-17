<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Laporan;
use App\Models\User;

/**
 * Hak akses laporan. Setiap halaman laporan petugas memakai ability-nya sendiri agar aturan tiap
 * kanal tidak tercampur: Kotak Masuk Pengaduan (H-11) memakai lihatKotakMasukPengaduan dan
 * kelolaPengaduan. Halaman PIC Unit (H-12) dan Ruang Kerja WBS (H-13) menambah ability sendiri.
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
     * Identitas pelapor pengaduan dibutuhkan Admin Pengaduan untuk menghubungi pelapor. Setiap
     * pembukaannya dicatat di log audit oleh halaman yang menampilkannya.
     */
    public function lihatIdentitasPelapor(User $user, Laporan $laporan): bool
    {
        return $this->kelolaPengaduan($user, $laporan);
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
