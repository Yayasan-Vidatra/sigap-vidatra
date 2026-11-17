<?php

namespace App\Models;

use App\Enums\JenisLaporan;
use App\Exceptions\PelanggaranIntegritasData;
use Database\Factories\DisposisiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Disposisi pengaduan ke unit dan PIC (UC-09). Laporan WBS tidak pernah diteruskan ke unit
 * (Rancangan r1 Bagian 2).
 */
#[Table('disposisi')]
#[Fillable(['laporan_id', 'unit_id', 'pic_user_id', 'instruksi', 'tindak_lanjut', 'selesai_pada'])]
class Disposisi extends Model
{
    /** @use HasFactory<DisposisiFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diperbarui_pada';

    protected static function booted(): void
    {
        static::saving(function (Disposisi $disposisi): void {
            if ($disposisi->laporan->isWbs()) {
                throw PelanggaranIntegritasData::hanyaUntukJenis('Disposisi', JenisLaporan::Pengaduan);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'selesai_pada' => 'datetime',
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
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }
}
