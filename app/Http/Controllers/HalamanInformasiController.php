<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\Pengaturan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-08 FAQ dan Kebijakan Privasi. Teksnya dari Pengaturan (dikelola Super Admin di H-19), bukan dari kode. Target
 * waktu penanganan diambil langsung dari Kategori dan SLA, sehingga selalu sama dengan yang dipakai sistem. Sejak
 * revisi 20-11 halaman publik tidak menyebut kanal WBS internal. Halaman ini tidak memuat data petugas.
 */
class HalamanInformasiController extends Controller
{
    public function faq(): Response
    {
        return Inertia::render('Informasi/Faq', [
            'pertanyaan' => Pengaturan::daftar(Pengaturan::PUBLIK_FAQ),
            'targetPengaduan' => Kategori::targetPengaduan(),
        ]);
    }

    public function privasi(): Response
    {
        return Inertia::render('Informasi/Privasi', [
            'bagian' => Pengaturan::daftar(Pengaturan::PUBLIK_PRIVASI),
        ]);
    }
}
