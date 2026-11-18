<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\VerifikasiSso;
use App\Http\Controllers\Auth\LoginGoogleController;
use App\Http\Controllers\UnduhLampiranController;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
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
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Widget dasbor per peran (H-10) ditemukan otomatis dari app/Filament/Widgets.
            ->widgets([
                AccountWidget::class,
            ])
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
