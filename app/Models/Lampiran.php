<?php

namespace App\Models;

use App\Enums\StatusPindai;
use Database\Factories\LampiranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metadata lampiran laporan atau pesan (Rancangan r1 Bagian 8).
 */
#[Table('lampiran')]
#[Fillable(['laporan_id', 'pesan_id', 'nama_asli', 'path_acak', 'mime', 'ukuran', 'hash_sha256', 'status_pindai'])]
class Lampiran extends Model
{
    /** @use HasFactory<LampiranFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diperbarui_pada';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'ukuran' => 'integer',
            'status_pindai' => StatusPindai::class,
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
     * @return BelongsTo<Pesan, $this>
     */
    public function pesan(): BelongsTo
    {
        return $this->belongsTo(Pesan::class);
    }
}
