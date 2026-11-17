<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AksiAudit;
use App\Filament\Pages\Auth\VerifikasiSso;
use App\Http\Controllers\Controller;
use App\Models\LogAudit;
use App\Models\User;
use Filament\Auth\MultiFactor\MultiFactorChallenge;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as PenggunaGoogle;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Login SSO Google Workspace untuk Admin Pengaduan dan PIC Unit (US-26, H-09).
 *
 * Hanya akun @vidatra.sch.id yang sudah terdaftar sebagai akun SSO yang diterima; akun baru tidak
 * pernah dibuat otomatis. Setelah Google, petugas tetap harus lolos 2FA aplikasi autentikator.
 */
class LoginGoogleController extends Controller
{
    private const PESAN_DITOLAK = 'Akun Google ini tidak dapat dipakai untuk masuk. SSO hanya untuk Admin Pengaduan '
        .'dan PIC Unit yang terdaftar dengan akun @vidatra.sch.id. Petugas lain masuk dengan akun lokal.';

    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')
            ->redirectUrl(route('filament.panel.sso.google.callback'))
            ->with(['hd' => config('sigap.sso.domain'), 'prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $penggunaGoogle = Socialite::driver('google')
                ->redirectUrl(route('filament.panel.sso.google.callback'))
                ->user();
        } catch (Throwable) {
            return $this->tolak(null, 'Login Google gagal atau dibatalkan. Silakan coba lagi.');
        }

        if (! $this->dariDomainYayasan($penggunaGoogle)) {
            return $this->tolak(null);
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower((string) $penggunaGoogle->getEmail())])
            ->first();

        if ($user === null || ! $user->bolehLoginSso()) {
            return $this->tolak($user);
        }

        if (MultiFactorChallenge::make()->hasEnabledProviders($user)) {
            VerifikasiSso::tandaiMenunggu($user);

            return redirect()->route('filament.panel.sso.google.verifikasi');
        }

        // Belum memasang 2FA: Filament mewajibkan pemasangan sebelum panel bisa dipakai.
        Filament::auth()->login($user);
        session()->regenerate();

        return redirect()->intended(Filament::getUrl());
    }

    /**
     * Surel harus berdomain yayasan, terverifikasi Google, dan berasal dari tenant Workspace yayasan (klaim hd).
     */
    private function dariDomainYayasan(PenggunaGoogle $penggunaGoogle): bool
    {
        $domain = (string) config('sigap.sso.domain');
        $data = $penggunaGoogle instanceof AbstractUser ? $penggunaGoogle->getRaw() : [];

        return Str::endsWith(Str::lower((string) $penggunaGoogle->getEmail()), '@'.$domain)
            && ($data['hd'] ?? null) === $domain
            && filter_var($data['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function tolak(?User $user, string $pesan = self::PESAN_DITOLAK): RedirectResponse
    {
        $user === null
            ? LogAudit::catatObjek(null, AksiAudit::LoginSsoDitolak, LogAudit::OBJEK_PENGGUNA_TIDAK_TERDAFTAR)
            : LogAudit::catat($user, AksiAudit::LoginSsoDitolak, $user);

        Notification::make()->title($pesan)->danger()->send();

        return redirect()->to(Filament::getLoginUrl());
    }
}
