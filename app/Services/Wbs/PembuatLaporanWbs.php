<?php

namespace App\Services\Wbs;

use App\Enums\AksiAudit;
use App\Enums\JenisLaporan;
use App\Enums\KedudukanTerlapor;
use App\Enums\StatusLaporan;
use App\Enums\StatusPindai;
use App\Enums\SumberLaporan;
use App\Enums\TahapWbs;
use App\Events\LaporanWbsPerluDitelaah;
use App\Events\StatusLaporanBerubah;
use App\Exceptions\AksiWbsTidakSah;
use App\Http\Requests\StoreWbsRequest;
use App\Jobs\ProsesLampiran;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\User;
use App\Services\Lampiran\BerkasDitolak;
use App\Services\Lampiran\PemeriksaBerkas;
use App\Services\Lampiran\PenyimpananLampiran;
use App\Services\WaktuLokal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Membuat laporan WBS berstatus Diterima, dari Formulir WBS (H-04, UC-02) atau dari Catat Laporan Masuk (H-14,
 * UC-18). Isian sensitif terenkripsi lewat cast model; batas telaah 5 hari kerja dihitung sejak diterima; penelaah
 * menurut matriks diberi tahu tanpa isi laporan. Dipanggil di dalam transaksi oleh pemanggil.
 */
class PembuatLaporanWbs
{
    public function __construct(
        private PemeriksaBerkas $pemeriksa,
        private PenyimpananLampiran $penyimpanan,
    ) {}

    /**
     * @param  array{mode: string, nama?: string|null, email?: string|null, no_hp?: string|null, media_sosial?: string|null, kedudukan_lain?: string|null, kategori_id: int|string, unit_id: int|string, terlapor: list<array{nama: string, jabatan?: string|null}>, kedudukan_terlapor: string, judul: string, kronologi: string, waktu_kejadian?: string|null, lokasi?: string|null, saksi?: string|null}  $data
     */
    public function buat(array $data, SumberLaporan $sumber, string $pin, ?User $pencatat = null): Laporan
    {
        $laporan = Laporan::create([
            'kode_lacak' => Laporan::buatKodeLacak(JenisLaporan::Wbs),
            'jenis' => JenisLaporan::Wbs,
            'sumber' => $sumber,
            'unit_id' => $data['unit_id'],
            'kategori_id' => $data['kategori_id'],
            'judul' => $data['judul'],
            'kronologi' => $data['kronologi'],
            'waktu_kejadian' => filled($data['waktu_kejadian'] ?? null) ? WaktuLokal::dariMasukan((string) $data['waktu_kejadian']) : null,
            'lokasi' => self::teksAtauNull($data['lokasi'] ?? null),
            'saksi' => self::teksAtauNull($data['saksi'] ?? null),
            'kedudukan_terlapor' => $data['kedudukan_terlapor'],
            'status' => StatusLaporan::Diterima,
            'batas_waktu' => TahapWbs::Telaah->batasDari(now()),
            'pin_hash' => $pin,
        ]);

        // Kedudukan "Lain-lain" yang ditulis pelapor disimpan terenkripsi pada terlapor pertama (revisi 20-11).
        $kedudukanLain = $data['kedudukan_terlapor'] === KedudukanTerlapor::LainLain->value
            ? self::teksAtauNull($data['kedudukan_lain'] ?? null)
            : null;

        foreach (array_values($data['terlapor']) as $urutan => $terlapor) {
            $laporan->terlapor()->create([
                'nama' => trim((string) $terlapor['nama']),
                'jabatan' => self::teksAtauNull($terlapor['jabatan'] ?? null),
                'kedudukan_lain' => $urutan === 0 ? $kedudukanLain : null,
            ]);
        }

        if ($data['mode'] === 'bernama') {
            $laporan->identitasPelapor()->create([
                'nama' => trim((string) $data['nama']),
                'email' => self::teksAtauNull($data['email'] ?? null),
                'no_hp' => self::teksAtauNull($data['no_hp'] ?? null),
                'media_sosial' => self::teksAtauNull($data['media_sosial'] ?? null),
                'persetujuan_orang_tua' => false,
            ]);
        }

        $riwayat = $laporan->riwayatStatus()->create([
            'status_lama' => null,
            'status_baru' => StatusLaporan::Diterima,
            'oleh_user_id' => $pencatat?->getKey(),
            'catatan' => match (true) {
                $pencatat === null => null,
                $sumber === SumberLaporan::Pengaduan => 'Dipindah dari kanal Pengaduan oleh Admin Pengaduan.',
                default => "Dicatat dari saluran {$sumber->getLabel()}.",
            },
            'tampil_ke_pelapor' => false,
        ]);

        StatusLaporanBerubah::dispatch($laporan, $riwayat, false);
        LaporanWbsPerluDitelaah::dispatch($laporan);

        return $laporan;
    }

    /**
     * H-14 Catat Laporan Masuk (UC-18, US-18): laporan dari surel, telepon/WhatsApp, atau surat dicatat Ketua Tim WBS
     * dengan tangkapan layar atau pindaian sebagai lampiran WBS (terenkripsi, metadata dihapus lewat queue).
     * PIN sementara dibuat acak dan dikembalikan sekali untuk disampaikan kepada pelapor lewat saluran asal.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{path: string, nama_asli: string}>  $berkas
     * @return array{laporan: Laporan, pin: string}
     *
     * @throws BerkasDitolak
     */
    public function catatLaporanMasuk(User $pencatat, array $data, SumberLaporan $sumber, array $berkas): array
    {
        Gate::forUser($pencatat)->authorize('catatLaporanMasuk', Laporan::class);

        if (! in_array($sumber, SumberLaporan::saluranLaporanMasuk(), true)) {
            throw new AksiWbsTidakSah('Sumber laporan masuk harus surel, telepon/WhatsApp, atau surat.');
        }

        $diperiksa = array_map(fn (array $item): array => [
            ...$item,
            ...$this->pemeriksa->periksa($item['path'], $item['nama_asli'], JenisLaporan::Wbs),
        ], $berkas);

        $pin = self::pinSementara();

        $laporan = DB::transaction(function () use ($pencatat, $data, $sumber, $pin, $diperiksa): Laporan {
            $laporan = $this->buat($data, $sumber, $pin, $pencatat);

            foreach ($diperiksa as $item) {
                $lampiran = $laporan->lampiran()->create([
                    'nama_asli' => $item['nama_asli'],
                    'path_acak' => $this->penyimpanan->simpan($item['path'], enkripsi: true),
                    'mime' => $item['mime'],
                    'ukuran' => $item['ukuran'],
                    'hash_sha256' => hash_file('sha256', $item['path']),
                    'status_pindai' => StatusPindai::Menunggu,
                ]);

                ProsesLampiran::dispatch($lampiran)->afterCommit();
            }

            LogAudit::catat($pencatat, AksiAudit::MencatatLaporanMasuk, $laporan);

            return $laporan;
        });

        return ['laporan' => $laporan, 'pin' => $pin];
    }

    public static function pinSementara(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (StoreWbsRequest::pinMudahDitebak($pin));

        return $pin;
    }

    private static function teksAtauNull(mixed $nilai): ?string
    {
        return is_string($nilai) && trim($nilai) !== '' ? trim($nilai) : null;
    }
}
