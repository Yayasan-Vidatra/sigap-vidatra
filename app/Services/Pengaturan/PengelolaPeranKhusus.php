<?php

namespace App\Services\Pengaturan;

use App\Enums\AksiAudit;
use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Mail\PemberitahuanPeranKhusus;
use App\Mail\TautanBuatKataSandi;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Memberi, mencabut, dan mengatur ulang 2FA peran Tim WBS, penerima hasil, dan Super Admin (Rancangan r1 Bagian 9).
 * Hanya untuk akun lokal, selalu dengan dasar persetujuan tertulis Ketua Dewan Pengawas. Setiap tindakan tercatat
 * di log audit (objek `users:{id}`) dengan peran lama/baru dan dasar persetujuan di riwayat_pengaturan, lalu pemegang
 * peran diberi tahu lewat surel tanpa isi laporan. Dijalankan lewat `php artisan sigap:peran-khusus`; alur
 * persetujuan di dalam sistem menyusul di Tahap 2.
 */
class PengelolaPeranKhusus
{
    public function __construct(private PencatatPerubahanPengaturan $pencatat) {}

    public function beri(User $pengguna, Peran $peran, DasarPersetujuan $dasar, string $pelaksana): void
    {
        self::pastikanAkunLokal($pengguna);

        if (! $peran->butuhPersetujuanPengawas()) {
            throw new InvalidArgumentException("Peran {$peran->getLabel()} dikelola Super Admin di Pengaturan, bukan lewat perintah ini.");
        }

        if ($pengguna->peran === $peran) {
            throw new InvalidArgumentException("Akun ini sudah memegang peran {$peran->getLabel()}.");
        }

        $this->jalankan($pengguna, AksiAudit::MemberiPeranKhusus, $dasar, $pelaksana, function () use ($pengguna, $peran): void {
            $pengguna->forceFill(['peran' => $peran, 'unit_id' => null])->save();
        });
    }

    public function cabut(User $pengguna, DasarPersetujuan $dasar, string $pelaksana): void
    {
        self::pastikanAkunLokal($pengguna);
        self::pastikanPemegangPeranKhusus($pengguna);

        $this->jalankan($pengguna, AksiAudit::MencabutPeranKhusus, $dasar, $pelaksana, function () use ($pengguna): void {
            $pengguna->forceFill(['peran' => null, 'unit_id' => null])->save();
            self::putuskanSesi($pengguna);
        });
    }

    /**
     * Menghapus rahasia dan kode pemulihan 2FA serta memutus sesi; pemegang peran wajib memasang 2FA lagi saat login.
     */
    public function aturUlang2fa(User $pengguna, DasarPersetujuan $dasar, string $pelaksana): void
    {
        self::pastikanAkunLokal($pengguna);
        self::pastikanPemegangPeranKhusus($pengguna);

        $this->jalankan($pengguna, AksiAudit::MengaturUlang2fa, $dasar, $pelaksana, function () use ($pengguna): void {
            $pengguna->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save();
            self::putuskanSesi($pengguna);
        });
    }

    /**
     * Mengirim tautan sekali pakai untuk membuat kata sandi (broker kata sandi Laravel, halaman buat kata sandi Filament).
     * Tautan dan tokennya tidak ditampilkan maupun dicatat; log audit hanya mencatat bahwa tautan dikirim.
     */
    public function kirimTautanKataSandi(User $pengguna, DasarPersetujuan $dasar, string $pelaksana): void
    {
        self::pastikanAkunLokal($pengguna);
        self::pastikanPemegangPeranKhusus($pengguna);

        DB::transaction(function () use ($pengguna, $dasar, $pelaksana): void {
            $this->pencatat->catat(
                null,
                'users:'.$pengguna->getKey(),
                [],
                ['dasar_persetujuan' => $dasar->teks(), 'pelaksana' => $pelaksana],
                aksi: AksiAudit::MengirimTautanKataSandi,
            );
        });

        $token = Password::broker()->createToken($pengguna);

        Mail::to($pengguna->email)->send(new TautanBuatKataSandi(
            $pengguna->peran,
            Filament::getPanel('panel')->getResetPasswordUrl($token, $pengguna),
            (int) config('auth.passwords.users.expire'),
        ));
    }

    /**
     * @param  callable(): void  $ubah
     */
    private function jalankan(User $pengguna, AksiAudit $aksi, DasarPersetujuan $dasar, string $pelaksana, callable $ubah): void
    {
        $peranLama = $pengguna->peran;

        DB::transaction(function () use ($pengguna, $aksi, $dasar, $pelaksana, $ubah, $peranLama): void {
            $ubah();

            $this->pencatat->catat(
                null,
                'users:'.$pengguna->getKey(),
                ['peran' => $peranLama],
                [
                    'peran' => $pengguna->peran,
                    'dasar_persetujuan' => $dasar->teks(),
                    'pelaksana' => $pelaksana,
                    '2fa' => $aksi === AksiAudit::MengaturUlang2fa ? 'diatur ulang' : null,
                ],
                rahasia: ['2fa'],
                aksi: $aksi,
            );

            Mail::to($pengguna->email)->send(new PemberitahuanPeranKhusus(
                $aksi,
                $aksi === AksiAudit::MencabutPeranKhusus ? $peranLama : $pengguna->peran,
                $dasar->teks(),
                url('/panel/login'),
            ));
        });
    }

    private static function pastikanAkunLokal(User $pengguna): void
    {
        if ($pengguna->jenis_akun !== JenisAkun::Lokal) {
            throw new InvalidArgumentException('Peran Tim WBS, penerima hasil, dan Super Admin hanya untuk akun lokal, bukan akun SSO (Rancangan r1 Bagian 9).');
        }
    }

    private static function pastikanPemegangPeranKhusus(User $pengguna): void
    {
        if (! ($pengguna->peran?->butuhPersetujuanPengawas() ?? false)) {
            throw new InvalidArgumentException('Akun ini tidak memegang peran Tim WBS, penerima hasil, atau Super Admin.');
        }
    }

    private static function putuskanSesi(User $pengguna): void
    {
        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $pengguna->getKey())->delete();
        }
    }
}
