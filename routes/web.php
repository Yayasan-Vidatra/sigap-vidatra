<?php

use App\Http\Controllers\BerandaController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', BerandaController::class)->name('beranda');

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
 * Jalur /wbs* tidak memasang cookie apa pun: tanpa sesi, tanpa token CSRF, tanpa cookie terenkripsi
 * (aturan proyek no. 5, Rancangan r1 Bagian 9). Semua halaman WBS (H-03, H-04) ditambahkan di grup ini.
 */
Route::prefix('wbs')
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->group(function (): void {
        Route::inertia('/', 'Sementara', [
            'judul' => 'Pelaporan Pelanggaran (WBS)',
            'keterangan' => 'Kanal pelaporan pelanggaran sedang disiapkan. Sementara itu, laporan pelanggaran '
                .'dapat disampaikan lewat surel atau telepon/WhatsApp khusus SPI.',
        ])->name('wbs');
    });
