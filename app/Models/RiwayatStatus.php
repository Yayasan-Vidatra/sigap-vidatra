<?php

namespace App\Models;

use App\Enums\StatusLaporan;
use Database\Factories\RiwayatStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat perpindahan status laporan; oleh_user_id kosong bila diubah sistem atau pelapor.
 */
#[Table('riwayat_status')]
#[Fillable(['laporan_id', 'status_lama', 'status_baru', 'oleh_user_id', 'catatan'])]
class RiwayatStatus extends Model
{
    /** @use HasFactory<RiwayatStatusFactory> */
    use HasFactory;

    public const CREATED_AT = 'waktu';

    public const UPDATED_AT = null;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status_lama' => StatusLaporan::class,
            'status_baru' => StatusLaporan::class,
            'catatan' => 'encrypted',
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
    public function oleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh_user_id');
    }
}
