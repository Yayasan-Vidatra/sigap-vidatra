<?php

namespace App\Providers;

use AltchaOrg\Altcha\Altcha;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Altcha::class, function (): Altcha {
            $kunci = (string) config('sigap.altcha.hmac_key');

            if ($kunci === '') {
                throw new RuntimeException('ALTCHA_HMAC_KEY belum diisi di .env; CAPTCHA tidak dapat dibuat atau diverifikasi.');
            }

            return new Altcha(hmacSignatureSecret: $kunci);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Batas pengiriman Formulir Pengaduan per menit per IP (US-02). Saat terlampaui, pelapor kembali ke
     * formulir dengan pesan, dan isiannya tetap ada di browser.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('kirim-pengaduan', fn (Request $request): Limit => Limit::perMinute(
            (int) config('sigap.pengaduan.batas_kirim_per_menit'),
        )->by($request->ip())->response(fn () => back()->withErrors([
            'kirim' => 'Terlalu banyak pengiriman dalam waktu singkat. Silakan coba lagi dalam satu menit.',
        ])));

        RateLimiter::for('tantangan-altcha', fn (Request $request): Limit => Limit::perMinute(
            (int) config('sigap.altcha.batas_tantangan_per_menit'),
        )->by($request->ip()));
    }
}
