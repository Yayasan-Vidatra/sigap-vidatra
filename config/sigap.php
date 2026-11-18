<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenggat WBS (hari kerja)
    |--------------------------------------------------------------------------
    |
    | Rancangan r1 Bagian 2, UC-12, dan UC-13. Tenggat WBS mengikuti tahap
    | penanganan, bukan kategori: telaah sejak laporan diterima, investigasi
    | sejak dinyatakan layak, dan perpanjangan sekali dengan persetujuan
    | penerima hasil. Hari kerja adalah Senin sampai Jumat; libur nasional
    | belum dihitung (lihat prd/log-deviasi.md).
    |
    */

    'wbs' => [
        'telaah_hari_kerja' => 5,
        'investigasi_hari_kerja' => 30,
        'perpanjangan_hari_kerja' => 30,
        // Token formulir pengganti CSRF di jalur /wbs* tanpa sesi; halaman formulir memperbaruinya berkala.
        'masa_token_formulir_menit' => 30,
        // Pembatas laju jalur WBS tanpa IP: penghitung global, ditambah per token formulir untuk unggahan.
        'batas_kirim_global_per_menit' => 20,
        'batas_unggah_global_per_menit' => 60,
        'batas_unggah_per_token_per_menit' => 20,
        'batas_potongan_global_per_menit' => 600,
        'batas_potongan_per_token_per_menit' => 120,
        // Isi laporan yang memicu pesan kontak darurat sekolah di formulir WBS (UC-02 2b).
        'kata_kunci_kekerasan' => [
            'kekerasan', 'perundungan', 'bullying', 'pelecehan', 'pemukulan', 'dipukul', 'memukul',
            'ancaman', 'diancam', 'intimidasi', 'kekerasan seksual', 'cabul',
        ],
        'maks_terlapor' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Login petugas (US-26, Rancangan r1 Bagian 9)
    |--------------------------------------------------------------------------
    |
    | Akun terkunci setelah sekian kali gagal login berturut-turut dan baru
    | terbuka bila dibuka Super Admin. SSO Google Workspace hanya menerima
    | akun dari domain ini, dan hanya untuk Admin Pengaduan serta PIC Unit.
    |
    */

    'login' => [
        'maks_gagal' => 5,
    ],

    'sso' => [
        'domain' => 'vidatra.sch.id',
    ],

    /*
    |--------------------------------------------------------------------------
    | CAPTCHA ALTCHA (proof-of-work, self-hosted)
    |--------------------------------------------------------------------------
    |
    | Tantangan dibuat dan diverifikasi di server ini; tidak ada skrip atau
    | layanan pihak ketiga, sehingga bisa dipakai juga di /wbs. Kunci HMAC
    | wajib diisi lewat ALTCHA_HMAC_KEY. Setiap solusi hanya berlaku sekali
    | dan kedaluwarsa setelah masa_berlaku_detik. cost dan rentang counter
    | menentukan lama hitung di perangkat pelapor: kerja = counter x PBKDF2
    | (cost iterasi), dibagi ke beberapa worker browser. Dengan cost 5000 dan
    | counter 1.000-3.000, PBKDF2 native sekitar 2,7 ms per percobaan, jadi
    | rata-rata 2.000 percobaan selesai sekitar 2-3 detik di laptop 4 inti
    | (lebih lama di HP lama). Sebelumnya counter 5.000-10.000: 5-15 detik.
    |
    */

    'altcha' => [
        'hmac_key' => env('ALTCHA_HMAC_KEY'),
        'masa_berlaku_detik' => 600,
        'cost' => 5000,
        'counter_min' => 1000,
        'counter_maks' => 3000,
        // Penghitung global, bukan per IP, karena endpoint ini juga dipakai formulir WBS (aturan proyek no. 5).
        'batas_tantangan_per_menit' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Lampiran (US-04, UC-03, UC-17, Rancangan r1 Bagian 8)
    |--------------------------------------------------------------------------
    |
    | Format diperiksa dari isi berkas (MIME), bukan ekstensi. Audio hanya
    | untuk WBS. Berkas diunggah bertahap per potongan, disimpan di disk
    | privat "lampiran" dengan nama acak, lalu dipindai dan dibersihkan
    | metadatanya lewat queue.
    |
    | Driver "lokal" untuk Windows/Laragon tanpa ClamAV/ExifTool: tidak
    | memindai dan tidak membersihkan metadata, dan statusnya tercatat
    | "tidak_dipindai" serta di log aplikasi. Driver "alat" untuk VPS.
    |
    */

    'lampiran' => [
        'maks_berkas' => 5,
        'maks_total_mb' => 200,
        'ukuran_potongan_mb' => 5,
        'masa_unggahan_jam' => 24,
        'jenis' => [
            'dokumen' => [
                'maks_mb' => 10,
                'kanal' => ['pengaduan', 'wbs'],
                'mime' => [
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/plain',
                ],
            ],
            'foto' => [
                'maks_mb' => 10,
                'kanal' => ['pengaduan', 'wbs'],
                'mime' => ['image/jpeg', 'image/png', 'image/heic', 'image/heif'],
            ],
            'video' => [
                'maks_mb' => 100,
                'kanal' => ['pengaduan', 'wbs'],
                'mime' => ['video/mp4', 'video/quicktime'],
            ],
            'audio' => [
                'maks_mb' => 20,
                'kanal' => ['wbs'],
                'mime' => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a'],
            ],
        ],
        // Kunci enkripsi isi lampiran WBS: 32 byte, base64. Wajib bila ada lampiran WBS yang disimpan.
        'kunci_enkripsi' => env('LAMPIRAN_KUNCI_ENKRIPSI'),
        'pemindai' => env('LAMPIRAN_PEMINDAI', 'lokal'),
        'pembersih_metadata' => env('LAMPIRAN_PEMBERSIH_METADATA', 'lokal'),
        'alat' => [
            'clamscan' => env('LAMPIRAN_CLAMSCAN', 'clamscan'),
            'exiftool' => env('LAMPIRAN_EXIFTOOL', 'exiftool'),
            'ffmpeg' => env('LAMPIRAN_FFMPEG', 'ffmpeg'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Formulir Pengaduan (US-02, UC-01)
    |--------------------------------------------------------------------------
    |
    | Batas pengiriman per menit per alamat IP (jalur /pengaduan, bukan /wbs).
    | Kata kunci memicu saran pindah ke kanal WBS (UC-01 alur 2a); saran ini
    | hanya anjuran dan tidak menghalangi pengiriman.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Bukti Lapor (H-05, US-07)
    |--------------------------------------------------------------------------
    |
    | PDF bukti lapor dibuat saat diunduh dari halaman Bukti Lapor dan tidak
    | disimpan. Kode lacak diambil dari sesi (bukan URL) dan hanya berlaku
    | sekian menit setelah laporan dikirim. Waktu ditampilkan dalam zona waktu
    | tampilan (sigap.zona_waktu).
    |
    */

    'bukti_lapor' => [
        'masa_unduh_pdf_menit' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Zona waktu tampilan dan hari kerja
    |--------------------------------------------------------------------------
    |
    | Basis data tetap menyimpan waktu dalam UTC (app.timezone). Semua tampilan
    | (panel, PDF, surel) memakai WITA karena Yayasan berada di Bontang, dan
    | batas waktu hari kerja dihitung dari tanggal WITA (App\Services\WaktuLokal).
    |
    */

    'zona_waktu' => [
        'nama' => 'Asia/Makassar',
        'label' => 'WITA',
    ],

    'pengaduan' => [
        'batas_kirim_per_menit' => 5,
        // Pelapor diminta konfirmasi dalam sekian hari kalender (WITA); lewat itu pengaduan Selesai otomatis.
        'hari_konfirmasi' => 7,
        'kata_kunci_wbs' => [
            'korupsi', 'pungli', 'pungutan liar', 'pungutan tidak resmi', 'suap', 'sogok', 'gratifikasi',
            'dana bos', 'penggelapan', 'penyalahgunaan dana', 'manipulasi', 'mark up', 'markup',
            'benturan kepentingan', 'kekerasan', 'perundungan', 'bullying', 'pelecehan', 'pemukulan',
            'pemerasan', 'ancaman', 'balas dendam',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lacak Laporan (H-06, H-07; US-08)
    |--------------------------------------------------------------------------
    |
    | Setelah maks_gagal kode salah dalam masa_gagal_menit, percobaan berikutnya
    | wajib CAPTCHA dan harus menunggu jeda_detik sejak kegagalan terakhir.
    | Izin membuka detail berakhir setelah masa_sesi_menit tidak aktif.
    |
    */

    'lacak' => [
        'maks_gagal' => 5,
        'masa_gagal_menit' => 15,
        'jeda_detik' => 60,
        'masa_sesi_menit' => 15,
        // Kode WBS: penghitung gagal per kode lacak dan penghitung global semua kode WBS, tanpa IP.
        'wbs_maks_gagal_global' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Akun uji lokal (AkunUjiSeeder)
    |--------------------------------------------------------------------------
    |
    | Kata sandi bersama untuk akun uji @sigap.test yang dibuat AkunUjiSeeder.
    | Seeder hanya berjalan di environment local dan berhenti bila nilai ini
    | kosong. Jangan diisi di server.
    |
    */

    'akun_uji' => [
        'password' => env('AKUN_UJI_PASSWORD'),
    ],

];
