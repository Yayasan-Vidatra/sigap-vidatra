<?php

namespace App\Models;

use Database\Factories\IdentitasPelaporFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Identitas pelapor, terenkripsi untuk semua baris. Tidak ada baris bila pelapor WBS anonim.
 */
#[Table('identitas_pelapor')]
#[Fillable(['laporan_id', 'nama', 'email', 'no_hp', 'kontak_orang_tua', 'persetujuan_orang_tua'])]
class IdentitasPelapor extends Model
{
    /** @use HasFactory<IdentitasPelaporFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diperbarui_pada';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nama' => 'encrypted',
            'email' => 'encrypted',
            'no_hp' => 'encrypted',
            'kontak_orang_tua' => 'encrypted',
            'persetujuan_orang_tua' => 'boolean',
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
