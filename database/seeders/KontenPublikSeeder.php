<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use Illuminate\Database\Seeder;

/**
 * Draf awal FAQ dan Kebijakan Privasi (H-08), disiapkan pengembang untuk disetujui Yayasan (TOR, persetujuan draf
 * Hari 13). Teks ini hanya isi awal basis data; setelah itu dikelola Super Admin di Pengaturan > Konten Publik.
 * Aman dijalankan ulang: teks yang sudah ada tidak ditimpa. Target waktu penanganan tidak ditulis di sini karena
 * halaman /faq mengambilnya langsung dari Kategori dan SLA serta tenggat WBS.
 */
class KontenPublikSeeder extends Seeder
{
    /**
     * @var list<array{pertanyaan: string, jawaban: string}>
     */
    public const FAQ = [
        [
            'pertanyaan' => 'Apa bedanya Pengaduan dan Pelaporan Pelanggaran (WBS)?',
            'jawaban' => "Pengaduan dipakai untuk keluhan layanan sekolah, misalnya fasilitas, administrasi, kebersihan, jadwal kegiatan, atau komunikasi guru dan orang tua. Pengaduan ditangani Admin Pengaduan dan unit sekolah terkait.\n\nPelaporan Pelanggaran (WBS) dipakai untuk dugaan pelanggaran, misalnya penyalahgunaan dana atau aset, pungutan tidak sah, suap atau gratifikasi, benturan kepentingan, manipulasi data, atau tindakan balasan terhadap pelapor. WBS ditangani Tim WBS secara rahasia.",
        ],
        [
            'pertanyaan' => 'Siapa yang boleh melapor?',
            'jawaban' => 'Pengaduan dapat dikirim oleh orang tua, siswa, alumni, dan masyarakat. Pelaporan Pelanggaran dapat dikirim oleh siapa saja, termasuk guru dan karyawan.',
        ],
        [
            'pertanyaan' => 'Apakah saya boleh melapor tanpa nama?',
            'jawaban' => "Untuk Pengaduan, nama dan salah satu kontak (surel atau nomor HP) wajib diisi agar petugas dapat menindaklanjuti. Siswa mengisi kontak orang tua atau wali beserta persetujuannya.\n\nUntuk Pelaporan Pelanggaran, Anda boleh melapor tanpa nama. Laporan tetap bisa dipantau dengan kode lacak dan PIN.",
        ],
        [
            'pertanyaan' => 'Bagaimana cara mengetahui perkembangan laporan saya?',
            'jawaban' => "Setelah laporan terkirim, Anda mendapat kode lacak. Buka halaman Lacak Laporan dan masukkan kode tersebut. Untuk Pelaporan Pelanggaran, masukkan juga PIN 6 digit yang Anda buat saat melapor.\n\nDi halaman itu Anda dapat melihat status, berkirim pesan dengan petugas, dan menambah lampiran. Halaman akan keluar otomatis setelah 15 menit tidak aktif.",
        ],
        [
            'pertanyaan' => 'Bagaimana bila kode lacak atau PIN saya hilang?',
            'jawaban' => 'Simpan kode lacak dan PIN dengan baik, misalnya dengan mengunduh Bukti Lapor. PIN disimpan dalam bentuk acak sehingga petugas pun tidak dapat melihat atau memulihkannya. Bila keduanya hilang, kirim laporan baru dan jelaskan bahwa laporan itu kelanjutan dari laporan sebelumnya.',
        ],
        [
            'pertanyaan' => 'Apakah identitas saya aman bila melapor pelanggaran?',
            'jawaban' => "Ya. Halaman Pelaporan Pelanggaran tidak mencatat alamat IP, tidak memasang cookie pelacak, dan tidak memakai analitik pihak ketiga. Identitas pelapor, isi laporan, dan lampiran disimpan dalam bentuk terenkripsi.\n\nHanya penelaah yang berwenang menurut aturan yang dapat membuka identitas pelapor, dan setiap pembukaan tercatat. Laporan tentang anggota Tim WBS, Pengawas, atau Pembina ditangani pihak lain sesuai aturan, bukan oleh orang yang dilaporkan.",
        ],
        [
            'pertanyaan' => 'Bagaimana bila laporan saya menyangkut kekerasan di sekolah?',
            'jawaban' => 'Laporan kekerasan diteruskan kepada Kepala Sekolah paling lambat 1 × 24 jam, atau langsung kepada Pokja BSAN bila Kepala Sekolah terlibat. Formulir juga menampilkan nomor kontak darurat sekolah. Bila ada bahaya yang mendesak, segera hubungi kontak darurat tersebut.',
        ],
        [
            'pertanyaan' => 'Lampiran apa saja yang bisa saya kirim?',
            'jawaban' => 'Anda dapat melampirkan paling banyak 5 berkas dengan total 200 MB, berupa dokumen, foto, video, atau rekaman suara. Jenis dan ukuran berkas yang diterima tertulis di formulir. Data lokasi dan perangkat pada foto dan video dihapus otomatis.',
        ],
        [
            'pertanyaan' => 'Apa yang terjadi setelah pengaduan saya ditindaklanjuti?',
            'jawaban' => 'Anda akan diminta mengonfirmasi hasil penanganan melalui halaman Lacak Laporan. Anda dapat menerima atau menolak hasil, serta memberi penilaian 1 sampai 5. Bila tidak ada tanggapan dalam 7 hari, pengaduan dinyatakan selesai.',
        ],
    ];

    /**
     * @var list<array{judul: string, isi: string}>
     */
    public const PRIVASI = [
        [
            'judul' => 'Tentang kebijakan ini',
            'isi' => 'SIGAP Vidatra adalah layanan Pengaduan dan Pelaporan Pelanggaran (WBS) Yayasan Vidatra untuk SD YPVDP, SMP YPVDP, SMA YPVDP, dan Pelaksana Harian. Kebijakan ini menjelaskan data apa yang kami simpan, untuk apa, berapa lama, dan apa hak Anda, sesuai Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi.',
        ],
        [
            'judul' => 'Data yang kami simpan',
            'isi' => "Pengaduan:\n- nama, surel atau nomor HP, dan jenis pelapor (orang tua, siswa, alumni, atau masyarakat);\n- kontak orang tua atau wali beserta persetujuannya, bila pelapor siswa;\n- isi pengaduan, unit, kategori, dan lampiran.\n\nPelaporan Pelanggaran (WBS):\n- nama dan kontak hanya bila Anda memilih melapor dengan nama;\n- isi laporan, nama dan jabatan pihak yang dilaporkan, serta lampiran;\n- PIN dalam bentuk acak (hash) yang tidak bisa dibaca kembali.",
        ],
        [
            'judul' => 'Data yang tidak kami simpan',
            'isi' => "Halaman Pelaporan Pelanggaran (/wbs) tidak mencatat alamat IP, tidak memasang cookie, dan tidak memuat analitik pihak ketiga. Pencatatan alamat IP untuk halaman ini juga dimatikan di server.\n\nHalaman lain memakai cookie sesi yang diperlukan agar formulir dan halaman Lacak Laporan berfungsi. Kami tidak memakai cookie iklan atau analitik pihak ketiga, dan alamat IP tidak disimpan bersama laporan.",
        ],
        [
            'judul' => 'Cara kami melindungi data',
            'isi' => "- Identitas pelapor, isi laporan pelanggaran, dan lampirannya disimpan terenkripsi.\n- Lampiran disimpan di luar folder publik dengan nama acak dan hanya dibuka petugas lewat tautan sementara.\n- Petugas masuk dengan verifikasi dua langkah, dan hanya melihat laporan sesuai perannya. Petugas unit hanya melihat pengaduan unitnya, dan pengelola sistem tidak dapat membaca isi laporan pelanggaran.\n- Setiap pembukaan laporan dan lampiran tercatat di log yang tidak bisa diubah atau dihapus.",
        ],
        [
            'judul' => 'Siapa yang dapat melihat laporan Anda',
            'isi' => "Pengaduan dibaca Admin Pengaduan dan petugas unit yang menangani. Laporan kekerasan juga diteruskan kepada Kepala Sekolah atau Pokja BSAN.\n\nLaporan pelanggaran dibaca penelaah yang berwenang menurut aturan (penelaah lain bila laporan menyangkut Tim WBS, Pengawas, atau Pembina), serta investigator yang ditunjuk. Pimpinan yang menerima hasil penanganan tidak menerima identitas pelapor.",
        ],
        [
            'judul' => 'Lama penyimpanan',
            'isi' => 'Data laporan disimpan selama 5 tahun setelah laporan ditutup, lalu dimusnahkan dengan berita acara. Cadangan data harian disimpan terenkripsi paling sedikit 30 hari.',
        ],
        [
            'judul' => 'Hak pelapor',
            'isi' => "Anda berhak mengetahui data apa yang kami simpan tentang Anda, meminta perbaikan data yang keliru, dan mengajukan keberatan atas pemrosesan data sesuai UU 27/2022. Sampaikan permintaan melalui pesan di halaman Lacak Laporan.\n\nData siswa pada Pengaduan diproses dengan persetujuan orang tua atau wali. Laporan kekerasan dari siswa tetap diproses demi melindungi kepentingan vital siswa dan memenuhi kewajiban hukum.",
        ],
        [
            'judul' => 'Hak pihak yang dilaporkan',
            'isi' => 'Pihak yang dilaporkan berhak diberi tahu dan diberi kesempatan memberi penjelasan, kecuali pemberitahuan perlu ditunda dengan alasan yang tercatat, misalnya untuk melindungi bukti atau pelapor. Pihak yang dilaporkan juga dapat meminta akses atas data tentang dirinya; permintaan itu dilayani tanpa membuka identitas pelapor.',
        ],
        [
            'judul' => 'Perubahan kebijakan',
            'isi' => 'Kebijakan ini dapat diperbarui bila aturan atau layanan berubah. Versi terbaru selalu tersedia di halaman ini.',
        ],
    ];

    public function run(): void
    {
        Pengaturan::query()->firstOrCreate(['kunci' => Pengaturan::PUBLIK_FAQ], [
            'nilai' => json_encode(self::FAQ, JSON_UNESCAPED_UNICODE),
        ]);

        Pengaturan::query()->firstOrCreate(['kunci' => Pengaturan::PUBLIK_PRIVASI], [
            'nilai' => json_encode(self::PRIVASI, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
