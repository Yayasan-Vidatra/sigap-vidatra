<?php

namespace Database\Seeders;

use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Akun uji @sigap.test untuk mencoba panel di lokal: satu akun lokal per peran utama. Hanya berjalan di
 * environment local; di environment lain berhenti tanpa membuat apa pun. Kata sandi dibaca dari
 * AKUN_UJI_PASSWORD (config sigap.akun_uji.password), tidak pernah ditulis di kode.
 *
 * Tidak dipanggil dari DatabaseSeeder. Jalankan dengan: php artisan db:seed --class=AkunUjiSeeder
 * Aman dijalankan ulang: akun dicocokkan berdasarkan surel. 2FA dipasang saat login pertama.
 */
class AkunUjiSeeder extends Seeder
{
    /**
     * @var list<array{email: string, name: string, peran: Peran, unit: string|null}>
     */
    public const AKUN = [
        ['email' => 'superadmin@sigap.test', 'name' => 'Uji Super Admin', 'peran' => Peran::SuperAdmin, 'unit' => null],
        ['email' => 'admin@sigap.test', 'name' => 'Uji Admin Pengaduan Yayasan', 'peran' => Peran::AdminPengaduan, 'unit' => null],
        ['email' => 'admin.sd@sigap.test', 'name' => 'Uji Admin Sekolah SD', 'peran' => Peran::AdminPengaduan, 'unit' => 'SD YPVDP'],
        ['email' => 'pic.sd@sigap.test', 'name' => 'Uji PIC SD', 'peran' => Peran::PicUnit, 'unit' => 'SD YPVDP'],
        ['email' => 'pic.smp@sigap.test', 'name' => 'Uji PIC SMP', 'peran' => Peran::PicUnit, 'unit' => 'SMP YPVDP'],
        ['email' => 'pic.sma@sigap.test', 'name' => 'Uji PIC SMA', 'peran' => Peran::PicUnit, 'unit' => 'SMA YPVDP'],
        ['email' => 'pic.ph@sigap.test', 'name' => 'Uji PIC Pelaksana Harian', 'peran' => Peran::PicUnit, 'unit' => 'Pelaksana Harian'],
        ['email' => 'ketua.wbs@sigap.test', 'name' => 'Uji Ketua Tim WBS', 'peran' => Peran::KetuaTimWbs, 'unit' => null],
        ['email' => 'anggota.wbs@sigap.test', 'name' => 'Uji Anggota Tim WBS', 'peran' => Peran::AnggotaTimWbs, 'unit' => null],
        ['email' => 'dewas@sigap.test', 'name' => 'Uji Dewan Pengawas', 'peran' => Peran::DewanPengawas, 'unit' => null],
        ['email' => 'kepsek.sd@sigap.test', 'name' => 'Uji Kepala Sekolah SD', 'peran' => Peran::KepalaSekolah, 'unit' => 'SD YPVDP'],
        ['email' => 'etik@sigap.test', 'name' => 'Uji Komite Etik', 'peran' => Peran::KomiteEtik, 'unit' => null],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('AkunUjiSeeder hanya berjalan di environment local; tidak ada akun yang dibuat.');

            return;
        }

        $kataSandi = (string) config('sigap.akun_uji.password');

        if ($kataSandi === '') {
            throw new RuntimeException('AKUN_UJI_PASSWORD kosong. Isi di .env lokal, jalankan php artisan config:clear, lalu ulangi seeder ini.');
        }

        $this->call(UnitSeeder::class);

        $idUnit = Unit::query()->pluck('id', 'nama');

        foreach (self::AKUN as $akun) {
            User::query()->updateOrCreate(['email' => $akun['email']], [
                'name' => $akun['name'],
                'password' => $kataSandi,
                'peran' => $akun['peran'],
                'unit_id' => $akun['unit'] === null ? null : $idUnit[$akun['unit']],
                'jenis_akun' => JenisAkun::Lokal,
            ]);
        }

        $this->command?->info(count(self::AKUN).' akun uji @sigap.test siap.');
    }
}
