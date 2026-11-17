<?php

namespace App\Models;

use App\Enums\PengirimPesan;
use Database\Factories\PesanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pesan dua arah dengan pelapor; internal = true adalah catatan internal petugas. Isi terenkripsi.
 */
#[Table('pesan')]
#[Fillable(['laporan_id', 'pengirim', 'isi', 'internal'])]
class Pesan extends Model
{
    /** @use HasFactory<PesanFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = null;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'pengirim' => PengirimPesan::class,
            'isi' => 'encrypted',
            'internal' => 'boolean',
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
     * @return HasMany<Lampiran, $this>
     */
    public function lampiran(): HasMany
    {
        return $this->hasMany(Lampiran::class);
    }
}
