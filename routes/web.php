<?php

use App\Http\Controllers\AltchaController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\BuktiLaporController;
use App\Http\Controllers\PengaduanController;
use App\Http\Controllers\UnggahLampiranController;
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

// Halaman sementara; isinya dikerjakan sesuai jadwal TOR (H-06 Hari 8, H-08 Hari 13).
Route::inertia('/lacak', 'Sementara', [
    'judul' => 'Lacak Laporan',
    'keterangan' => 'Halaman untuk melacak laporan dengan kode lacak sedang disiapkan.',
])->name('lacak');
Route::inertia('/faq', 'Sementara', [
    'judul' => 'Tanya Jawab (FAQ)',
    'keterangan' => 'Tanya jawab umum dan target waktu penanganan sedang disiapkan.',
])->name('faq');
Route::inertia('/privasi', 'Sementara', [
    'judul' => 'Kebijakan Privasi',
    'keterangan' => 'Kebijakan privasi (data yang disimpan, lama penyimpanan, hak pelapor dan terlapor) sedang disiapkan.',
])->name('privasi');

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

// Semua halaman WBS (H-03, H-04) ditambahkan di grup ini.
Route::prefix('wbs')
    ->withoutMiddleware($tanpaCookie)
    ->group(function (): void {
        Route::inertia('/', 'Sementara', [
            'judul' => 'Pelaporan Pelanggaran (WBS)',
            'keterangan' => 'Kanal pelaporan pelanggaran sedang disiapkan. Sementara itu, laporan pelanggaran '
                .'dapat disampaikan lewat surel atau telepon/WhatsApp khusus SPI.',
        ])->name('wbs');
    });
