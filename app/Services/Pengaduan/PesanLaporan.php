<?php

namespace App\Services\Pengaduan;

use App\Enums\AksiAudit;
use App\Enums\PengirimPesan;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\Pesan;
use App\Models\User;
use App\Services\Lampiran\PelampirUnggahan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Kanal pesan dua arah pengaduan (UC-05, US-09, F-06): pelapor dan Admin Pengaduan saling berkirim pesan.
 * Pelapor boleh menyertakan lampiran tambahan lewat unggah bertahap yang sudah ada (dipindai lewat queue).
 * Admin boleh menulis catatan internal yang tidak terlihat pelapor. PIC Unit tidak melihat kanal ini.
 */
class PesanLaporan
{
    public const MAKS_ISI = 2000;

    public function __construct(private PelampirUnggahan $pelampir) {}

    /**
     * Dipanggil halaman Lacak (H-07) setelah pelapor membuktikan kode lacaknya.
     *
     * @param  list<string>  $idUnggahan  unggahan pengaduan yang sudah selesai diperiksa
     */
    public function kirimDariPelapor(Laporan $laporan, string $isi, array $idUnggahan = []): Pesan
    {
        $isi = $this->periksaIsi($isi);

        return DB::transaction(function () use ($laporan, $isi, $idUnggahan): Pesan {
            $pesan = $laporan->pesan()->create([
                'pengirim' => PengirimPesan::Pelapor,
                'isi' => $isi,
                'internal' => false,
            ]);

            $this->pelampir->lampirkan($laporan, $idUnggahan, $pesan);
            LogAudit::catat(null, AksiAudit::PelaporMengirimPesan, $laporan);

            return $pesan;
        });
    }

    /**
     * Pesan Admin Pengaduan dari H-11. Catatan internal tidak tampil di halaman Lacak.
     */
    public function kirimDariPetugas(Laporan $laporan, User $petugas, string $isi, bool $internal): Pesan
    {
        Gate::forUser($petugas)->authorize('kelolaPengaduan', $laporan);

        $isi = $this->periksaIsi($isi);

        return DB::transaction(function () use ($laporan, $petugas, $isi, $internal): Pesan {
            $pesan = $laporan->pesan()->create([
                'pengirim' => PengirimPesan::Petugas,
                'pengirim_user_id' => $petugas->getKey(),
                'isi' => $isi,
                'internal' => $internal,
            ]);

            LogAudit::catat($petugas, AksiAudit::MembalasPesan, $laporan);

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
