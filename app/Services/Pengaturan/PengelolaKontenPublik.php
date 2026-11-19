<?php

namespace App\Services\Pengaturan;

use App\Enums\Peran;
use App\Models\Pengaturan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Teks halaman publik dan berkas jaminan kerahasiaan (H-19 > H-03, H-08; US-25). Hanya Super Admin. Setiap kunci
 * yang berubah dicatat sebagai `mengubah_pengaturan` pada `pengaturan:{kunci}` dengan nilai lama dan baru. Berkas
 * jaminan lama dihapus dari disk privat setelah penggantinya tersimpan.
 */
class PengelolaKontenPublik
{
    /** Disk dan folder berkas jaminan kerahasiaan; nama berkas acak dari Livewire (hashName). */
    public const DISK = 'local';

    public const FOLDER_BERKAS = 'pengaturan';

    /** Kunci teks biasa. */
    public const KUNCI_TEKS = [
        Pengaturan::WBS_SALURAN_SUREL,
        Pengaturan::WBS_SALURAN_TELEPON,
        Pengaturan::WBS_SALURAN_SURAT,
    ];

    /** Kunci daftar (JSON) beserta kolom setiap butirnya. */
    public const KUNCI_DAFTAR = [
        Pengaturan::PUBLIK_FAQ => ['pertanyaan', 'jawaban'],
        Pengaturan::PUBLIK_PRIVASI => ['judul', 'isi'],
    ];

    public function __construct(private PencatatPerubahanPengaturan $pencatat) {}

    /**
     * Nilai yang tersimpan sekarang, dalam bentuk isian formulir Konten Publik.
     *
     * @return array<string, mixed>
     */
    public static function isian(): array
    {
        $isian = [Pengaturan::WBS_BERKAS_JAMINAN => Pengaturan::nilai(Pengaturan::WBS_BERKAS_JAMINAN)];

        foreach (self::KUNCI_TEKS as $kunci) {
            $isian[$kunci] = Pengaturan::nilai($kunci);
        }

        foreach (array_keys(self::KUNCI_DAFTAR) as $kunci) {
            $isian[$kunci] = Pengaturan::daftar($kunci);
        }

        return $isian;
    }

    /**
     * @param  array<string, mixed>  $data  isian dengan kunci Pengaturan; kunci yang tidak ada dibiarkan
     */
    public function simpan(User $oleh, array $data): void
    {
        if ($oleh->peran !== Peran::SuperAdmin) {
            throw new AuthorizationException('Hanya Super Admin yang dapat mengubah pengaturan.');
        }

        $lama = self::isian();
        $berkasLama = null;

        DB::transaction(function () use ($oleh, $data, $lama, &$berkasLama): void {
            foreach ($lama as $kunci => $nilaiLama) {
                if (! array_key_exists($kunci, $data)) {
                    continue;
                }

                $nilaiBaru = self::normalkan($kunci, $data[$kunci]);

                if ($nilaiBaru === $nilaiLama) {
                    continue;
                }

                if ($kunci === Pengaturan::WBS_BERKAS_JAMINAN && is_string($nilaiBaru)) {
                    self::pastikanBerkasSah($nilaiBaru);
                }

                Pengaturan::query()->updateOrCreate(['kunci' => $kunci], [
                    'nilai' => is_array($nilaiBaru) ? json_encode($nilaiBaru, JSON_UNESCAPED_UNICODE) : $nilaiBaru,
                ]);

                $this->pencatat->catat($oleh, 'pengaturan:'.$kunci, ['nilai' => $nilaiLama], ['nilai' => $nilaiBaru]);

                if ($kunci === Pengaturan::WBS_BERKAS_JAMINAN) {
                    $berkasLama = $nilaiLama;
                }
            }
        });

        if (is_string($berkasLama) && str_starts_with($berkasLama, self::FOLDER_BERKAS.'/') && ! str_contains($berkasLama, '..')) {
            Storage::disk(self::DISK)->delete($berkasLama);
        }
    }

    /**
     * @return string|list<array<string, string>>|null
     */
    private static function normalkan(string $kunci, mixed $nilai): string|array|null
    {
        if (isset(self::KUNCI_DAFTAR[$kunci])) {
            $kolom = self::KUNCI_DAFTAR[$kunci];

            return collect(is_array($nilai) ? $nilai : [])
                ->map(fn (mixed $butir): array => collect($kolom)
                    ->mapWithKeys(fn (string $nama): array => [$nama => trim((string) (is_array($butir) ? ($butir[$nama] ?? '') : ''))])
                    ->all())
                ->reject(fn (array $butir): bool => implode('', $butir) === '')
                ->values()
                ->all();
        }

        if (is_array($nilai)) {
            $nilai = array_values($nilai)[0] ?? null;
        }

        $teks = trim((string) $nilai);

        return $teks === '' ? null : $teks;
    }

    /**
     * Berkas jaminan baru hanya boleh hasil unggahan halaman ini (nama acak di folder pengaturan pada disk privat),
     * agar path lain, misalnya lampiran laporan, tidak bisa disajikan di /wbs/jaminan-kerahasiaan. Isinya harus PDF
     * (diperiksa dari byte awal, bukan ekstensi); berkas yang bukan PDF dihapus lagi.
     *
     * @throws ValidationException
     */
    private static function pastikanBerkasSah(string $path): void
    {
        $disk = Storage::disk(self::DISK);

        if (preg_match('#\A'.self::FOLDER_BERKAS.'/[A-Za-z0-9]{20,}\.pdf\z#', $path) !== 1 || ! $disk->exists($path)) {
            throw new InvalidArgumentException('Berkas jaminan kerahasiaan harus berupa unggahan di folder pengaturan.');
        }

        $aliran = $disk->readStream($path);
        $awal = is_resource($aliran) ? (string) fread($aliran, 5) : '';

        if (is_resource($aliran)) {
            fclose($aliran);
        }

        if ($awal !== '%PDF-') {
            $disk->delete($path);

            throw ValidationException::withMessages([Pengaturan::WBS_BERKAS_JAMINAN => 'Berkas jaminan kerahasiaan harus berupa PDF.']);
        }
    }
}
