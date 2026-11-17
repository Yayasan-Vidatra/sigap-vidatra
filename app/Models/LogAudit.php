<?php

namespace App\Models;

use App\Enums\AksiAudit;
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
     * Objek untuk percobaan login dengan surel yang tidak terdaftar; surel itu sendiri tidak disimpan.
     */
    public const OBJEK_PENGGUNA_TIDAK_TERDAFTAR = 'users:tidak_terdaftar';

    /**
     * Mencatat aksi terhadap sebuah model, dengan objek berformat "{tabel}:{id}".
     */
    public static function catat(?User $user, AksiAudit|string $aksi, Model $objek): self
    {
        return self::catatObjek($user, $aksi, $objek->getTable().':'.$objek->getKey());
    }

    /**
     * Mencatat aksi dengan rujukan objek yang sudah berbentuk teks, misalnya OBJEK_PENGGUNA_TIDAK_TERDAFTAR.
     */
    public static function catatObjek(?User $user, AksiAudit|string $aksi, string $objek): self
    {
        return self::create([
            'user_id' => $user?->getKey(),
            'aksi' => $aksi instanceof AksiAudit ? $aksi->value : $aksi,
            'objek' => $objek,
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
