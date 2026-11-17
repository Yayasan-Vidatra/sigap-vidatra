<?php

namespace App\Models;

use App\Casts\TerenkripsiBilaWbs;
use App\Enums\JenisLaporan;
use App\Enums\KedudukanTerlapor;
use App\Enums\Prioritas;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Enums\TujuanPengalihan;
use App\Exceptions\PelanggaranIntegritasData;
use Database\Factories\LaporanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Laporan bersama kanal Pengaduan dan WBS (Rancangan r1 Bagian 10).
 */
#[Table('laporan')]
#[Fillable([
    'kode_lacak', 'jenis', 'sumber', 'unit_id', 'kategori_id', 'judul', 'kronologi', 'waktu_kejadian',
    'lokasi', 'kedudukan_terlapor', 'status', 'prioritas', 'batas_waktu', 'dialihkan_ke',
    'perpanjangan_disetujui_oleh', 'pin_hash',
])]
#[Hidden(['pin_hash'])]
class Laporan extends Model
{
    /** @use HasFactory<LaporanFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diperbarui_pada';

    protected static function booted(): void
    {
        static::saving(function (Laporan $laporan): void {
            if ($laporan->exists && $laporan->isDirty('jenis')) {
                throw PelanggaranIntegritasData::jenisLaporanTidakBolehBerubah();
            }

            if ($laporan->status !== null && ! $laporan->status->berlakuUntuk($laporan->jenis)) {
                throw PelanggaranIntegritasData::statusTidakBerlaku($laporan->status, $laporan->jenis);
            }
        });
    }

    /**
     * Mengisi jenis lebih dulu, karena cast TerenkripsiBilaWbs membaca jenis untuk memutuskan enkripsi.
     *
     * @param  array<string, mixed>  $attributes
     * @return $this
     */
    public function fill(array $attributes)
    {
        if (array_key_exists('jenis', $attributes)) {
            $attributes = ['jenis' => $attributes['jenis']] + $attributes;
        }

        return parent::fill($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisLaporan::class,
            'sumber' => SumberLaporan::class,
            'judul' => TerenkripsiBilaWbs::class,
            'kronologi' => TerenkripsiBilaWbs::class,
            'lokasi' => TerenkripsiBilaWbs::class,
            'waktu_kejadian' => 'datetime',
            'kedudukan_terlapor' => KedudukanTerlapor::class,
            'status' => StatusLaporan::class,
            'prioritas' => Prioritas::class,
            'batas_waktu' => 'datetime',
            'dialihkan_ke' => TujuanPengalihan::class,
            'pin_hash' => 'hashed',
        ];
    }

    public function isWbs(): bool
    {
        return $this->jenis === JenisLaporan::Wbs;
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<Kategori, $this>
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function perpanjanganDisetujuiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'perpanjangan_disetujui_oleh');
    }

    /**
     * @return HasOne<IdentitasPelapor, $this>
     */
    public function identitasPelapor(): HasOne
    {
        return $this->hasOne(IdentitasPelapor::class);
    }

    /**
     * @return HasMany<TerlaporWbs, $this>
     */
    public function terlapor(): HasMany
    {
        return $this->hasMany(TerlaporWbs::class);
    }

    /**
     * @return HasMany<Lampiran, $this>
     */
    public function lampiran(): HasMany
    {
        return $this->hasMany(Lampiran::class);
    }

    /**
     * @return HasMany<Pesan, $this>
     */
    public function pesan(): HasMany
    {
        return $this->hasMany(Pesan::class);
    }

    /**
     * @return HasMany<RiwayatStatus, $this>
     */
    public function riwayatStatus(): HasMany
    {
        return $this->hasMany(RiwayatStatus::class);
    }

    /**
     * @return HasMany<Disposisi, $this>
     */
    public function disposisi(): HasMany
    {
        return $this->hasMany(Disposisi::class);
    }

    /**
     * @return HasMany<Pengalihan, $this>
     */
    public function pengalihan(): HasMany
    {
        return $this->hasMany(Pengalihan::class);
    }
}
