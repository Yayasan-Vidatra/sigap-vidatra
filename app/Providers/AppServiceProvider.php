<?php

namespace App\Providers;

use AltchaOrg\Altcha\Altcha;
use App\Services\Lampiran\PembersihMetadata;
use App\Services\Lampiran\PembersihMetadataAlat;
use App\Services\Lampiran\PembersihMetadataLokal;
use App\Services\Lampiran\PemindaiClamav;
use App\Services\Lampiran\PemindaiLokal;
use App\Services\Lampiran\PemindaiVirus;
use App\Services\Sesi\PenanganSesiTanpaJejak;
use App\Services\WaktuLokal;
use App\Services\Wbs\TokenFormulirWbs;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
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
        $this->configureSession();

        // Panel menampilkan waktu dalam WITA; basis data tetap UTC (config sigap.zona_waktu).
        FilamentTimezone::set(WaktuLokal::zona());
    }

    /**
     * Driver sesi "database" diganti penangan yang tidak menyimpan IP dan user agent (aturan proyek no. 5),
     * sehingga SESSION_DRIVER=database tetap dipakai tanpa mengubah .env.
     */
    private function configureSession(): void
    {
        Session::extend('database', fn ($app): PenanganSesiTanpaJejak => new PenanganSesiTanpaJejak(
            $app['db']->connection($app['config']->get('session.connection')),
            $app['config']->get('session.table'),
            $app['config']->get('session.lifetime'),
            $app,
        ));
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

        // Tantangan CAPTCHA juga dipakai formulir WBS, sehingga dibatasi dengan penghitung global, tanpa IP.
        RateLimiter::for('tantangan-altcha', fn (): Limit => Limit::perMinute(
            (int) config('sigap.altcha.batas_tantangan_per_menit'),
        )->by('global'));

        // Unggah lampiran: memulai/menyelesaikan unggahan, dan potongan (100 MB = 20 potongan 5 MB).
        RateLimiter::for('unggah-lampiran', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
        // Pesan pelapor di halaman Lacak (UC-05), per sesi.
        RateLimiter::for('pesan-pelapor', fn (Request $request): Limit => Limit::perMinute(10)->by($request->session()->getId()));
        RateLimiter::for('potongan-lampiran', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));

        $this->configureRateLimitingWbs();
    }

    /**
     * Pembatas laju jalur /wbs* tanpa IP (aturan proyek no. 5): penghitung global untuk semua pelapor WBS,
     * ditambah penghitung per token formulir untuk unggahan agar satu halaman tidak menghabiskan jatah global.
     */
    private function configureRateLimitingWbs(): void
    {
        $terlaluBanyak = fn () => response()->json([
            'message' => 'Terlalu banyak permintaan dalam waktu singkat. Silakan coba lagi dalam satu menit.',
        ], 429);

        RateLimiter::for('kirim-wbs', fn (): Limit => Limit::perMinute(
            (int) config('sigap.wbs.batas_kirim_global_per_menit'),
        )->by('global')->response($terlaluBanyak));

        foreach (['unggah' => 'unggah-lampiran-wbs', 'potongan' => 'potongan-lampiran-wbs'] as $jenis => $nama) {
            RateLimiter::for($nama, fn (Request $request): array => [
                Limit::perMinute((int) config("sigap.wbs.batas_{$jenis}_global_per_menit"))
                    ->by('global')
                    ->response($terlaluBanyak),
                Limit::perMinute((int) config("sigap.wbs.batas_{$jenis}_per_token_per_menit"))
                    ->by('token:'.hash('sha256', (string) TokenFormulirWbs::nonce($request->header('X-Token-Formulir'))))
                    ->response($terlaluBanyak),
            ]);
        }
    }
}
