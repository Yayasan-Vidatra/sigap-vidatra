<?php

namespace App\Jobs;

use App\Enums\JenisBerkas;
use App\Enums\StatusPindai;
use App\Events\LampiranDikarantina;
use App\Models\Lampiran;
use App\Services\Lampiran\PembersihMetadata;
use App\Services\Lampiran\PemindaiVirus;
use App\Services\Lampiran\PenyimpananLampiran;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Memindai virus dan membersihkan metadata satu lampiran, di luar request (UC-17).
 *
 * Bekerja pada salinan sementara berupa isi asli: lampiran WBS didekripsi dulu, lalu dienkripsi ulang.
 * Berkas berbahaya dipindah ke karantina tanpa diubah, dan pelapor diberi tahu (UC-01 alur 5a);
 * laporannya tetap tersimpan.
 */
class ProsesLampiran implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public Lampiran $lampiran) {}

    public function handle(PemindaiVirus $pemindai, PembersihMetadata $pembersih, PenyimpananLampiran $penyimpanan): void
    {
        $lampiran = $this->lampiran;

        if ($lampiran->status_pindai !== StatusPindai::Menunggu) {
            return;
        }

        $terenkripsi = $lampiran->terenkripsi();
        $sementara = $penyimpanan->keBerkasSementara($lampiran->path_acak, $terenkripsi);

        try {
            $hasil = $pemindai->pindai($sementara);

            if ($hasil['status'] === StatusPindai::Karantina) {
                $lampiran->update([
                    'status_pindai' => StatusPindai::Karantina,
                    'path_acak' => $penyimpanan->pindahKeKarantina($lampiran->path_acak),
                ]);

                LampiranDikarantina::dispatch($lampiran, (string) $hasil['keterangan']);

                return;
            }

            $jenis = JenisBerkas::dariMime($lampiran->mime);

            if ($jenis !== null && $pembersih->bersihkan($sementara, $jenis, $lampiran->mime)) {
                $penyimpanan->gantiIsi($lampiran->path_acak, $sementara, $terenkripsi);
                $lampiran->ukuran = (int) filesize($sementara);
                $lampiran->hash_sha256 = (string) hash_file('sha256', $sementara);
            }

            $lampiran->status_pindai = $hasil['status'];
            $lampiran->save();
        } finally {
            @unlink($sementara);
        }
    }

    public function failed(?Throwable $galat): void
    {
        $this->lampiran->update(['status_pindai' => StatusPindai::Gagal]);

        Log::error('Pemindaian lampiran gagal setelah beberapa percobaan.', [
            'lampiran_id' => $this->lampiran->getKey(),
            'galat' => $galat?->getMessage(),
        ]);
    }
}
