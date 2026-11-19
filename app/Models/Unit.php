<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unit Yayasan: SD YPVDP, SMP YPVDP, SMA YPVDP, Pelaksana Harian (Rancangan r1 Bagian 10).
 */
#[Fillable(['nama', 'aktif', 'kontak_darurat'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diperbarui_pada';

    /**
     * Kalimat untuk unit yang kontak daruratnya belum diisi di Pengaturan, agar halaman publik tidak mengajak
     * menghubungi nomor yang tidak tampil.
     */
    public const KONTAK_DARURAT_CADANGAN = 'Segera hubungi wali kelas atau kantor sekolah.';

    /**
     * Nama unit aktif yang kontak daruratnya belum diisi.
     *
     * @return list<string>
     */
    public static function tanpaKontakDarurat(): array
    {
        return self::query()
            ->where('aktif', true)
            ->where(fn ($query) => $query->whereNull('kontak_darurat')->orWhere('kontak_darurat', ''))
            ->orderBy('id')
            ->pluck('nama')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Laporan, $this>
     */
    public function laporan(): HasMany
    {
        return $this->hasMany(Laporan::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Disposisi, $this>
     */
    public function disposisi(): HasMany
    {
        return $this->hasMany(Disposisi::class);
    }
}
