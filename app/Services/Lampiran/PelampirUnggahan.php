<?php

namespace App\Services\Lampiran;

use App\Enums\StatusPindai;
use App\Jobs\ProsesLampiran;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\Pesan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menjadikan unggahan bertahap yang sudah siap sebagai lampiran laporan: isi dipindah ke nama acak
 * (dienkripsi bila WBS), baris lampiran dibuat berstatus menunggu, lalu pemindaian dan penghapusan
 * metadata dijadwalkan di queue setelah transaksi tersimpan. Dipanggil di dalam transaksi pembuatan laporan.
 */
class PelampirUnggahan
{
    public function __construct(
        private UnggahanBertahap $unggahan,
        private PenyimpananLampiran $penyimpanan,
    ) {}

    /**
     * @param  list<string>  $idUnggahan
     * @param  Pesan|null  $pesan  pesan pelapor yang membawa lampiran tambahan (UC-05)
     * @return list<Lampiran>
     */
    public function lampirkan(Laporan $laporan, array $idUnggahan, ?Pesan $pesan = null): array
    {
        $lampiran = [];

        foreach ($idUnggahan as $id) {
            $siap = $this->unggahan->ambilSiap($id, $laporan->jenis)
                ?? throw new RuntimeException('Unggahan lampiran tidak lagi tersedia.');

            $pathAcak = $this->penyimpanan->simpan($siap['path'], enkripsi: $laporan->isWbs());

            $lampiran[] = $laporan->lampiran()->create([
                'pesan_id' => $pesan?->getKey(),
                'nama_asli' => $siap['nama_asli'],
                'path_acak' => $pathAcak,
                'mime' => $siap['mime'],
                'ukuran' => $siap['ukuran'],
                'hash_sha256' => $siap['hash_sha256'],
                'status_pindai' => StatusPindai::Menunggu,
            ]);

            DB::afterCommit(fn () => $this->unggahan->batal($id));
        }

        foreach ($lampiran as $item) {
            ProsesLampiran::dispatch($item)->afterCommit();
        }

        return $lampiran;
    }
}
