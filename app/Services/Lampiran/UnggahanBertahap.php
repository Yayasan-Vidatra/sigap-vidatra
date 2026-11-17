<?php

namespace App\Services\Lampiran;

use App\Enums\JenisBerkas;
use App\Enums\JenisLaporan;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Unggah lampiran bertahap per potongan agar video 100 MB tahan koneksi putus (US-04, NF-03).
 *
 * Alur: mulai() mencatat nama dan ukuran, lalu mengembalikan id acak; simpanPotongan() menyimpan tiap
 * potongan (boleh diulang); selesaikan() menggabungkan dan memeriksa isinya dengan PemeriksaBerkas.
 * Id unggahan yang siap dipakai formulir saat laporan dikirim; setelah dipakai, id itu tidak berlaku lagi.
 * Berkas tunggu ada di disk privat "lampiran" folder unggahan/ dan kedaluwarsa setelah masa_unggahan_jam.
 */
class UnggahanBertahap
{
    public const FOLDER = 'unggahan';

    private const AWALAN_CACHE = 'unggahan-lampiran:';

    public function __construct(private PemeriksaBerkas $pemeriksa) {}

    /**
     * @return array{id: string, ukuran_potongan: int, jumlah_potongan: int}
     *
     * @throws BerkasDitolak
     */
    public function mulai(string $namaAsli, int $ukuran, JenisLaporan $kanal): array
    {
        if ($ukuran <= 0) {
            throw BerkasDitolak::kosong($namaAsli);
        }

        if ($ukuran > $this->ukuranTerbesarUntuk($kanal)) {
            throw BerkasDitolak::terlaluBesar($namaAsli, 'lampiran', intdiv($this->ukuranTerbesarUntuk($kanal), 1024 * 1024));
        }

        $id = Str::random(40);
        $jumlahPotongan = (int) ceil($ukuran / $this->ukuranPotongan());

        Cache::put(self::AWALAN_CACHE.$id, [
            'nama_asli' => $namaAsli,
            'ukuran' => $ukuran,
            'kanal' => $kanal->value,
            'jumlah_potongan' => $jumlahPotongan,
            'siap' => false,
        ], $this->masaBerlaku());

        $this->disk()->makeDirectory(self::FOLDER.'/'.$id);

        return ['id' => $id, 'ukuran_potongan' => $this->ukuranPotongan(), 'jumlah_potongan' => $jumlahPotongan];
    }

    /**
     * @return bool false bila id tidak dikenal, sudah selesai, indeks di luar rentang, atau ukuran potongan salah
     */
    public function simpanPotongan(string $id, int $indeks, UploadedFile $potongan): bool
    {
        $data = $this->data($id);

        if ($data === null || $data['siap'] || $indeks < 0 || $indeks >= $data['jumlah_potongan']) {
            return false;
        }

        $terakhir = $indeks === $data['jumlah_potongan'] - 1;
        $ukuranSeharusnya = $terakhir
            ? $data['ukuran'] - $indeks * $this->ukuranPotongan()
            : $this->ukuranPotongan();

        if ($potongan->getSize() !== $ukuranSeharusnya) {
            return false;
        }

        $this->disk()->putFileAs(self::FOLDER.'/'.$id, $potongan, 'potongan-'.$indeks);

        return true;
    }

    /**
     * Menggabungkan potongan dan memeriksa isi berkas.
     *
     * @return array{id: string, nama_asli: string, ukuran: int, jenis: string}|null null bila id tidak dikenal atau potongan belum lengkap
     *
     * @throws BerkasDitolak berkas tunggu dihapus sebelum pengecualian dilempar
     */
    public function selesaikan(string $id): ?array
    {
        $data = $this->data($id);

        if ($data === null) {
            return null;
        }

        if ($data['siap']) {
            return $this->ringkasan($id, $data);
        }

        $folder = self::FOLDER.'/'.$id;

        for ($i = 0; $i < $data['jumlah_potongan']; $i++) {
            if (! $this->disk()->exists("{$folder}/potongan-{$i}")) {
                return null;
            }
        }

        $gabungan = $this->disk()->path("{$folder}/gabungan");
        $keluaran = fopen($gabungan, 'wb');

        for ($i = 0; $i < $data['jumlah_potongan']; $i++) {
            $masukan = fopen($this->disk()->path("{$folder}/potongan-{$i}"), 'rb');
            stream_copy_to_stream($masukan, $keluaran);
            fclose($masukan);
            $this->disk()->delete("{$folder}/potongan-{$i}");
        }

        fclose($keluaran);

        try {
            $hasil = $this->pemeriksa->periksa($gabungan, $data['nama_asli'], JenisLaporan::from($data['kanal']));
        } catch (BerkasDitolak $penolakan) {
            $this->batal($id);

            throw $penolakan;
        }

        $data = [
            ...$data,
            'siap' => true,
            'jenis' => $hasil['jenis']->value,
            'mime' => $hasil['mime'],
            'ukuran' => $hasil['ukuran'],
            'hash_sha256' => hash_file('sha256', $gabungan),
        ];

        Cache::put(self::AWALAN_CACHE.$id, $data, $this->masaBerlaku());

        return $this->ringkasan($id, $data);
    }

    /**
     * Unggahan siap untuk kanal tertentu, untuk dilampirkan ke laporan.
     *
     * @return array{nama_asli: string, ukuran: int, jenis: JenisBerkas, mime: string, hash_sha256: string, path: string}|null
     */
    public function ambilSiap(string $id, JenisLaporan $kanal): ?array
    {
        $data = $this->data($id);

        if ($data === null || ! $data['siap'] || $data['kanal'] !== $kanal->value) {
            return null;
        }

        return [
            'nama_asli' => $data['nama_asli'],
            'ukuran' => $data['ukuran'],
            'jenis' => JenisBerkas::from($data['jenis']),
            'mime' => $data['mime'],
            'hash_sha256' => $data['hash_sha256'],
            'path' => $this->disk()->path(self::FOLDER.'/'.$id.'/gabungan'),
        ];
    }

    /**
     * Menghapus unggahan beserta berkas tunggunya; dipakai saat dibatalkan, ditolak, atau sudah dilampirkan.
     */
    public function batal(string $id): void
    {
        Cache::forget(self::AWALAN_CACHE.$id);
        $this->disk()->deleteDirectory(self::FOLDER.'/'.$id);
    }

    /**
     * Menghapus folder unggahan yang lebih tua dari masa berlakunya (dijalankan terjadwal).
     */
    public function bersihkanKedaluwarsa(): int
    {
        $batas = now()->subHours((int) config('sigap.lampiran.masa_unggahan_jam'))->getTimestamp();
        $dihapus = 0;

        foreach ($this->disk()->directories(self::FOLDER) as $folder) {
            if (filemtime($this->disk()->path($folder)) < $batas) {
                $this->batal(basename($folder));
                $dihapus++;
            }
        }

        return $dihapus;
    }

    public function ukuranPotongan(): int
    {
        return (int) config('sigap.lampiran.ukuran_potongan_mb') * 1024 * 1024;
    }

    private function ukuranTerbesarUntuk(JenisLaporan $kanal): int
    {
        return collect(JenisBerkas::cases())
            ->filter(fn (JenisBerkas $jenis): bool => $jenis->bolehUntuk($kanal))
            ->map(fn (JenisBerkas $jenis): int => $jenis->maksByte())
            ->max();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function data(string $id): ?array
    {
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $id)) {
            return null;
        }

        $data = Cache::get(self::AWALAN_CACHE.$id);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: string, nama_asli: string, ukuran: int, jenis: string}
     */
    private function ringkasan(string $id, array $data): array
    {
        return ['id' => $id, 'nama_asli' => $data['nama_asli'], 'ukuran' => $data['ukuran'], 'jenis' => $data['jenis']];
    }

    private function masaBerlaku(): \DateTimeInterface
    {
        return now()->addHours((int) config('sigap.lampiran.masa_unggahan_jam'));
    }

    private function disk(): Filesystem
    {
        return Storage::disk('lampiran');
    }
}
