<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use Illuminate\Database\Seeder;

/**
 * Draf awal FAQ dan Kebijakan Privasi (H-08) serta petunjuk awal kode akses WBS, disiapkan pengembang untuk disetujui
 * Yayasan (TOR, persetujuan draf Hari 13). Teks ini hanya isi awal basis data; setelah itu dikelola di Pengaturan.
 * Aman dijalankan ulang: teks yang sudah ada tidak ditimpa. Sejak revisi 20-11 FAQ dan Kebijakan Privasi tidak
 * menyebut WBS, karena kanal WBS hanya untuk internal. Target waktu penanganan tidak ditulis di sini karena halaman
 * /faq mengambilnya langsung dari Kategori dan SLA.
 */
class KontenPublikSeeder extends Seeder
{
    /**
     * @var list<array{pertanyaan: string, jawaban: string}>
     */
    public const FAQ = [
        [
            'pertanyaan' => 'Apa yang bisa saya sampaikan lewat Pengaduan?',
            'jawaban' => 'Pengaduan dipakai untuk keluhan layanan sekolah, misalnya fasilitas, administrasi, kebersihan, jadwal kegiatan, atau komunikasi guru dan orang tua. Pengaduan ditangani Admin Pengaduan dan unit sekolah terkait.',
        ],
        [
            'pertanyaan' => 'Siapa yang boleh mengirim pengaduan?',
            'jawaban' => 'Pengaduan dapat dikirim oleh orang tua, siswa, alumni, dan masyarakat.',
        ],
        [
            'pertanyaan' => 'Apakah saya boleh mengadu tanpa nama?',
            'jawaban' => 'Nama dan salah satu kontak (surel atau nomor HP) wajib diisi agar petugas dapat menindaklanjuti. Siswa mengisi kontak orang tua atau wali beserta persetujuannya.',
        ],
        [
            'pertanyaan' => 'Bagaimana cara mengetahui perkembangan pengaduan saya?',
            'jawaban' => "Setelah pengaduan terkirim, Anda mendapat kode lacak. Buka halaman Lacak Laporan dan masukkan kode tersebut.\n\nDi halaman itu Anda dapat melihat status, berkirim pesan dengan petugas, dan menambah lampiran. Halaman akan keluar otomatis setelah 15 menit tidak aktif.",
        ],
        [
            'pertanyaan' => 'Bagaimana bila kode lacak saya hilang?',
            'jawaban' => 'Simpan kode lacak dengan baik, misalnya dengan mengunduh Bukti Lapor. Kode lacak juga dikirim ke surel Anda bila diisi. Bila kode hilang, kirim pengaduan baru dan jelaskan bahwa pengaduan itu kelanjutan dari pengaduan sebelumnya.',
        ],
        [
            'pertanyaan' => 'Apakah identitas saya aman?',
            'jawaban' => 'Ya. Identitas pelapor disimpan dalam bentuk terenkripsi dan hanya dibaca petugas yang menangani pengaduan Anda. Setiap pembukaan pengaduan dan lampiran tercatat.',
        ],
        [
            'pertanyaan' => 'Bagaimana bila pengaduan saya menyangkut kekerasan di sekolah?',
            'jawaban' => 'Laporan kekerasan diteruskan kepada Kepala Sekolah paling lambat 1 × 24 jam, atau langsung kepada Pokja BSAN bila Kepala Sekolah terlibat. Formulir juga menampilkan nomor kontak darurat sekolah. Bila ada bahaya yang mendesak, segera hubungi kontak darurat tersebut.',
        ],
        [
            'pertanyaan' => 'Lampiran apa saja yang bisa saya kirim?',
            'jawaban' => "Anda dapat melampirkan paling banyak 5 berkas dengan total 200 MB, berupa dokumen, foto, video, atau berkas ZIP. Jenis dan ukuran berkas yang diterima tertulis di formulir. Data lokasi dan perangkat pada foto dan video dihapus otomatis, tetapi data berkas di dalam ZIP tidak dibersihkan.\n\nBila berkas terlalu besar, Anda dapat menempelkan tautan Google Drive. Berkas di Google Drive tidak dapat dipindai antivirus atau dienkripsi oleh sistem, jadi bagikan aksesnya hanya ke surel petugas dan jangan dihapus sebelum pengaduan selesai.",
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
            'isi' => 'SIGAP Vidatra adalah layanan Pengaduan Yayasan Vidatra untuk SD YPVDP, SMP YPVDP, SMA YPVDP, dan Pelaksana Harian. Kebijakan ini menjelaskan data apa yang kami simpan, untuk apa, berapa lama, dan apa hak Anda, sesuai Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi.',
        ],
        [
            'judul' => 'Data yang kami simpan',
            'isi' => "- nama, surel atau nomor HP, dan jenis pelapor (orang tua, siswa, alumni, atau masyarakat);\n- kontak orang tua atau wali beserta persetujuannya, bila pelapor siswa;\n- isi pengaduan, unit, kategori, lampiran, dan tautan Google Drive yang Anda kirim.",
        ],
        [
            'judul' => 'Data yang tidak kami simpan',
            'isi' => 'Situs ini memakai cookie sesi yang diperlukan agar formulir dan halaman Lacak Laporan berfungsi. Kami tidak memakai cookie iklan atau analitik pihak ketiga, dan alamat IP tidak disimpan bersama laporan.',
        ],
        [
            'judul' => 'Cara kami melindungi data',
            'isi' => "- Identitas pelapor dan tautan Google Drive disimpan terenkripsi.\n- Lampiran disimpan di luar folder publik dengan nama acak dan hanya dibuka petugas lewat tautan sementara.\n- Petugas masuk dengan verifikasi dua langkah, dan hanya melihat pengaduan sesuai perannya. Petugas unit hanya melihat pengaduan unitnya.\n- Setiap pembukaan pengaduan, lampiran, dan tautan tercatat di log yang tidak bisa diubah atau dihapus.",
        ],
        [
            'judul' => 'Siapa yang dapat melihat laporan Anda',
            'isi' => 'Pengaduan dibaca Admin Pengaduan dan petugas unit yang menangani. Laporan kekerasan juga diteruskan kepada Kepala Sekolah atau Pokja BSAN. Pengaduan yang perlu ditangani secara rahasia dialihkan ke tim khusus yayasan, yang akan menghubungi Anda.',
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

    /**
     * Petunjuk awal kode akses WBS (revisi 20-11). Hanya petunjuknya; kode aksesnya sendiri diatur Super Admin atau
     * Ketua Tim WBS di Pengaturan > Kode Akses WBS dan tidak pernah ada di seeder.
     */
    public const PETUNJUK_KODE_AKSES = 'Petunjuk: tahun berdirinya Yayasan Vidatra';

    public function run(): void
    {
        Pengaturan::query()->firstOrCreate(['kunci' => Pengaturan::WBS_PETUNJUK_KODE_AKSES], [
            'nilai' => self::PETUNJUK_KODE_AKSES,
        ]);

        Pengaturan::query()->firstOrCreate(['kunci' => Pengaturan::PUBLIK_FAQ], [
            'nilai' => json_encode(self::FAQ, JSON_UNESCAPED_UNICODE),
        ]);

        Pengaturan::query()->firstOrCreate(['kunci' => Pengaturan::PUBLIK_PRIVASI], [
            'nilai' => json_encode(self::PRIVASI, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
