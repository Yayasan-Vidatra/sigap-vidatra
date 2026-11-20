<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\MintaTautanKataSandi;
use App\Filament\Pages\Auth\ProfilPetugas;
use App\Filament\Pages\Auth\VerifikasiSso;
use App\Filament\Pages\Dasbor;
use App\Filament\Support\AvatarInisial;
use App\Filament\Support\WarnaPanel;
use App\Http\Controllers\Auth\LoginGoogleController;
use App\Http\Controllers\UnduhLampiranController;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PanelPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('panel')
            ->path('panel')
            ->login(Login::class)
            // Tautan buat kata sandi hanya dari sigap:peran-khusus; permintaan mandiri dimatikan (Bagian 9).
            ->passwordReset(MintaTautanKataSandi::class)
            ->profile(ProfilPetugas::class, isSimple: false)
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: true)
            // Filament memasang middleware 2FA wajib pada route halaman. Didaftarkan juga sebagai persistent
            // agar permintaan Livewire lanjutan di halaman yang sudah terbuka ikut dialihkan bila 2FA hilang.
            ->persistentMiddleware([
                EnsureMultiFactorAuthenticationIsEnabled::class,
            ])
            ->routes(function (): void {
                Route::get('sso/google', [LoginGoogleController::class, 'redirect'])->name('sso.google.redirect');
                Route::get('sso/google/callback', [LoginGoogleController::class, 'callback'])->name('sso.google.callback');
                Route::get('sso/google/verifikasi', VerifikasiSso::class)->name('sso.google.verifikasi');
            })
            ->authenticatedRoutes(function (): void {
                Route::get('lampiran/{lampiran}/unduh', UnduhLampiranController::class)
                    ->middleware(['signed', EnsureMultiFactorAuthenticationIsEnabled::class])
                    ->name('lampiran.unduh');
            })
            // Tampilan sesuai prototipe yang disetujui (prd/mockup/panel-*.html): menu atas, biru #1E3A9E, biru tua
            // #0B1B4D untuk area WBS, dan Plus Jakarta Sans dari resources/fonts lewat tema Vite (tanpa domain luar).
            ->topNavigation()
            ->breadcrumbs(false)
            ->viteTheme('resources/css/filament/panel/theme.css')
            ->font('Plus Jakarta Sans', provider: LocalFontProvider::class)
            ->brandName('SIGAP Vidatra')
            ->brandLogo(fn () => view('filament.merek.logo'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('img/logo-96.png'))
            ->defaultAvatarProvider(AvatarInisial::class)
            ->maxContentWidth(Width::ScreenTwoExtraLarge)
            ->colors(WarnaPanel::palet())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dasbor::class,
            ])
            // Widget dasbor per peran (H-10) ditemukan otomatis dari app/Filament/Widgets. Kartu "Selamat Datang"
            // bawaan Filament (AccountWidget) sengaja tidak dipasang.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
