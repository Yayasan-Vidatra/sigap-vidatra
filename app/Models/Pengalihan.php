<?php

namespace App\Models;

use App\Enums\TujuanPengalihan;
use Database\Factories\PengalihanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengalihan laporan ke Kepala Sekolah, Komite Etik, atau Pokja BSAN (UC-19). Ringkasan terenkripsi.
 */
#[Table('pengalihan')]
#[Fillable(['laporan_id', 'tujuan', 'ringkasan', 'dikirim_oleh', 'diterima_pada'])]
class Pengalihan extends Model
{
    /** @use HasFactory<PengalihanFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diperbarui_pada';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'tujuan' => TujuanPengalihan::class,
            'ringkasan' => 'encrypted',
            'diterima_pada' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikirim_oleh');
    }
}
