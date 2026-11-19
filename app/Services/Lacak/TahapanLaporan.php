<?php

namespace App\Services\Lacak;

use App\Enums\JenisLaporan;
use App\Enums\StatusLaporan;
use App\Models\Laporan;
use App\Models\RiwayatStatus;
use App\Services\WaktuLokal;

/**
 * Tahapan status untuk garis waktu di H-07 (mockup Lacak): Pengaduan Diterima > Diperiksa admin > Diproses unit >
 * Konfirmasi Anda > Selesai; WBS Diterima > Ditelaah > Diinvestigasi > Selesai (Rancangan r1 Bagian 6). Status di
 * luar alur itu (Ditolak, Dipindah ke WBS, Dialihkan, Tidak Ditindaklanjuti) ditampilkan sebagai tahap akhir
 * sesudah tahap yang sudah dilalui. Hanya label status dan waktu; tanpa catatan petugas.
 */
class TahapanLaporan
{
    public const SELESAI = 'selesai';

    public const BERJALAN = 'berjalan';

    public const MENUNGGU = 'menunggu';

    /**
     * @return list<array{label: string, waktu: string|null, keadaan: string}>
     */
    public static function untuk(Laporan $laporan): array
    {
        $alur = $laporan->jenis === JenisLaporan::Wbs
            ? [
                StatusLaporan::Diterima->value => 'Diterima',
                StatusLaporan::Ditelaah->value => 'Ditelaah',
                StatusLaporan::Diinvestigasi->value => 'Diinvestigasi',
                StatusLaporan::Selesai->value => 'Selesai',
            ]
            : [
                StatusLaporan::Diterima->value => 'Diterima',
                StatusLaporan::Terverifikasi->value => 'Diperiksa admin',
                StatusLaporan::Diproses->value => 'Diproses unit',
                StatusLaporan::MenungguKonfirmasi->value => 'Konfirmasi Anda',
                StatusLaporan::Selesai->value => 'Selesai',
            ];

        $waktu = self::waktuTerakhir($laporan);
        $status = $laporan->status === StatusLaporan::DiinvestigasiDiperpanjang ? StatusLaporan::Diinvestigasi : $laporan->status;
        $posisi = array_search($status->value, array_keys($alur), true);

        if ($posisi === false) {
            $dilalui = array_filter($alur, fn (string $label, string $nilai): bool => isset($waktu[$nilai]), ARRAY_FILTER_USE_BOTH);

            return [
                ...array_map(fn (string $nilai): array => ['label' => $alur[$nilai], 'waktu' => $waktu[$nilai], 'keadaan' => self::SELESAI], array_keys($dilalui)),
                ['label' => $laporan->status->getLabel(), 'waktu' => $waktu[$laporan->status->value] ?? null, 'keadaan' => self::SELESAI],
            ];
        }

        $tahapan = [];

        foreach (array_keys($alur) as $urutan => $nilai) {
            $keadaan = match (true) {
                $urutan < $posisi, $urutan === $posisi && $status === StatusLaporan::Selesai => self::SELESAI,
                $urutan === $posisi => self::BERJALAN,
                default => self::MENUNGGU,
            };

            $tahapan[] = [
                'label' => $alur[$nilai],
                'waktu' => $keadaan === self::MENUNGGU ? null : ($waktu[$nilai] ?? null),
                'keadaan' => $keadaan,
            ];
        }

        return $tahapan;
    }

    /**
     * Waktu terakhir laporan masuk ke setiap status; Diterima memakai waktu laporan dibuat bila tidak ada riwayat.
     *
     * @return array<string, string>
     */
    private static function waktuTerakhir(Laporan $laporan): array
    {
        $waktu = [StatusLaporan::Diterima->value => WaktuLokal::tampilkan($laporan->dibuat_pada, 'd M, H.i')];

        $laporan->riwayatStatus
            ->sortBy('id')
            ->each(function (RiwayatStatus $riwayat) use (&$waktu): void {
                $nilai = $riwayat->status_baru === StatusLaporan::DiinvestigasiDiperpanjang ? StatusLaporan::Diinvestigasi->value : $riwayat->status_baru->value;
                $waktu[$nilai] = WaktuLokal::tampilkan($riwayat->waktu, 'd M, H.i');
            });

        return $waktu;
    }
}
