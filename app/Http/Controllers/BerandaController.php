<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\Pengaturan;
use App\Models\Unit;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-01 Beranda (US-01, F-01). Kontak darurat diambil dari units.kontak_darurat yang diisi lewat
 * Pengaturan, target waktu Pengaduan dari SLA kategori, dan tanya jawab dari
 * Pengaturan, bukan ditanam di kode (aturan proyek no. 7).
 */
class BerandaController extends Controller
{
    /** Banyaknya tanya jawab dari Pengaturan yang ditampilkan di Beranda; selengkapnya di /faq. */
    private const JUMLAH_TANYA_JAWAB = 4;

    public function __invoke(): Response
    {
        return Inertia::render('Beranda', [
            'kontakDarurat' => Unit::query()
                ->untukPengaduan()
                ->where('aktif', true)
                ->whereNotNull('kontak_darurat')
                ->where('kontak_darurat', '!=', '')
                ->orderBy('id')
                ->get(['nama', 'kontak_darurat'])
                ->map(fn (Unit $unit): array => ['unit' => $unit->nama, 'kontak' => $unit->kontak_darurat])
                ->all(),
            'unitTanpaKontakDarurat' => Unit::tanpaKontakDarurat(),
            'kontakDaruratCadangan' => Unit::KONTAK_DARURAT_CADANGAN,
            'targetPengaduan' => self::ringkasanTarget(Kategori::targetPengaduan()),
            'tanyaJawab' => array_slice(Pengaturan::daftar(Pengaturan::PUBLIK_FAQ), 0, self::JUMLAH_TANYA_JAWAB),
        ]);
    }

    /**
     * Rentang target (terkecil dan terbesar) dari semua kategori pengaduan; null bila belum ada kategori.
     *
     * @param  list<array{kategori: string, tanggapan: int, selesai: int}>  $target
     * @return array{tanggapan: array{min: int, maks: int}, selesai: array{min: int, maks: int}}|null
     */
    private static function ringkasanTarget(array $target): ?array
    {
        if ($target === []) {
            return null;
        }

        $tanggapan = array_column($target, 'tanggapan');
        $selesai = array_column($target, 'selesai');

        return [
            'tanggapan' => ['min' => min($tanggapan), 'maks' => max($tanggapan)],
            'selesai' => ['min' => min($selesai), 'maks' => max($selesai)],
        ];
    }
}
