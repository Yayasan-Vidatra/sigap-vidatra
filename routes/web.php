<?php

use App\Http\Controllers\AltchaController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\BuktiLaporController;
use App\Http\Controllers\HalamanInformasiController;
use App\Http\Controllers\LacakController;
use App\Http\Controllers\PengaduanController;
use App\Http\Controllers\UnggahLampiranController;
use App\Http\Controllers\WbsController;
use App\Http\Middleware\LindungiJalurWbs;
use App\Http\Middleware\PeriksaTokenFormulirWbs;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', BerandaController::class)->name('beranda');

Route::get('/pengaduan', [PengaduanController::class, 'create'])->name('pengaduan.create');
Route::post('/pengaduan', [PengaduanController::class, 'store'])
    ->middleware('throttle:kirim-pengaduan')
    ->name('pengaduan.store');
Route::get('/selesai', BuktiLaporController::class)->name('bukti-lapor');
Route::get('/selesai/pdf', [BuktiLaporController::class, 'pdf'])->name('bukti-lapor.pdf');

// Unggah lampiran bertahap (US-04). Id unggahan acak menjadi satu-satunya pegangan sampai formulir dikirim.
Route::prefix('lampiran/unggah')->name('lampiran.unggah.')->group(function (): void {
    Route::post('/', [UnggahLampiranController::class, 'mulai'])->middleware('throttle:unggah-lampiran')->name('mulai');
    Route::post('/{id}/potongan/{indeks}', [UnggahLampiranController::class, 'potongan'])
        ->whereNumber('indeks')
        ->middleware('throttle:potongan-lampiran')
        ->name('potongan');
    Route::post('/{id}/selesai', [UnggahLampiranController::class, 'selesai'])->middleware('throttle:unggah-lampiran')->name('selesai');
    Route::delete('/{id}', [UnggahLampiranController::class, 'batal'])->name('batal');
});

// H-06 Lacak Laporan dan H-07 Detail Laporan (US-08, US-09, US-13). Laporan yang dibuka ditentukan dari izin
// di sesi; kode lacak tidak pernah ada di URL agar tidak tersimpan di riwayat browser dan log server.
Route::get('/lacak', [LacakController::class, 'create'])->name('lacak');
Route::post('/lacak', [LacakController::class, 'store'])->name('lacak.store');
Route::post('/lacak/keluar', [LacakController::class, 'keluar'])->name('lacak.keluar');
Route::prefix('lacak/laporan')->name('lacak.')->group(function (): void {
    Route::get('/', [LacakController::class, 'show'])->name('show');
    Route::post('/pesan', [LacakController::class, 'kirimPesan'])->middleware('throttle:pesan-pelapor')->name('pesan');
    Route::post('/konfirmasi', [LacakController::class, 'konfirmasi'])->name('konfirmasi');
    Route::post('/tolak', [LacakController::class, 'tolak'])->name('tolak');
    Route::post('/penilaian', [LacakController::class, 'nilai'])->name('penilaian');
});

// H-08 FAQ dan Kebijakan Privasi; teksnya dikelola di Pengaturan > Konten Publik (H-19).
Route::get('/faq', [HalamanInformasiController::class, 'faq'])->name('faq');
Route::get('/privasi', [HalamanInformasiController::class, 'privasi'])->name('privasi');

/*
 * Middleware yang memasang cookie: sesi, token CSRF, dan cookie terenkripsi. Dilepas dari jalur /wbs* dan
 * dari endpoint tantangan CAPTCHA yang dipakai formulir WBS (aturan proyek no. 5, Rancangan r1 Bagian 9).
 */
$tanpaCookie = [
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
];

Route::get('/altcha/tantangan', AltchaController::class)
    ->withoutMiddleware($tanpaCookie)
    ->middleware('throttle:tantangan-altcha')
    ->name('altcha.tantangan');

/*
 * Semua jalur WBS (H-03, H-04, H-05) di grup ini: tanpa sesi, cookie, dan CSRF; header no-referrer, no-store,
 * dan CSP tanpa pihak ketiga (LindungiJalurWbs). Pengganti CSRF: ALTCHA dan token formulir bertanda tangan.
 */
Route::prefix('wbs')
    ->withoutMiddleware($tanpaCookie)
    ->middleware(LindungiJalurWbs::class)
    ->group(function (): void {
        Route::prefix('lampiran/unggah')
            ->name('wbs.lampiran.unggah.')
            ->middleware(PeriksaTokenFormulirWbs::class)
            ->group(function (): void {
                Route::post('/', [UnggahLampiranController::class, 'mulai'])->middleware('throttle:unggah-lampiran-wbs')->name('mulai');
                Route::post('/{id}/potongan/{indeks}', [UnggahLampiranController::class, 'potongan'])
                    ->whereNumber('indeks')
                    ->middleware('throttle:potongan-lampiran-wbs')
                    ->name('potongan');
                Route::post('/{id}/selesai', [UnggahLampiranController::class, 'selesai'])->middleware('throttle:unggah-lampiran-wbs')->name('selesai');
                Route::delete('/{id}', [UnggahLampiranController::class, 'batal'])->middleware('throttle:unggah-lampiran-wbs')->name('batal');
            });

        Route::get('/', [WbsController::class, 'jaminan'])->name('wbs');
        Route::get('/jaminan-kerahasiaan', [WbsController::class, 'berkasJaminan'])->name('wbs.jaminan.berkas');
        Route::get('/lapor', [WbsController::class, 'create'])->name('wbs.lapor');
        Route::get('/lapor/token', [WbsController::class, 'token'])->middleware('throttle:tantangan-altcha')->name('wbs.token');
        // Kiriman dijawab JSON berisi data Bukti Lapor WBS (H-05) yang ditampilkan sekali di halaman.
        Route::post('/lapor', [WbsController::class, 'store'])->middleware('throttle:kirim-wbs')->name('wbs.store');
        Route::post('/bukti/pdf', [WbsController::class, 'pdf'])->middleware('throttle:kirim-wbs')->name('wbs.bukti.pdf');
    });
