<?php

namespace App\Models;

use Database\Factories\PengaturanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan berbentuk kunci–nilai untuk teks halaman publik dan berkas jaminan kerahasiaan (aturan proyek
 * no. 7). Dikelola Super Admin lewat Pengaturan > Konten Publik (H-19) dan dicatat PengelolaKontenPublik.
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
     * Kode akses kanal WBS internal (revisi 20-11): hanya hash (Hash::make), tidak pernah nilai aslinya. Dikelola
     * lewat App\Services\Wbs\KodeAksesWbs, bukan Konten Publik.
     */
    public const WBS_KODE_AKSES = 'wbs.kode_akses_hash';

    /** Petunjuk kode akses yang tampil di bawah isian kode di /wbs. */
    public const WBS_PETUNJUK_KODE_AKSES = 'wbs.petunjuk_kode_akses';

    /** Tanya jawab H-08 (/faq): JSON daftar {pertanyaan, jawaban}. */
    public const PUBLIK_FAQ = 'publik.faq';

    /** Kebijakan Privasi H-08 (/privasi): JSON daftar {judul, isi}. */
    public const PUBLIK_PRIVASI = 'publik.privasi';

    /**
     * Nilai pengaturan, atau null bila belum diisi atau kosong.
     */
    public static function nilai(string $kunci): ?string
    {
        $nilai = self::query()->where('kunci', $kunci)->value('nilai');

        return is_string($nilai) && trim($nilai) !== '' ? trim($nilai) : null;
    }

    /**
     * Pengaturan berbentuk daftar (JSON), misalnya FAQ; daftar kosong bila belum diisi atau rusak.
     *
     * @return list<array<string, string>>
     */
    public static function daftar(string $kunci): array
    {
        $daftar = json_decode(self::nilai($kunci) ?? '[]', true);

        return is_array($daftar) ? array_values(array_filter($daftar, 'is_array')) : [];
    }
}
