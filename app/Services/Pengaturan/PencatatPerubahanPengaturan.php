<?php

namespace App\Services\Pengaturan;

use App\Enums\AksiAudit;
use App\Models\LogAudit;
use App\Models\RiwayatPengaturan;
use App\Models\User;
use BackedEnum;

/**
 * Mencatat perubahan Pengaturan (H-19): satu baris log audit (bawaan `mengubah_pengaturan`) per objek yang berubah, dengan
 * nilai lama dan baru setiap kolom di riwayat_pengaturan. Kolom rahasia hanya tercatat bahwa ia diubah.
 * Dipanggil di dalam transaksi yang sama dengan perubahannya.
 */
class PencatatPerubahanPengaturan
{
    /**
     * @param  string  $objek  rujukan berformat "{tabel}:{id}" atau "pengaturan:{kunci}"
     * @param  array<string, mixed>  $lama
     * @param  array<string, mixed>  $baru
     * @param  list<string>  $rahasia  kolom yang nilainya tidak boleh disimpan (kata sandi, rahasia 2FA)
     * @param  User|null  $oleh  null bila dijalankan lewat perintah artisan di server
     */
    public function catat(?User $oleh, string $objek, array $lama, array $baru, array $rahasia = [], AksiAudit $aksi = AksiAudit::MengubahPengaturan): ?LogAudit
    {
        $berubah = [];

        foreach (array_unique([...array_keys($lama), ...array_keys($baru)]) as $kolom) {
            $nilaiBaru = self::teks($baru[$kolom] ?? null);

            if (in_array($kolom, $rahasia, true)) {
                if ($nilaiBaru !== null) {
                    $berubah[$kolom] = [null, RiwayatPengaturan::NILAI_RAHASIA];
                }

                continue;
            }

            $nilaiLama = self::teks($lama[$kolom] ?? null);

            if ($nilaiLama !== $nilaiBaru) {
                $berubah[$kolom] = [$nilaiLama, $nilaiBaru];
            }
        }

        if ($berubah === []) {
            return null;
        }

        $log = LogAudit::catatObjek($oleh, $aksi, $objek);

        foreach ($berubah as $kolom => [$nilaiLama, $nilaiBaru]) {
            $log->riwayatPengaturan()->create([
                'kolom' => $kolom,
                'nilai_lama' => $nilaiLama,
                'nilai_baru' => $nilaiBaru,
            ]);
        }

        return $log;
    }

    private static function teks(mixed $nilai): ?string
    {
        $teks = match (true) {
            $nilai === null => null,
            $nilai instanceof BackedEnum => (string) $nilai->value,
            is_bool($nilai) => $nilai ? 'ya' : 'tidak',
            is_array($nilai) => $nilai === [] ? null : json_encode($nilai, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => trim((string) $nilai),
        };

        return $teks === '' ? null : $teks;
    }
}
