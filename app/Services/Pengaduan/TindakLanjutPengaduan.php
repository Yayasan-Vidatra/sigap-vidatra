<?php

namespace App\Services\Pengaduan;

use App\Enums\AksiAudit;
use App\Enums\JenisLaporan;
use App\Enums\StatusLaporan;
use App\Enums\StatusPindai;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Jobs\ProsesLampiran;
use App\Models\Disposisi;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\User;
use App\Services\Lampiran\BerkasDitolak;
use App\Services\Lampiran\PemeriksaBerkas;
use App\Services\Lampiran\PenyimpananLampiran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Tindak lanjut PIC Unit di Pengaduan Unit Saya (H-12, UC-10, US-12):
 * - mencatat tindak lanjut pada disposisi unitnya, selama pengaduan Diproses;
 * - mengunggah bukti penyelesaian sebagai lampiran yang terikat pada disposisi (lampiran.disposisi_id),
 *   diperiksa, disimpan dengan nama acak di disk privat, lalu dipindai lewat queue (Rancangan r1 Bagian 8);
 * - meminta konfirmasi pelapor: Diproses → Menunggu Konfirmasi, hanya bila tindak lanjut sudah diisi.
 *
 * Hak akses (tindakLanjutiPengaduan) diperiksa di server untuk setiap aksi.
 */
class TindakLanjutPengaduan
{
    public function __construct(
        private PengubahStatus $pengubah,
        private PemeriksaBerkas $pemeriksa,
        private PenyimpananLampiran $penyimpanan,
    ) {}

    public static function bisaDitindaklanjuti(Laporan $laporan): bool
    {
        return $laporan->status === StatusLaporan::Diproses;
    }

    /**
     * Disposisi terbaru ke unit PIC, yang menjadi tempat tindak lanjut dan bukti dicatat.
     */
    public static function disposisiUnit(Laporan $laporan, User $pic): ?Disposisi
    {
        return $laporan->disposisi()->where('unit_id', $pic->unit_id)->latest('id')->first();
    }

    public function simpanTindakLanjut(Laporan $laporan, User $pic, string $tindakLanjut): Disposisi
    {
        Gate::forUser($pic)->authorize('tindakLanjutiPengaduan', $laporan);
        $this->pastikanDiproses($laporan, 'dicatat tindak lanjutnya');

        $disposisi = self::disposisiUnit($laporan, $pic) ?? throw AksiPengaduanTidakSah::bukanDisposisiUnit();
        $disposisi->update(['tindak_lanjut' => filled($tindakLanjut) ? trim($tindakLanjut) : null]);

        LogAudit::catat($pic, AksiAudit::MencatatTindakLanjut, $laporan);

        return $disposisi;
    }

    /**
     * @param  list<array{path: string, nama_asli: string}>  $berkas  berkas sementara yang sudah diunggah
     * @return list<Lampiran>
     *
     * @throws BerkasDitolak
     */
    public function lampirkanBukti(Laporan $laporan, User $pic, array $berkas): array
    {
        Gate::forUser($pic)->authorize('tindakLanjutiPengaduan', $laporan);
        $this->pastikanDiproses($laporan, 'diberi bukti penyelesaian');

        $disposisi = self::disposisiUnit($laporan, $pic) ?? throw AksiPengaduanTidakSah::bukanDisposisiUnit();

        $diperiksa = array_map(fn (array $item): array => [
            ...$item,
            ...$this->pemeriksa->periksa($item['path'], $item['nama_asli'], JenisLaporan::Pengaduan),
        ], $berkas);

        return DB::transaction(function () use ($laporan, $pic, $disposisi, $diperiksa): array {
            $lampiran = [];

            foreach ($diperiksa as $item) {
                $lampiran[] = $bukti = $laporan->lampiran()->create([
                    'disposisi_id' => $disposisi->getKey(),
                    'nama_asli' => $item['nama_asli'],
                    'path_acak' => $this->penyimpanan->simpan($item['path'], enkripsi: false),
                    'mime' => $item['mime'],
                    'ukuran' => $item['ukuran'],
                    'hash_sha256' => hash_file('sha256', $item['path']),
                    'status_pindai' => StatusPindai::Menunggu,
                ]);

                LogAudit::catat($pic, AksiAudit::MengunggahBuktiPenyelesaian, $bukti);
                ProsesLampiran::dispatch($bukti)->afterCommit();
            }

            return $lampiran;
        });
    }

    public function mintaKonfirmasi(Laporan $laporan, User $pic): Laporan
    {
        Gate::forUser($pic)->authorize('tindakLanjutiPengaduan', $laporan);

        $periksaStatus = PengubahStatus::dariStatus([StatusLaporan::Diproses], 'dimintakan konfirmasi');

        return $this->pengubah->ubah(
            $laporan,
            $pic,
            function (Laporan $terkunci) use ($periksaStatus, $pic): ?AksiPengaduanTidakSah {
                if ($galat = $periksaStatus($terkunci)) {
                    return $galat;
                }

                $disposisi = self::disposisiUnit($terkunci, $pic);

                return match (true) {
                    $disposisi === null => AksiPengaduanTidakSah::bukanDisposisiUnit(),
                    blank($disposisi->tindak_lanjut) => AksiPengaduanTidakSah::tindakLanjutKosong(),
                    default => null,
                };
            },
            StatusLaporan::MenungguKonfirmasi,
            AksiAudit::MemintaKonfirmasiPelapor,
            function (Laporan $terkunci) use ($pic): ?string {
                self::disposisiUnit($terkunci, $pic)?->update(['selesai_pada' => now()]);

                return null;
            },
        );
    }

    private function pastikanDiproses(Laporan $laporan, string $kataKerja): void
    {
        if (! self::bisaDitindaklanjuti($laporan->fresh() ?? $laporan)) {
            throw AksiPengaduanTidakSah::statusTidakSesuai($kataKerja, ($laporan->fresh() ?? $laporan)->status);
        }
    }
}
