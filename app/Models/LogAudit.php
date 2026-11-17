<?php

namespace App\Models;

use App\Exceptions\PelanggaranIntegritasData;
use Database\Factories\LogAuditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log audit yang hanya bisa ditambah (NF-07). Model menolak update dan delete; trigger di basis data
 * menolak hal yang sama untuk query di luar Eloquent.
 */
#[Table('log_audit')]
#[Fillable(['user_id', 'aksi', 'objek'])]
class LogAudit extends Model
{
    /** @use HasFactory<LogAuditFactory> */
    use HasFactory;

    public const CREATED_AT = 'waktu';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw PelanggaranIntegritasData::logAuditHanyaBisaDitambah();
        });

        static::deleting(function (): never {
            throw PelanggaranIntegritasData::logAuditHanyaBisaDitambah();
        });
    }

    /**
     * Mencatat aksi terhadap sebuah model, dengan objek berformat "{tabel}:{id}".
     */
    public static function catat(?User $user, string $aksi, Model $objek): self
    {
        return self::create([
            'user_id' => $user?->getKey(),
            'aksi' => $aksi,
            'objek' => $objek->getTable().':'.$objek->getKey(),
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
