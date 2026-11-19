<?php

namespace App\Services\Pemantauan;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pemeriksaan kesehatan untuk GET /kesehatan (NF-04): basis data, detak scheduler, detak antrean, dan sisa disk.
 * Detak scheduler ditulis `sigap:detak` setiap menit; detak antrean ditulis job CatatDetakAntrean yang dikirim
 * perintah itu, sehingga detak antrean berhenti bila worker antrean berhenti. Hasilnya hanya nama pemeriksaan dan
 * lolos/gagal, tanpa data laporan.
 */
class PemeriksaKesehatan
{
    public const KUNCI_SCHEDULER = 'sigap:detak:scheduler';

    public const KUNCI_ANTREAN = 'sigap:detak:antrean';

    public static function catatDetak(string $kunci): void
    {
        Cache::forever($kunci, now()->getTimestamp());
    }

    /**
     * @return array<string, bool>
     */
    public function periksa(): array
    {
        $basisData = $this->basisDataTerjangkau();

        return [
            'basis_data' => $basisData,
            'scheduler' => $basisData && $this->detakSegar(self::KUNCI_SCHEDULER, (int) config('sigap.pemantauan.maks_jeda_scheduler_menit')),
            'antrean' => $basisData && $this->detakSegar(self::KUNCI_ANTREAN, (int) config('sigap.pemantauan.maks_jeda_antrean_menit')),
            'disk' => $this->sisaDiskPersen() >= (float) config('sigap.pemantauan.min_sisa_disk_persen'),
        ];
    }

    public function sisaDiskPersen(): float
    {
        $total = @disk_total_space(storage_path());
        $sisa = @disk_free_space(storage_path());

        return $total > 0 && $sisa !== false ? $sisa / $total * 100 : 0.0;
    }

    private function basisDataTerjangkau(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function detakSegar(string $kunci, int $maksMenit): bool
    {
        try {
            $waktu = Cache::get($kunci);
        } catch (Throwable) {
            return false;
        }

        return is_int($waktu) && now()->getTimestamp() - $waktu <= $maksMenit * 60;
    }
}
