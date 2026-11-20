<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header untuk semua respons /wbs* (aturan proyek no. 5, Rancangan r1 Bagian 9, NF-02):
 * - X-Robots-Tag noindex, nofollow (kanal internal sejak revisi 20-11);
 * - Referrer-Policy no-referrer, agar alamat halaman WBS tidak terkirim ke situs lain;
 * - Cache-Control no-store, agar isi halaman dan respons tidak tersimpan di cache browser atau proxy;
 * - Content-Security-Policy yang hanya mengizinkan skrip, gaya, gambar, font, dan koneksi dari situs ini
 *   (tanpa skrip, aset, atau analitik pihak ketiga). Widget ALTCHA memakai worker dari blob:, pratinjau
 *   lampiran memakai blob:, dan dokumen jaminan kerahasiaan ditampilkan di iframe dari situs ini.
 *
 * Sebagai lapis kedua, cookie apa pun yang terpasang di respons dibuang. Route WBS sendiri sudah berjalan
 * tanpa StartSession, EncryptCookies, dan CSRF (routes/web.php).
 */
class LindungiJalurWbs
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        foreach ($response->headers->getCookies() as $cookie) {
            $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }

        // Kanal internal (revisi 20-11): jangan diindeks mesin pencari. Sengaja tidak lewat robots.txt, karena
        // mencantumkan /wbs di sana justru membuka alamatnya.
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', self::kebijakanKonten($nonce));

        return $response;
    }

    /**
     * Saat Vite dev server berjalan, asal dev server ikut diizinkan agar halaman tetap bisa dikembangkan;
     * di produksi hanya 'self'.
     */
    public static function kebijakanKonten(string $nonce): string
    {
        $asalDev = '';

        if (Vite::isRunningHot()) {
            $hot = rtrim(trim((string) file_get_contents(Vite::hotFile())), '/');
            $asalDev = ' '.$hot.' '.preg_replace('#^http#', 'ws', $hot);
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'".$asalDev,
            "style-src 'self' 'unsafe-inline'".$asalDev,
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            "font-src 'self' data:".$asalDev,
            "connect-src 'self'".$asalDev,
            "worker-src 'self' blob:",
            "frame-src 'self'",
            "object-src 'none'",
            "base-uri 'none'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
