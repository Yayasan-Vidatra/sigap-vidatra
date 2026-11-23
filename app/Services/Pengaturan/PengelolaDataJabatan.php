<?php

namespace App\Services\Pengaturan;

use App\Enums\AksiAudit;
use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Exceptions\PelanggaranIntegritasData;
use App\Models\LogAudit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Unit, kontak darurat, dan data jabatan (nama dan surel Admin, PIC, Kepala Sekolah, Komite Etik) yang dikelola
 * Super Admin di Pengaturan (H-19, US-24, UC-15). Setiap perubahan tercatat lewat PencatatPerubahanPengaturan.
 * Pengguna dengan peran di luar Peran::kelolaanPengaturan() tidak bisa diubah lewat sini.
 */
class PengelolaDataJabatan
{
    public function __construct(private PencatatPerubahanPengaturan $pencatat) {}

    /**
     * @param  array{nama: string, aktif?: bool|null, kontak_darurat?: string|null}  $data
     */
    public function simpanUnit(User $oleh, ?Unit $unit, array $data): Unit
    {
        self::pastikanSuperAdmin($oleh);

        return DB::transaction(function () use ($oleh, $unit, $data): Unit {
            $unit ??= new Unit;
            $lama = $unit->exists ? self::isianUnit($unit) : [];

            $unit->fill([
                'nama' => trim($data['nama']),
                'aktif' => (bool) ($data['aktif'] ?? true),
                'khusus_wbs' => (bool) ($data['khusus_wbs'] ?? $unit->khusus_wbs ?? false),
                'nama_pimpinan' => self::teksAtauNull($data['nama_pimpinan'] ?? null),
                'kontak_darurat' => self::teksAtauNull($data['kontak_darurat'] ?? null),
            ])->save();

            $this->pencatat->catat($oleh, 'units:'.$unit->getKey(), $lama, self::isianUnit($unit));

            return $unit;
        });
    }

    /**
     * Membuat atau mengubah akun pemegang jabatan. Peran kosong berarti akun dinonaktifkan (tanpa akses panel).
     * Akun lokal baru wajib diberi kata sandi; akun SSO memakai kata sandi acak yang tidak dipakai.
     *
     * @param  array{name: string, email: string, peran?: Peran|string|null, unit_id?: int|string|null, jenis_akun?: JenisAkun|string|null, password?: string|null}  $data
     */
    public function simpanPengguna(User $oleh, ?User $pengguna, array $data): User
    {
        self::pastikanSuperAdmin($oleh);

        $peran = self::peran($data['peran'] ?? null);

        if ($pengguna?->exists && ! self::bolehDikelola($pengguna)) {
            throw PelanggaranIntegritasData::peranDiLuarPengaturan($pengguna->peran);
        }

        if ($peran !== null && ! $peran->dikelolaLewatPengaturan()) {
            throw PelanggaranIntegritasData::peranDiLuarPengaturan($peran);
        }

        return DB::transaction(function () use ($oleh, $pengguna, $data, $peran): User {
            $pengguna ??= new User;
            $baruDibuat = ! $pengguna->exists;
            $lama = $baruDibuat ? [] : self::isianPengguna($pengguna);
            $kataSandi = self::teksAtauNull($data['password'] ?? null);

            $jenisAkun = $data['jenis_akun'] ?? null;
            $jenisAkun = $jenisAkun instanceof JenisAkun ? $jenisAkun : JenisAkun::tryFrom((string) $jenisAkun);
            $jenisAkun = $jenisAkun === JenisAkun::Sso && ($peran?->bolehSso() ?? false) ? JenisAkun::Sso : JenisAkun::Lokal;

            if ($baruDibuat && $jenisAkun === JenisAkun::Lokal && $kataSandi === null) {
                throw new InvalidArgumentException('Akun lokal baru wajib diberi kata sandi awal.');
            }

            $pengguna->fill([
                'name' => trim($data['name']),
                'email' => Str::lower(trim($data['email'])),
                'peran' => $peran,
                'unit_id' => $peran?->bolehUnit() ? self::teksAtauNull((string) ($data['unit_id'] ?? '')) : null,
                'jenis_akun' => $jenisAkun,
            ]);

            if ($kataSandi !== null) {
                $pengguna->password = $kataSandi;
            } elseif ($baruDibuat) {
                $pengguna->password = Str::random(64);
            }

            $pengguna->save();

            $this->pencatat->catat(
                $oleh,
                'users:'.$pengguna->getKey(),
                $lama,
                [...self::isianPengguna($pengguna), 'kata_sandi' => $kataSandi],
                rahasia: ['kata_sandi'],
            );

            return $pengguna;
        });
    }

    public function bukaKunci(User $oleh, User $pengguna): void
    {
        self::pastikanSuperAdmin($oleh);

        if (! self::bolehDikelola($pengguna)) {
            throw PelanggaranIntegritasData::peranDiLuarPengaturan($pengguna->peran);
        }

        DB::transaction(function () use ($oleh, $pengguna): void {
            $pengguna->bukaKunci();
            LogAudit::catat($oleh, AksiAudit::KunciAkunDibuka, $pengguna);
        });
    }

    /**
     * Akun yang boleh diubah lewat Pengaturan: pemegang peran kelolaan, atau akun nonaktif (tanpa peran).
     */
    public static function bolehDikelola(User $pengguna): bool
    {
        return $pengguna->peran === null || $pengguna->peran->dikelolaLewatPengaturan();
    }

    private static function pastikanSuperAdmin(User $oleh): void
    {
        if ($oleh->peran !== Peran::SuperAdmin) {
            throw new AuthorizationException('Hanya Super Admin yang dapat mengubah pengaturan.');
        }
    }

    private static function peran(Peran|string|null $peran): ?Peran
    {
        return $peran instanceof Peran ? $peran : Peran::tryFrom((string) $peran);
    }

    /**
     * @return array<string, mixed>
     */
    private static function isianUnit(Unit $unit): array
    {
        return $unit->only(['nama', 'aktif', 'khusus_wbs', 'nama_pimpinan', 'kontak_darurat']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function isianPengguna(User $pengguna): array
    {
        return [
            'nama' => $pengguna->name,
            'surel' => $pengguna->email,
            'peran' => $pengguna->peran,
            'unit' => $pengguna->unit_id === null ? null : Unit::query()->whereKey($pengguna->unit_id)->value('nama'),
            'jenis_akun' => $pengguna->jenis_akun,
        ];
    }

    private static function teksAtauNull(?string $teks): ?string
    {
        $teks = trim((string) $teks);

        return $teks === '' ? null : $teks;
    }
}
