<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Exceptions\PelanggaranIntegritasData;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'peran', 'unit_id', 'jenis_akun'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
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
        ];
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
