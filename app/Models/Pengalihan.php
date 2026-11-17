<?php

namespace App\Models;

use App\Enums\TujuanPengalihan;
use App\Exceptions\PelanggaranIntegritasData;
use Database\Factories\PengalihanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengalihan laporan ke Kepala Sekolah, Komite Etik, atau Pokja BSAN (UC-19). Ringkasan terenkripsi.
 */
#[Table('pengalihan')]
#[Fillable([
    'laporan_id', 'tujuan', 'unit_tujuan_id', 'kepala_sekolah_terlapor', 'ringkasan', 'dikirim_oleh', 'diterima_pada',
])]
class Pengalihan extends Model
{
    /** @use HasFactory<PengalihanFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diperbarui_pada';

    /**
     * Kepala Sekolah tidak boleh menerima laporan yang menyangkut dirinya (US-20, aturan proyek no. 3).
     */
    protected static function booted(): void
    {
        static::saving(function (Pengalihan $pengalihan): void {
            if ($pengalihan->tujuan !== TujuanPengalihan::KepalaSekolah) {
                return;
            }

            if ($pengalihan->kepala_sekolah_terlapor) {
                throw PelanggaranIntegritasData::kepalaSekolahTerlapor();
            }

            if ($pengalihan->unit_tujuan_id === null) {
                throw PelanggaranIntegritasData::unitTujuanWajib();
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'tujuan' => TujuanPengalihan::class,
            'kepala_sekolah_terlapor' => 'boolean',
            'ringkasan' => 'encrypted',
            'diterima_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Laporan, $this>
     */
    public function laporan(): BelongsTo
    {
        return $this->belongsTo(Laporan::class);
    }

    /**
     * Unit yang Kepala Sekolahnya menerima pengalihan.
     *
     * @return BelongsTo<Unit, $this>
     */
    public function unitTujuan(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_tujuan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikirim_oleh');
    }
}
