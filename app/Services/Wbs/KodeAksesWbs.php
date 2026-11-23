<?php

namespace App\Services\Wbs;

use App\Enums\Peran;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\Pengaturan\PencatatPerubahanPengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Kode akses bersama kanal WBS internal (revisi 20-11, log deviasi Bagian 2). Kode disimpan sebagai hash di
 * tabel pengaturan dan tidak pernah ditanam di kode, seeder, atau test; petunjuknya teks biasa. Keduanya dikelola
 * Super Admin dan Ketua Tim WBS di Pengaturan > Kode Akses WBS. Perubahan kode tercatat di riwayat_pengaturan
 * hanya sebagai "kode akses diubah", tanpa nilainya.
 *
 * Lolos kode menghasilkan token akses bertanda tangan (TokenTanpaSesi) yang dibawa halaman ke jaminan kerahasiaan
 * dan formulir, tanpa sesi, cookie, atau IP.
 */
class KodeAksesWbs
{
    public const TUJUAN_TOKEN = 'akses-wbs';

    /** Panjang minimal kode akses baru. */
    public const PANJANG_MINIMAL = 4;

    private static ?string $hashTiruan = null;

    public function __construct(private PencatatPerubahanPengaturan $pencatat) {}

    public static function dibuka(): bool
    {
        return Pengaturan::nilai(Pengaturan::WBS_KODE_AKSES) !== null;
    }

    public static function petunjuk(): ?string
    {
        return Pengaturan::nilai(Pengaturan::WBS_PETUNJUK_KODE_AKSES);
    }

    /**
     * Membandingkan kode dengan hash tersimpan (Hash::check, waktu tetap). Bila kode belum diatur, hasilnya selalu
     * false; hash tiruan tetap diperiksa agar lama pemeriksaan tidak membedakan keduanya.
     */
    public static function cocok(mixed $kode): bool
    {
        $hash = Pengaturan::nilai(Pengaturan::WBS_KODE_AKSES);
        $kode = is_string($kode) ? self::normalkan($kode) : '';
        $sah = Hash::check($kode, $hash ?? self::hashTiruan());

        return $hash !== null && $kode !== '' && $sah;
    }

    public static function terbitkanToken(): string
    {
        return TokenTanpaSesi::terbitkan(self::TUJUAN_TOKEN, ['n' => Str::random(16)], self::masaTokenMenit());
    }

    public static function tokenSah(mixed $token): bool
    {
        return TokenTanpaSesi::uraikan(self::TUJUAN_TOKEN, $token) !== null;
    }

    public static function masaTokenMenit(): int
    {
        return (int) config('sigap.wbs.masa_token_akses_menit');
    }

    public static function bolehMengelola(?User $user): bool
    {
        return $user !== null && in_array($user->peran, [Peran::SuperAdmin, Peran::KetuaTimWbs], true);
    }

    /**
     * Menyimpan kode baru (bila diisi) dan petunjuk. Kode lama tidak pernah ditampilkan atau dibaca kembali.
     *
     * @throws AuthorizationException
     */
    public function simpan(User $oleh, ?string $kodeBaru, ?string $petunjuk): void
    {
        if (! self::bolehMengelola($oleh)) {
            throw new AuthorizationException('Hanya Super Admin dan Ketua Tim WBS yang dapat mengubah kode akses WBS.');
        }

        $kodeBaru = is_string($kodeBaru) ? self::normalkan($kodeBaru) : '';
        $petunjuk = is_string($petunjuk) && trim($petunjuk) !== '' ? trim($petunjuk) : null;
        $petunjukLama = self::petunjuk();

        DB::transaction(function () use ($oleh, $kodeBaru, $petunjuk, $petunjukLama): void {
            if ($kodeBaru !== '') {
                Pengaturan::query()->updateOrCreate(['kunci' => Pengaturan::WBS_KODE_AKSES], ['nilai' => Hash::make($kodeBaru)]);
                $this->pencatat->catat($oleh, 'pengaturan:'.Pengaturan::WBS_KODE_AKSES, [], ['kode_akses' => 'diubah'], rahasia: ['kode_akses']);
            }

            if ($petunjuk !== $petunjukLama) {
                Pengaturan::query()->updateOrCreate(['kunci' => Pengaturan::WBS_PETUNJUK_KODE_AKSES], ['nilai' => $petunjuk]);
                $this->pencatat->catat($oleh, 'pengaturan:'.Pengaturan::WBS_PETUNJUK_KODE_AKSES, ['nilai' => $petunjukLama], ['nilai' => $petunjuk]);
            }
        });
    }

    /**
     * Kode dibandingkan tanpa spasi di tepi dan tanpa membedakan huruf besar-kecil, agar petunjuk seperti tahun
     * atau nama tidak gagal karena penulisan.
     */
    private static function normalkan(string $kode): string
    {
        return mb_strtolower(trim($kode));
    }

    private static function hashTiruan(): string
    {
        return self::$hashTiruan ??= Hash::make(Str::random(16));
    }
}
