<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
