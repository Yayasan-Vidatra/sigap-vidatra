<?php

namespace App\Models;

use Database\Factories\PengaturanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan berbentuk kunci–nilai untuk teks halaman publik dan berkas jaminan kerahasiaan (aturan proyek
 * no. 7). Dikelola Super Admin lewat Pengaturan (H-19, Hari 13); sebelum itu halaman memakai teks bawaan.
 */
#[Table('pengaturan')]
#[Fillable(['kunci', 'nilai'])]
class Pengaturan extends Model
{
    /** @use HasFactory<PengaturanFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diperbarui_pada';

    /** Path berkas PDF jaminan kerahasiaan WBS di disk privat "local" (H-03). */
    public const WBS_BERKAS_JAMINAN = 'wbs.berkas_jaminan_kerahasiaan';

    /** Saluran alternatif WBS (F-03, H-03). */
    public const WBS_SALURAN_SUREL = 'wbs.saluran_surel';

    public const WBS_SALURAN_TELEPON = 'wbs.saluran_telepon';

    public const WBS_SALURAN_SURAT = 'wbs.saluran_surat';

    /**
     * Nilai pengaturan, atau null bila belum diisi atau kosong.
     */
    public static function nilai(string $kunci): ?string
    {
        $nilai = self::query()->where('kunci', $kunci)->value('nilai');

        return is_string($nilai) && trim($nilai) !== '' ? trim($nilai) : null;
    }
}
