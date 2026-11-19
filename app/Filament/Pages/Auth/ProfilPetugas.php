<?php

namespace App\Filament\Pages\Auth;

use App\Enums\AksiAudit;
use App\Enums\JenisAkun;
use App\Models\LogAudit;
use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Profil petugas: hanya mengganti kata sandi sendiri, dengan kata sandi lama wajib diisi. Nama dan surel dikelola
 * di Pengaturan (H-19) sehingga hanya ditampilkan. Akun SSO tidak memakai kata sandi. 2FA tidak diubah di sini;
 * pengaturan ulangnya lewat `sigap:peran-khusus` atau Super Admin. Perubahan tercatat di log audit tanpa nilai.
 */
class ProfilPetugas extends EditProfile
{
    public function form(Schema $schema): Schema
    {
        $lokal = $this->akunLokal();

        return $schema->components([
            $this->getNameFormComponent()->disabled()->dehydrated(false),
            $this->getEmailFormComponent()->disabled()->dehydrated(false),
            ...($lokal ? [
                $this->getPasswordFormComponent()->required(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ] : []),
        ]);
    }

    public function getMultiFactorAuthenticationContentComponent(): ?Component
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        if (! $this->akunLokal() || ! isset($data['password'])) {
            return $record;
        }

        $record->forceFill(['password' => $data['password']])->save();

        if ($record instanceof User) {
            LogAudit::catat($record, AksiAudit::MengubahKataSandi, $record);
        }

        return $record;
    }

    private function akunLokal(): bool
    {
        $user = $this->getUser();

        return $user instanceof User && $user->jenis_akun === JenisAkun::Lokal;
    }
}
