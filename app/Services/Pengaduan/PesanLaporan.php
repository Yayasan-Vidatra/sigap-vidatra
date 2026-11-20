<?php

namespace App\Services\Pengaduan;

use App\Enums\AksiAudit;
use App\Enums\PengirimPesan;
use App\Enums\StatusLaporan;
use App\Events\PesanLaporanTerkirim;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\Pesan;
use App\Models\User;
use App\Services\Lampiran\PelampirUnggahan;
use App\Services\Lampiran\PenyimpanTautanBukti;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Kanal pesan dua arah (UC-05, US-09, F-06): pelapor dan Admin Pengaduan saling berkirim pesan. Pelapor WBS
 * memakai kanal yang sama dari halaman Lacak; balasan Tim WBS menyusul bersama Ruang Kerja WBS (Hari 10).
 * Pelapor boleh menyertakan lampiran tambahan lewat unggah bertahap yang sudah ada (dipindai lewat queue).
 * Admin boleh menulis catatan internal yang tidak terlihat pelapor. PIC Unit tidak melihat kanal ini.
 * Setelah Selesai atau Ditolak, pelapor tidak bisa mengirim pesan lagi; Admin tetap bisa. Pengaduan yang dipindah ke
 * WBS tertutup bagi pelapor dan Admin, karena percakapannya ikut pindah ke laporan WBS. Setiap pesan
 * memicu PesanLaporanTerkirim untuk surel pemberitahuan tanpa isi pesan.
 */
class PesanLaporan
{
    public const MAKS_ISI = 2000;

    /**
     * Status yang menutup kanal pesan bagi pelapor.
     *
     * @var list<StatusLaporan>
     */
    public const STATUS_TERTUTUP_BAGI_PELAPOR = [
        StatusLaporan::Selesai, StatusLaporan::Ditolak, StatusLaporan::TidakDitindaklanjuti, StatusLaporan::DipindahKeWbs,
    ];

    public function __construct(
        private PelampirUnggahan $pelampir,
        private PenyimpanTautanBukti $tautan,
    ) {}

    public static function pelaporBolehMengirim(Laporan $laporan): bool
    {
        return ! in_array($laporan->status, self::STATUS_TERTUTUP_BAGI_PELAPOR, true);
    }

    /**
     * Dipanggil halaman Lacak (H-07) setelah pelapor membuktikan kode lacaknya.
     *
     * @param  list<string>  $idUnggahan  unggahan pengaduan yang sudah selesai diperiksa
     * @param  list<string>  $tautan  tautan Google Drive yang sudah lolos validasi dan disetujui pelapor
     */
    public function kirimDariPelapor(Laporan $laporan, string $isi, array $idUnggahan = [], array $tautan = []): Pesan
    {
        $isi = $this->periksaIsi($isi);

        if (! self::pelaporBolehMengirim($laporan->fresh() ?? $laporan)) {
            throw AksiPengaduanTidakSah::laporanDitutup();
        }

        return DB::transaction(function () use ($laporan, $isi, $idUnggahan, $tautan): Pesan {
            $pesan = $laporan->pesan()->create([
                'pengirim' => PengirimPesan::Pelapor,
                'isi' => $isi,
                'internal' => false,
            ]);

            $this->pelampir->lampirkan($laporan, $idUnggahan, $pesan);
            $this->tautan->simpan($laporan, $tautan, $pesan);
            LogAudit::catat(null, AksiAudit::PelaporMengirimPesan, $laporan);
            PesanLaporanTerkirim::dispatch($pesan);

            return $pesan;
        });
    }

    /**
     * Pesan Admin Pengaduan dari H-11, atau Tim WBS/penelaah dari H-13 (tampil di Lacak sebagai "Tim WBS").
     * Catatan internal tidak pernah tampil di halaman Lacak.
     */
    public function kirimDariPetugas(Laporan $laporan, User $petugas, string $isi, bool $internal): Pesan
    {
        Gate::forUser($petugas)->authorize($laporan->isWbs() ? 'tanganiLaporanWbs' : 'kelolaPengaduan', $laporan);

        $isi = $this->periksaIsi($isi);

        if (! $laporan->isWbs() && ($laporan->fresh() ?? $laporan)->status === StatusLaporan::DipindahKeWbs) {
            throw AksiPengaduanTidakSah::laporanDitutup();
        }

        return DB::transaction(function () use ($laporan, $petugas, $isi, $internal): Pesan {
            $pesan = $laporan->pesan()->create([
                'pengirim' => PengirimPesan::Petugas,
                'pengirim_user_id' => $petugas->getKey(),
                'isi' => $isi,
                'internal' => $internal,
            ]);

            LogAudit::catat($petugas, AksiAudit::MembalasPesan, $laporan);
            PesanLaporanTerkirim::dispatch($pesan);

            return $pesan;
        });
    }

    private function periksaIsi(string $isi): string
    {
        $isi = trim($isi);

        if ($isi === '') {
            throw AksiPengaduanTidakSah::pesanKosong();
        }

        return mb_substr($isi, 0, self::MAKS_ISI);
    }
}
