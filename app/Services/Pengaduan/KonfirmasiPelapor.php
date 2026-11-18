<?php

namespace App\Services\Pengaduan;

use App\Enums\AksiAudit;
use App\Enums\JenisLaporan;
use App\Enums\StatusLaporan;
use App\Events\HasilDitolakPelapor;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Models\Laporan;
use App\Models\RiwayatStatus;
use App\Services\WaktuLokal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Tanggapan pelapor atas hasil tindak lanjut (UC-06, US-13; Rancangan r1 Bagian 6) dan penutupan otomatis:
 * - konfirmasi: Menunggu Konfirmasi → Selesai;
 * - tolak hasil dengan alasan: Menunggu Konfirmasi → Diproses, lalu Admin diberi tahu (HasilDitolakPelapor);
 * - tanpa tanggapan selama 7 hari kalender WITA: Selesai oleh sistem.
 *
 * Halaman Lacak (H-07, Hari 8) memanggil konfirmasi() dan tolakHasil() setelah pelapor membuktikan kode
 * lacaknya; layanan ini tidak memeriksa sesi pelapor.
 */
class KonfirmasiPelapor
{
    public const CATATAN_TUTUP_OTOMATIS = 'Ditutup otomatis karena tidak ada tanggapan pelapor dalam 7 hari.';

    public function __construct(private PengubahStatus $pengubah) {}

    public function konfirmasi(Laporan $laporan): Laporan
    {
        return $this->pengubah->ubah(
            $this->pastikanPengaduan($laporan),
            null,
            PengubahStatus::dariStatus([StatusLaporan::MenungguKonfirmasi], 'dikonfirmasi'),
            StatusLaporan::Selesai,
            AksiAudit::PelaporMengonfirmasiHasil,
            beritahuPelapor: false,
        );
    }

    public function tolakHasil(Laporan $laporan, string $alasan): Laporan
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw AksiPengaduanTidakSah::alasanTolakHasilWajib();
        }

        $laporan = $this->pengubah->ubah(
            $this->pastikanPengaduan($laporan),
            null,
            PengubahStatus::dariStatus([StatusLaporan::MenungguKonfirmasi], 'ditolak hasilnya'),
            StatusLaporan::Diproses,
            AksiAudit::PelaporMenolakHasil,
            function (Laporan $terkunci): ?string {
                $terkunci->disposisi()->latest('id')->first()?->update(['selesai_pada' => null]);

                return null;
            },
            catatanPelapor: $alasan,
            beritahuPelapor: false,
        );

        HasilDitolakPelapor::dispatch($laporan, $laporan->riwayatStatus()->latest('id')->firstOrFail());

        return $laporan;
    }

    /**
     * Menutup pengaduan Menunggu Konfirmasi yang batas tanggapannya sudah lewat. Dipanggil perintah
     * terjadwal sigap:tutup-pengaduan-otomatis.
     */
    public function tutupOtomatis(Laporan $laporan, ?CarbonInterface $sekarang = null): ?Laporan
    {
        $batas = self::batasTanggapan($laporan);

        if ($batas === null || ($sekarang ?? now())->lessThan($batas)) {
            return null;
        }

        return $this->pengubah->ubah(
            $laporan,
            null,
            PengubahStatus::dariStatus([StatusLaporan::MenungguKonfirmasi], 'ditutup otomatis'),
            StatusLaporan::Selesai,
            AksiAudit::MenutupPengaduanOtomatis,
            catatanPelapor: self::CATATAN_TUTUP_OTOMATIS,
        );
    }

    /**
     * Pelapor boleh menanggapi sampai akhir hari ke-7 (WITA) setelah tanggal konfirmasi diminta. Contoh:
     * diminta Rabu 18 Nov pukul berapa pun, tanggapan diterima sampai Rabu 25 Nov 23.59 WITA, dan
     * pengaduan ditutup mulai Kamis 26 Nov 00.00 WITA. Hasilnya dalam zona waktu aplikasi (UTC).
     */
    public static function batasTanggapan(Laporan $laporan): ?CarbonImmutable
    {
        if ($laporan->status !== StatusLaporan::MenungguKonfirmasi) {
            return null;
        }

        /** @var RiwayatStatus|null $diminta */
        $diminta = $laporan->riwayatStatus()
            ->where('status_baru', StatusLaporan::MenungguKonfirmasi->value)
            ->latest('id')
            ->first();

        if ($diminta === null) {
            return null;
        }

        return WaktuLokal::keLokal($diminta->waktu)
            ->startOfDay()
            ->addDays((int) config('sigap.pengaduan.hari_konfirmasi') + 1)
            ->setTimezone(config('app.timezone'));
    }

    private function pastikanPengaduan(Laporan $laporan): Laporan
    {
        if ($laporan->jenis !== JenisLaporan::Pengaduan) {
            throw AksiPengaduanTidakSah::statusTidakSesuai('dikonfirmasi', $laporan->status);
        }

        return $laporan;
    }
}
