<?php

namespace App\Models;

use App\Enums\JenisLaporan;
use App\Exceptions\PelanggaranIntegritasData;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\KategoriFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori pengaduan (jenis = pengaduan) atau jenis pelanggaran (jenis = wbs).
 *
 * SLA dalam hari kerja Senin–Jumat dan hanya ada pada kategori pengaduan; tenggat WBS mengikuti
 * tahap (App\Enums\TahapWbs). Libur nasional belum dihitung (lihat prd/log-deviasi.md).
 */
#[Table('kategori')]
#[Fillable(['jenis', 'nama', 'sla_tanggapan_hari', 'sla_hari'])]
class Kategori extends Model
{
    /** @use HasFactory<KategoriFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diperbarui_pada';

    protected static function booted(): void
    {
        static::saving(function (Kategori $kategori): void {
            $punyaSla = $kategori->sla_tanggapan_hari !== null || $kategori->sla_hari !== null;
            $slaLengkap = $kategori->sla_tanggapan_hari !== null && $kategori->sla_hari !== null;

            $sesuai = $kategori->jenis === JenisLaporan::Pengaduan ? $slaLengkap : ! $punyaSla;

            if (! $sesuai) {
                throw PelanggaranIntegritasData::slaKategoriTidakSesuai($kategori->jenis);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisLaporan::class,
            'sla_tanggapan_hari' => 'integer',
            'sla_hari' => 'integer',
        ];
    }

    /**
     * Batas tanggapan awal pengaduan, dihitung dari waktu laporan diterima.
     */
    public function batasTanggapan(CarbonInterface $diterimaPada): CarbonImmutable
    {
        return CarbonImmutable::instance($diterimaPada)->addWeekdays($this->slaPengaduan('sla_tanggapan_hari'));
    }

    /**
     * Batas selesai pengaduan, dihitung dari waktu laporan diterima.
     */
    public function batasSelesai(CarbonInterface $diterimaPada): CarbonImmutable
    {
        return CarbonImmutable::instance($diterimaPada)->addWeekdays($this->slaPengaduan('sla_hari'));
    }

    private function slaPengaduan(string $kolom): int
    {
        if ($this->jenis !== JenisLaporan::Pengaduan) {
            throw PelanggaranIntegritasData::slaKategoriTidakSesuai($this->jenis);
        }

        return $this->{$kolom};
    }

    /**
     * @return HasMany<Laporan, $this>
     */
    public function laporan(): HasMany
    {
        return $this->hasMany(Laporan::class);
    }
}
