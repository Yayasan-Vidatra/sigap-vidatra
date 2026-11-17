<?php

namespace Database\Seeders;

use App\Enums\JenisLaporan;
use App\Models\Kategori;
use Illuminate\Database\Seeder;

/**
 * Kategori contoh dari contoh isi di Rancangan r1 Bagian 2. Kategori final menunggu Yayasan
 * (PRD Bagian 8, pertanyaan terbuka). Aman dijalankan ulang; SLA yang sudah diubah tidak ditimpa.
 *
 * SLA pengaduan 14 hari (target selesai, Bagian 2). SLA WBS 30 hari mengikuti target investigasi;
 * nilai ini contoh dan perlu dikonfirmasi.
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
     * @var array<string, int>
     */
    private const SLA_HARI = [
        'pengaduan' => 14,
        'wbs' => 30,
    ];

    public function run(): void
    {
        foreach (self::KATEGORI as $jenis => $daftarNama) {
            foreach ($daftarNama as $nama) {
                Kategori::firstOrCreate(
                    ['jenis' => JenisLaporan::from($jenis), 'nama' => $nama],
                    ['sla_hari' => self::SLA_HARI[$jenis]],
                );
            }
        }
    }
}
