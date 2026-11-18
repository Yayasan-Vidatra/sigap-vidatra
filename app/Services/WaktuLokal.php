<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Waktu lokal Yayasan (WITA). Basis data menyimpan UTC (app.timezone); kelas ini mengubahnya untuk
 * tampilan dan menghitung hari kerja menurut tanggal WITA, sehingga laporan yang masuk pukul 07.46 WITA
 * hari Rabu dihitung dari Rabu, bukan dari Selasa (tanggal UTC-nya).
 */
class WaktuLokal
{
    public static function zona(): string
    {
        return (string) config('sigap.zona_waktu.nama');
    }

    public static function label(): string
    {
        return (string) config('sigap.zona_waktu.label');
    }

    public static function keLokal(CarbonInterface $waktu): CarbonImmutable
    {
        return CarbonImmutable::instance($waktu)->setTimezone(self::zona());
    }

    /**
     * Teks waktu berbahasa Indonesia dalam WITA beserta labelnya, mis. "Rabu, 18 November 2026, 07.46 WITA".
     */
    public static function tampilkan(CarbonInterface $waktu, string $format = 'l, d F Y, H.i'): string
    {
        return self::keLokal($waktu)->locale('id')->translatedFormat($format).' '.self::label();
    }

    /**
     * Menambah hari kerja (Senin–Jumat) menurut kalender WITA. Hasilnya dikembalikan dalam zona waktu
     * aplikasi agar tersimpan benar di basis data. Libur nasional belum dihitung (prd/log-deviasi.md).
     */
    public static function tambahHariKerja(CarbonInterface $mulai, int $hari): CarbonImmutable
    {
        return self::keLokal($mulai)->addWeekdays($hari)->setTimezone(config('app.timezone'));
    }

    /**
     * Waktu yang diisi pelapor atau petugas tanpa zona (mis. "2026-11-18T07:46" dari isian datetime-local)
     * dibaca sebagai WITA, lalu diubah ke zona waktu aplikasi untuk disimpan.
     */
    public static function dariMasukan(string $waktu): CarbonImmutable
    {
        return CarbonImmutable::parse($waktu, self::zona())->setTimezone(config('app.timezone'));
    }

    /**
     * Waktu sekarang dalam WITA tanpa zona, untuk dibandingkan dengan masukan pengguna di aturan validasi.
     */
    public static function sekarangSebagaiMasukan(): string
    {
        return CarbonImmutable::now(self::zona())->format('Y-m-d H:i:s');
    }

    /**
     * Awal hari sebuah tanggal WITA (Y-m-d), dalam zona waktu aplikasi, untuk filter tanggal.
     */
    public static function awalHari(string $tanggal): CarbonImmutable
    {
        return CarbonImmutable::parse($tanggal, self::zona())->startOfDay()->setTimezone(config('app.timezone'));
    }

    /**
     * Akhir hari sebuah tanggal WITA (Y-m-d), dalam zona waktu aplikasi, untuk filter tanggal.
     */
    public static function akhirHari(string $tanggal): CarbonImmutable
    {
        return CarbonImmutable::parse($tanggal, self::zona())->endOfDay()->setTimezone(config('app.timezone'));
    }
}
