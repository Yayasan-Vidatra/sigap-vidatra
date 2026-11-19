<?php

namespace App\Models;

use App\Exceptions\PelanggaranIntegritasData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nilai lama dan baru satu kolom pada satu perubahan Pengaturan (H-19), terikat pada baris log audit
 * `mengubah_pengaturan`. Hanya bisa ditambah, seperti log audit; nilai rahasia tidak pernah disimpan.
 */
#[Table('riwayat_pengaturan')]
#[Fillable(['log_audit_id', 'kolom', 'nilai_lama', 'nilai_baru'])]
class RiwayatPengaturan extends Model
{
    /** Pengganti nilai kolom rahasia (kata sandi): perubahannya tercatat, isinya tidak. */
    public const NILAI_RAHASIA = '[rahasia diubah]';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw PelanggaranIntegritasData::riwayatPengaturanHanyaBisaDitambah();
        });

        static::deleting(function (): never {
            throw PelanggaranIntegritasData::riwayatPengaturanHanyaBisaDitambah();
        });
    }

    /**
     * @return BelongsTo<LogAudit, $this>
     */
    public function logAudit(): BelongsTo
    {
        return $this->belongsTo(LogAudit::class);
    }
}
