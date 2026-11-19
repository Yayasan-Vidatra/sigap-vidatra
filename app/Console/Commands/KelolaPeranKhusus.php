<?php

namespace App\Console\Commands;

use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Models\User;
use App\Services\Pengaturan\DasarPersetujuan;
use App\Services\Pengaturan\PengelolaPeranKhusus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Memberi, mencabut, atau mengatur ulang 2FA peran Tim WBS, penerima hasil, dan Super Admin (Rancangan r1 Bagian 9)
 * dengan persetujuan tertulis Ketua Dewan Pengawas. Peran ini tidak bisa diberikan lewat Pengaturan (H-19); alur
 * persetujuan di dalam sistem menyusul di Tahap 2.
 */
#[Signature('sigap:peran-khusus
    {aksi : beri, cabut, atau atur-ulang-2fa}
    {email : Surel akun lokal}
    {peran? : Peran yang diberikan (hanya untuk beri), misalnya ketua_tim_wbs}
    {--nomor-persetujuan= : Nomor persetujuan tertulis Ketua Dewan Pengawas}
    {--tanggal-persetujuan= : Tanggal persetujuan, format YYYY-MM-DD}
    {--nama= : Untuk beri: buat akun lokal baru dengan nama ini bila surel belum terdaftar}')]
#[Description('Memberi/mencabut peran Tim WBS, penerima hasil, Super Admin, atau mengatur ulang 2FA-nya, dengan persetujuan Ketua Dewan Pengawas')]
class KelolaPeranKhusus extends Command
{
    public function handle(PengelolaPeranKhusus $pengelola): int
    {
        try {
            $dasar = new DasarPersetujuan($this->option('nomor-persetujuan'), $this->option('tanggal-persetujuan'));
            $aksi = (string) $this->argument('aksi');
            $email = Str::lower(trim((string) $this->argument('email')));
            $pelaksana = 'artisan oleh '.(getenv('USER') ?: getenv('USERNAME') ?: get_current_user());

            match ($aksi) {
                'beri' => $this->beri($pengelola, $email, $dasar, $pelaksana),
                'cabut' => $pengelola->cabut($this->akun($email), $dasar, $pelaksana),
                'atur-ulang-2fa' => $pengelola->aturUlang2fa($this->akun($email), $dasar, $pelaksana),
                default => throw new InvalidArgumentException('Aksi harus beri, cabut, atau atur-ulang-2fa.'),
            };
        } catch (InvalidArgumentException $galat) {
            $this->error($galat->getMessage());

            return self::FAILURE;
        }

        $this->info('Selesai. Tindakan tercatat di log audit dan pemegang peran diberi tahu lewat surel.');

        return self::SUCCESS;
    }

    private function beri(PengelolaPeranKhusus $pengelola, string $email, DasarPersetujuan $dasar, string $pelaksana): void
    {
        $peran = Peran::tryFrom((string) $this->argument('peran'));

        if ($peran === null || ! $peran->butuhPersetujuanPengawas()) {
            $pilihan = collect(Peran::cases())->filter->butuhPersetujuanPengawas()->map->value->implode(', ');

            throw new InvalidArgumentException("Peran wajib salah satu dari: {$pilihan}.");
        }

        $pengguna = User::query()->where('email', $email)->first();
        $kataSandiAwal = null;

        if ($pengguna === null) {
            $nama = trim((string) $this->option('nama'));

            if ($nama === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Akun belum terdaftar. Isi --nama dan surel yang sah untuk membuat akun lokal baru.');
            }

            $kataSandiAwal = Str::password(20);
            $pengguna = new User(['name' => $nama, 'email' => $email, 'password' => $kataSandiAwal, 'jenis_akun' => JenisAkun::Lokal]);
        }

        $pengelola->beri($pengguna, $peran, $dasar, $pelaksana);

        if ($kataSandiAwal !== null) {
            $this->warn("Akun baru dibuat. Kata sandi awal (tampil sekali, serahkan langsung kepada pemegang akun): {$kataSandiAwal}");
        }
    }

    private function akun(string $email): User
    {
        return User::query()->where('email', $email)->first()
            ?? throw new InvalidArgumentException('Akun dengan surel itu tidak ditemukan.');
    }
}
