<?php

namespace App\Http\Controllers;

use App\Enums\JenisLaporan;
use App\Models\Kategori;
use App\Models\Pengaturan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-08 FAQ dan Kebijakan Privasi. Teksnya dari Pengaturan (dikelola Super Admin di H-19), bukan dari kode. Target
 * waktu penanganan diambil langsung dari Kategori dan SLA serta tenggat WBS, sehingga selalu sama dengan yang
 * dipakai sistem. Halaman ini tidak memuat data petugas, termasuk susunan Tim WBS dan penerima hasil.
 */
class HalamanInformasiController extends Controller
{
    public function faq(): Response
    {
        return Inertia::render('Informasi/Faq', [
            'pertanyaan' => Pengaturan::daftar(Pengaturan::PUBLIK_FAQ),
            'targetPengaduan' => Kategori::query()
                ->where('jenis', JenisLaporan::Pengaduan->value)
                ->orderBy('nama')
                ->get(['nama', 'sla_tanggapan_hari', 'sla_hari'])
                ->map(fn (Kategori $kategori): array => [
                    'kategori' => $kategori->nama,
                    'tanggapan' => $kategori->sla_tanggapan_hari,
                    'selesai' => $kategori->sla_hari,
                ])
                ->all(),
            'targetWbs' => [
                'telaah' => (int) config('sigap.wbs.telaah_hari_kerja'),
                'investigasi' => (int) config('sigap.wbs.investigasi_hari_kerja'),
                'perpanjangan' => (int) config('sigap.wbs.perpanjangan_hari_kerja'),
            ],
        ]);
    }

    public function privasi(): Response
    {
        return Inertia::render('Informasi/Privasi', [
            'bagian' => Pengaturan::daftar(Pengaturan::PUBLIK_PRIVASI),
        ]);
    }
}
