<?php

namespace App\Models;

use App\Enums\JenisLaporan;
use App\Exceptions\PelanggaranIntegritasData;
use Database\Factories\TerlaporWbsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pihak terlapor pada laporan WBS, terenkripsi.
 */
#[Table('terlapor_wbs')]
#[Fillable(['laporan_id', 'nama', 'jabatan'])]
class TerlaporWbs extends Model
{
    /** @use HasFactory<TerlaporWbsFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diperbarui_pada';

    protected static function booted(): void
    {
        static::saving(function (TerlaporWbs $terlapor): void {
            if (! $terlapor->laporan->isWbs()) {
                throw PelanggaranIntegritasData::hanyaUntukJenis('Terlapor WBS', JenisLaporan::Wbs);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nama' => 'encrypted',
            'jabatan' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Laporan, $this>
     */
    public function laporan(): BelongsTo
    {
        return $this->belongsTo(Laporan::class);
    }
}
