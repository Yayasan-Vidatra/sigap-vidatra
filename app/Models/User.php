<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Exceptions\PelanggaranIntegritasData;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'peran', 'unit_id', 'jenis_akun'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->jenis_akun === JenisAkun::Sso && ! ($user->peran?->bolehSso() ?? false)) {
                throw PelanggaranIntegritasData::akunSsoTidakSesuaiPeran($user->peran);
            }

            if ($user->peran === null) {
                return;
            }

            $punyaUnit = $user->unit_id !== null;

            if (($user->peran->wajibUnit() && ! $punyaUnit) || ($punyaUnit && ! $user->peran->bolehUnit())) {
                throw PelanggaranIntegritasData::unitTidakSesuaiPeran($user->peran);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'peran' => Peran::class,
            'jenis_akun' => JenisAkun::class,
            'gagal_login' => 'integer',
            'terkunci_pada' => 'datetime',
        ];
    }

    /**
     * Panel petugas hanya untuk pengguna yang memiliki peran dan tidak terkunci. Karena Filament juga
     * memeriksa ini di setiap permintaan, sesi akun yang baru terkunci ikut terputus.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'panel' && $this->peran !== null && ! $this->terkunci();
    }

    /**
     * Boleh masuk lewat SSO Google Workspace: akun SSO, peran Admin Pengaduan atau PIC Unit, dan boleh
     * membuka panel (punya peran, tidak terkunci). Rancangan r1 Bagian 9 dan 11.
     */
    public function bolehLoginSso(): bool
    {
        return $this->jenis_akun === JenisAkun::Sso
            && ($this->peran?->bolehSso() ?? false)
            && $this->canAccessPanel(Filament::getPanel('panel'));
    }

    public function terkunci(): bool
    {
        return $this->terkunci_pada !== null;
    }

    /**
     * Menambah hitungan gagal login dan mengunci akun bila mencapai batas (US-26).
     *
     * @return bool true bila akun baru saja terkunci oleh percobaan ini
     */
    public function catatGagalLogin(): bool
    {
        $gagal = $this->gagal_login + 1;
        $menguncikan = $gagal >= (int) config('sigap.login.maks_gagal');

        $this->forceFill([
            'gagal_login' => $gagal,
            'terkunci_pada' => $menguncikan ? now() : $this->terkunci_pada,
        ])->save();

        return $menguncikan;
    }

    public function resetGagalLogin(): void
    {
        if ($this->gagal_login > 0) {
            $this->forceFill(['gagal_login' => 0])->save();
        }
    }

    public function bukaKunci(): void
    {
        $this->forceFill(['gagal_login' => 0, 'terkunci_pada' => null])->save();
    }

    /**
     * Admin Pengaduan yang hanya menangani satu unit (Admin Sekolah, Rancangan r1 Bagian 2).
     */
    public function isAdminSekolah(): bool
    {
        return $this->peran === Peran::AdminPengaduan && $this->unit_id !== null;
    }

    /**
     * Admin Pengaduan untuk seluruh yayasan.
     */
    public function isAdminYayasan(): bool
    {
        return $this->peran === Peran::AdminPengaduan && $this->unit_id === null;
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Disposisi yang ditujukan kepada pengguna ini sebagai PIC Unit.
     *
     * @return HasMany<Disposisi, $this>
     */
    public function disposisi(): HasMany
    {
        return $this->hasMany(Disposisi::class, 'pic_user_id');
    }

    /**
     * @return HasMany<LogAudit, $this>
     */
    public function logAudit(): HasMany
    {
        return $this->hasMany(LogAudit::class);
    }
}
