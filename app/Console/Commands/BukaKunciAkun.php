<?php

namespace App\Console\Commands;

use App\Enums\AksiAudit;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Membuka akun yang terkunci setelah 5 kali gagal login. Dipakai sampai pembukaan kunci tersedia di
 * Pengaturan (H-19); setiap pembukaan tercatat di log audit.
 */
#[Signature('sigap:buka-kunci-akun {email : Surel akun yang terkunci}')]
#[Description('Membuka kunci akun petugas yang terkunci setelah 5 kali gagal login')]
class BukaKunciAkun extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('Akun dengan surel itu tidak ditemukan.');

            return self::FAILURE;
        }

        if (! $user->terkunci()) {
            $this->info('Akun ini tidak terkunci.');

            return self::SUCCESS;
        }

        $user->bukaKunci();
        LogAudit::catat(null, AksiAudit::KunciAkunDibuka, $user);

        $this->info('Kunci akun sudah dibuka.');

        return self::SUCCESS;
    }
}
