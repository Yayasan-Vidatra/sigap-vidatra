<?php

namespace App\Services\Pengaduan;

use App\Enums\Peran;
use App\Models\Laporan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Petugas penerima surel pengaduan. Akun yang terkunci tidak dikirimi surel. Surel petugas diambil dari
 * basis data (aturan proyek no. 7), tidak ditanam di kode.
 */
class PenerimaNotifikasi
{
    /**
     * Admin Pengaduan yayasan dan Admin Sekolah unit pengaduan itu.
     *
     * @return Collection<int, User>
     */
    public static function adminUntuk(Laporan $laporan): Collection
    {
        return self::aktif()
            ->where('peran', Peran::AdminPengaduan->value)
            ->where(fn (Builder $query) => $query->whereNull('unit_id')->orWhere('unit_id', $laporan->unit_id))
            ->get();
    }

    /**
     * Pimpinan Yayasan (A7): Ketua Umum dan Pengurus Harian.
     *
     * @return Collection<int, User>
     */
    public static function pimpinanYayasan(): Collection
    {
        return self::aktif()
            ->whereIn('peran', [Peran::KetuaUmum->value, Peran::PengurusHarian->value])
            ->get();
    }

    /**
     * @return Builder<User>
     */
    private static function aktif(): Builder
    {
        return User::query()->whereNull('terkunci_pada')->whereNotNull('email');
    }
}
