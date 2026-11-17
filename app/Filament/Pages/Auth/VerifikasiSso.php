<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Tantangan 2FA setelah login Google Workspace (US-26). Filament tidak menantang pengguna yang masuk
 * di luar halaman login, sehingga callback SSO menyimpan pengguna di sesi dan baru memanggil
 * login() setelah kode autentikator benar.
 *
 * @property-read Schema $form
 */
class VerifikasiSso extends SimplePage
{
    private const KUNCI_SESI = 'sigap.sso_menunggu_2fa';

    private const BATAS_MENIT = 5;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function tandaiMenunggu(User $user): void
    {
        session()->put(self::KUNCI_SESI, [
            'id' => $user->getKey(),
            'kedaluwarsa' => now()->addMinutes(self::BATAS_MENIT)->getTimestamp(),
        ]);
    }

    public function mount(): void
    {
        $user = $this->penggunaMenunggu();

        if ($user === null) {
            $this->redirect(Filament::getLoginUrl());

            return;
        }

        MultiFactorChallenge::make()->beforeChallenge($user);
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $user = $this->penggunaMenunggu();

        return $schema
            ->components($user ? MultiFactorChallenge::make()->getSchemaComponents($user) : [])
            ->statePath('data');
    }

    public function verifikasi(): void
    {
        $user = $this->penggunaMenunggu();
        $tantangan = MultiFactorChallenge::make();

        if ($user === null || ! $tantangan->hasEnabledProviders($user)) {
            $this->batal('Sesi verifikasi berakhir. Silakan login ulang dengan Google.');

            return;
        }

        if ($tantangan->isRateLimited($user)) {
            Notification::make()
                ->title('Terlalu banyak percobaan. Coba lagi dalam '.$tantangan->getRateLimiterAvailableInSeconds($user).' detik.')
                ->danger()
                ->send();

            return;
        }

        $tantangan->hitRateLimiter($user);
        $this->form->getState();

        // Periksa ulang setelah tantangan: akun bisa saja terkunci atau perannya berubah sementara itu.
        $user->refresh();

        if (! $user->bolehLoginSso()) {
            $this->batal('Akun ini tidak dapat masuk. Hubungi Super Admin.');

            return;
        }

        session()->forget(self::KUNCI_SESI);
        Filament::auth()->login($user);
        session()->regenerate();

        $this->redirect(Filament::getUrl());
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('verifikasi')
                ->footer([
                    Actions::make([
                        Action::make('verifikasi')->label('Verifikasi')->submit('verifikasi'),
                    ])->fullWidth(),
                ]),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Verifikasi 2FA';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Verifikasi 2FA';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Masukkan kode dari aplikasi autentikator untuk menyelesaikan login Google Workspace.';
    }

    private function penggunaMenunggu(): ?User
    {
        $menunggu = session()->get(self::KUNCI_SESI);

        if (! is_array($menunggu) || ($menunggu['kedaluwarsa'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return User::query()->find($menunggu['id'] ?? null);
    }

    private function batal(string $pesan): void
    {
        session()->forget(self::KUNCI_SESI);
        Notification::make()->title($pesan)->danger()->send();
        $this->redirect(Filament::getLoginUrl());
    }
}
