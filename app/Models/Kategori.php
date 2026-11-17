<?php

namespace App\Models;

use App\Enums\JenisLaporan;
use Database\Factories\KategoriFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori pengaduan (jenis = pengaduan) atau jenis pelanggaran (jenis = wbs) beserta SLA-nya.
 */
#[Table('kategori')]
#[Fillable(['jenis', 'nama', 'sla_hari'])]
class Kategori extends Model
{
    /** @use HasFactory<KategoriFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diperbarui_pada';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisLaporan::class,
            'sla_hari' => 'integer',
        ];
    }

    /**
     * @return HasMany<Laporan, $this>
     */
    public function laporan(): HasMany
    {
        return $this->hasMany(Laporan::class);
    }
}
