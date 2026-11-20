<?php

namespace App\Services\Wbs;

use App\Enums\AksiAudit;
use App\Enums\JenisLaporan;
use App\Enums\KedudukanTerlapor;
use App\Enums\StatusLaporan;
use App\Enums\StatusPindai;
use App\Enums\SumberLaporan;
use App\Exceptions\AksiWbsTidakSah;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\User;
use App\Services\Lampiran\PenyimpananLampiran;
use App\Services\Pengaduan\PengubahStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Pindah ke WBS (H-11, UC-09 1a, Rancangan r1 Bagian 6 baris "Dipindah ke WBS"): Admin Pengaduan memindahkan
 * pengaduan berisi dugaan pelanggaran serius ke kanal WBS dengan kode lacak baru.
 *
 * - Hanya dari status yang belum akhir. Admin memilih jenis pelanggaran, kedudukan terlapor, dan pihak terlapor;
 *   laporan WBS baru dirutekan menurut matriks 3.1 (PembuatLaporanWbs memberi tahu penelaah tanpa isi laporan).
 * - Isi pengaduan (judul, kronologi, waktu, lokasi), identitas, percakapan, dan lampiran pelapor dipindah ke laporan
 *   WBS dan disimpan terenkripsi seperti WBS biasa; pengaduan asal berstatus Dipindah ke WBS dan isinya dikosongkan,
 *   sehingga Admin dan PIC tidak dapat membacanya lagi dari mana pun.
 * - Pelapor tidak otomatis mendapat akses WBS: PIN acak awal tidak disimpan di mana pun. Penelaah menurut matriks
 *   membuat kode akses lewat buatAksesPelapor() dan menyampaikannya melalui saluran asal (seperti UC-18 langkah 3).
 */
class PemindahPengaduanKeWbs
{
    /**
     * Status pengaduan yang belum akhir.
     *
     * @var list<StatusLaporan>
     */
    public const STATUS_ASAL = [
        StatusLaporan::Diterima, StatusLaporan::Terverifikasi, StatusLaporan::Diproses, StatusLaporan::MenungguKonfirmasi,
    ];

    /**
     * Judul dan kronologi pengganti pada pengaduan asal. Tampil juga di halaman Lacak pelapor, sehingga sejak revisi 20-11
     * tidak menyebut kanal WBS internal.
     */
    public const JUDUL_PENGGANTI = 'Dialihkan ke tim khusus';

    public const KRONOLOGI_PENGGANTI = 'Isi pengaduan ini dialihkan ke tim penanganan khusus yayasan dan tidak lagi tersedia di sini.';

    public function __construct(
        private PembuatLaporanWbs $pembuat,
        private PengubahStatus $pengubah,
        private PenyimpananLampiran $penyimpanan,
    ) {}

    public static function bisa(Laporan $laporan): bool
    {
        return ! $laporan->isWbs() && in_array($laporan->status, self::STATUS_ASAL, true);
    }

    /**
     * @param  array{kategori_id: int|string, kedudukan_terlapor: string|KedudukanTerlapor, terlapor: list<array{nama?: string|null, jabatan?: string|null}>}  $data
     * @return Laporan laporan WBS baru
     */
    public function pindahkan(Laporan $pengaduan, User $oleh, array $data): Laporan
    {
        Gate::forUser($oleh)->authorize('kelolaPengaduan', $pengaduan);

        $kategori = Kategori::query()->find($data['kategori_id']);

        if ($kategori === null || $kategori->jenis !== JenisLaporan::Wbs) {
            throw new AksiWbsTidakSah('Jenis pelanggaran WBS wajib dipilih.');
        }

        $kedudukan = $data['kedudukan_terlapor'] instanceof KedudukanTerlapor
            ? $data['kedudukan_terlapor']
            : KedudukanTerlapor::tryFrom((string) $data['kedudukan_terlapor']);

        if ($kedudukan === null) {
            throw AksiWbsTidakSah::isianWajib('Kedudukan terlapor');
        }

        $terlapor = array_values(array_filter(
            array_map(fn (array $item): array => [
                'nama' => trim((string) ($item['nama'] ?? '')),
                'jabatan' => $item['jabatan'] ?? null,
            ], $data['terlapor']),
            fn (array $item): bool => $item['nama'] !== '',
        ));

        if ($terlapor === [] || count($terlapor) > (int) config('sigap.wbs.maks_terlapor')) {
            throw AksiWbsTidakSah::isianWajib('Pihak terlapor');
        }

        /** @var list<string> $berkasBaru */
        $berkasBaru = [];
        /** @var list<string> $berkasLama */
        $berkasLama = [];
        $wbs = null;

        try {
            $this->pengubah->ubah(
                $pengaduan,
                $oleh,
                PengubahStatus::dariStatus(self::STATUS_ASAL, 'dipindah ke WBS'),
                StatusLaporan::DipindahKeWbs,
                AksiAudit::MemindahkanPengaduanKeWbs,
                function (Laporan $terkunci) use ($oleh, $kategori, $kedudukan, $terlapor, &$wbs, &$berkasBaru, &$berkasLama): string {
                    $wbs = $this->pembuat->buat([
                        'mode' => 'anonim',
                        'kategori_id' => $kategori->getKey(),
                        'unit_id' => $terkunci->unit_id,
                        'terlapor' => $terlapor,
                        'kedudukan_terlapor' => $kedudukan->value,
                        'judul' => $terkunci->judul,
                        'kronologi' => $terkunci->kronologi,
                        'waktu_kejadian' => null,
                        'lokasi' => $terkunci->lokasi,
                        'saksi' => $terkunci->saksi,
                    ], SumberLaporan::Pengaduan, PembuatLaporanWbs::pinSementara(), $oleh);

                    $wbs->forceFill(['waktu_kejadian' => $terkunci->waktu_kejadian])->save();

                    if ($identitas = $terkunci->identitasPelapor) {
                        $wbs->identitasPelapor()->create($identitas->only(['nama', 'email', 'no_hp', 'kontak_orang_tua', 'persetujuan_orang_tua']));
                    }

                    $terkunci->pesan()->update(['laporan_id' => $wbs->getKey()]);

                    foreach ($terkunci->lampiranPelapor()->get() as $lampiran) {
                        $pathBaru = $this->salinTerenkripsi($lampiran->path_acak, $lampiran->status_pindai);
                        $berkasBaru[] = $pathBaru;
                        $berkasLama[] = $lampiran->path_acak;

                        $lampiran->forceFill(['laporan_id' => $wbs->getKey(), 'path_acak' => $pathBaru])->save();
                    }

                    $terkunci->forceFill([
                        'judul' => self::JUDUL_PENGGANTI,
                        'kronologi' => self::KRONOLOGI_PENGGANTI,
                        'waktu_kejadian' => null,
                        'lokasi' => null,
                        'saksi' => null,
                        'jabatan_pihak_dikeluhkan' => null,
                    ]);

                    LogAudit::catat($oleh, AksiAudit::MemindahkanPengaduanKeWbs, $wbs);

                    return 'Isi, percakapan, dan lampiran pelapor dipindah ke kanal WBS. Kode lacak baru disampaikan Tim WBS.';
                },
            );
        } catch (Throwable $galat) {
            foreach ($berkasBaru as $path) {
                $this->penyimpanan->hapus($path);
            }

            throw $galat;
        }

        foreach ($berkasLama as $path) {
            $this->penyimpanan->hapus($path);
        }

        /** @var Laporan $wbs */
        return $wbs;
    }

    /**
     * Membuat PIN sementara baru untuk laporan WBS pindahan agar pelapor dapat melacaknya (UC-04). Hanya penelaah utama
     * menurut matriks yang boleh; PIN lama tidak berlaku lagi. PIN dikembalikan sekali dan tidak disimpan teks biasa.
     */
    public function buatAksesPelapor(Laporan $wbs, User $oleh): string
    {
        Gate::forUser($oleh)->authorize('telaahLaporanWbs', $wbs);

        if (! self::bisaBuatAksesPelapor($wbs)) {
            throw new AksiWbsTidakSah('Kode akses pelapor hanya dibuat untuk laporan yang dipindah dari kanal Pengaduan.');
        }

        $pin = PembuatLaporanWbs::pinSementara();

        DB::transaction(function () use ($wbs, $oleh, $pin): void {
            $wbs->forceFill(['pin_hash' => $pin])->save();
            LogAudit::catat($oleh, AksiAudit::MembuatPinSementaraPelapor, $wbs);
        });

        return $pin;
    }

    public static function bisaBuatAksesPelapor(Laporan $laporan): bool
    {
        return $laporan->isWbs() && $laporan->sumber === SumberLaporan::Pengaduan;
    }

    /**
     * Lampiran pengaduan tersimpan tanpa enkripsi; salinannya dienkripsi seperti lampiran WBS (Bagian 8). Berkas yang
     * dikarantina tetap di folder karantina.
     */
    private function salinTerenkripsi(string $pathLama, StatusPindai $status): string
    {
        $pathBaru = $this->penyimpanan->simpan($this->penyimpanan->disk()->path($pathLama), enkripsi: true);

        return $status === StatusPindai::Karantina ? $this->penyimpanan->pindahKeKarantina($pathBaru) : $pathBaru;
    }
}
