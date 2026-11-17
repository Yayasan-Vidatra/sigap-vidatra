<?php

namespace Database\Seeders;

use App\Enums\JenisLaporan;
use App\Models\Kategori;
use Illuminate\Database\Seeder;

/**
 * Kategori contoh dari contoh isi di Rancangan r1 Bagian 2. Kategori final menunggu Yayasan
 * (PRD Bagian 8, pertanyaan terbuka). Aman dijalankan ulang; SLA yang sudah diubah tidak ditimpa.
 *
 * Kategori pengaduan: tanggapan awal 2 dan selesai 14 hari kerja (Bagian 2), bisa diubah di Pengaturan.
 * Kategori WBS tanpa SLA, karena tenggatnya per tahap di config/sigap.php.
 */
class KategoriSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    public const KATEGORI = [
        'pengaduan' => [
            'Fasilitas dan sarana',
            'Layanan administrasi',
            'Kebersihan',
            'Jadwal kegiatan',
            'Komunikasi guru dan orang tua',
        ],
        'wbs' => [
            'Kecurangan atau penyalahgunaan dana dan aset (termasuk BOS)',
            'Pungutan tidak sah atau di luar pembukuan',
            'Suap atau gratifikasi',
            'Benturan kepentingan pengadaan',
            'Manipulasi data atau laporan',
            'Tindakan balasan terhadap pelapor',
        ],
    ];

    /**
     * @var array<string, array{sla_tanggapan_hari: int|null, sla_hari: int|null}>
     */
    private const SLA = [
        'pengaduan' => ['sla_tanggapan_hari' => 2, 'sla_hari' => 14],
        'wbs' => ['sla_tanggapan_hari' => null, 'sla_hari' => null],
    ];

    public function run(): void
    {
        foreach (self::KATEGORI as $jenis => $daftarNama) {
            foreach ($daftarNama as $nama) {
                Kategori::firstOrCreate(
                    ['jenis' => JenisLaporan::from($jenis), 'nama' => $nama],
                    self::SLA[$jenis],
                );
            }
        }
    }
}
