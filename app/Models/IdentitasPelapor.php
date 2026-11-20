<?php

namespace App\Models;

use Database\Factories\IdentitasPelaporFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Identitas pelapor, terenkripsi untuk semua baris. Laporan WBS lama yang anonim tidak punya baris; sejak revisi 20-11
 * Formulir WBS mewajibkan nama, surel, dan nomor HP/WA.
 */
#[Table('identitas_pelapor')]
#[Fillable(['laporan_id', 'nama', 'email', 'no_hp', 'media_sosial', 'kontak_orang_tua', 'persetujuan_orang_tua'])]
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
            'media_sosial' => 'encrypted',
            'kontak_orang_tua' => 'encrypted',
            'persetujuan_orang_tua' => 'boolean',
        ];
    }

    /**
     * Alamat surel pelapor untuk notifikasi (F-12): surel pelapor, atau kontak orang tua/wali bila berupa surel
     * (pelapor siswa, UC-01 1a). null bila pelapor hanya memberi nomor HP; surel hanya dikirim bila ada.
     */
    public function alamatSurel(): ?string
    {
        foreach ([$this->email, $this->kontak_orang_tua] as $calon) {
            if (is_string($calon) && filter_var($calon, FILTER_VALIDATE_EMAIL)) {
                return $calon;
            }
        }

        return null;
    }

    /**
     * @return BelongsTo<Laporan, $this>
     */
    public function laporan(): BelongsTo
    {
        return $this->belongsTo(Laporan::class);
    }
}
