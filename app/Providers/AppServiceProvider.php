<?php

namespace App\Providers;

use AltchaOrg\Altcha\Altcha;
use App\Services\Lampiran\PembersihMetadata;
use App\Services\Lampiran\PembersihMetadataAlat;
use App\Services\Lampiran\PembersihMetadataLokal;
use App\Services\Lampiran\PemindaiClamav;
use App\Services\Lampiran\PemindaiLokal;
use App\Services\Lampiran\PemindaiVirus;
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

        $this->app->bind(PemindaiVirus::class, fn (): PemindaiVirus => match (config('sigap.lampiran.pemindai')) {
            'alat' => new PemindaiClamav,
            'lokal' => new PemindaiLokal,
            default => throw new RuntimeException('LAMPIRAN_PEMINDAI harus "alat" atau "lokal".'),
        });

        $this->app->bind(PembersihMetadata::class, fn (): PembersihMetadata => match (config('sigap.lampiran.pembersih_metadata')) {
            'alat' => new PembersihMetadataAlat,
            'lokal' => new PembersihMetadataLokal,
            default => throw new RuntimeException('LAMPIRAN_PEMBERSIH_METADATA harus "alat" atau "lokal".'),
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

        // Unggah lampiran: memulai/menyelesaikan unggahan, dan potongan (100 MB = 20 potongan 5 MB).
        RateLimiter::for('unggah-lampiran', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('potongan-lampiran', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));
    }
}
