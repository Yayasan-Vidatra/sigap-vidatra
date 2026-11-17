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
    | menentukan lama hitung di perangkat pelapor (beberapa detik).
    |
    */

    'altcha' => [
        'hmac_key' => env('ALTCHA_HMAC_KEY'),
        'masa_berlaku_detik' => 600,
        'cost' => 5000,
        'counter_min' => 5000,
        'counter_maks' => 10000,
        'batas_tantangan_per_menit' => 30,
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

    'pengaduan' => [
        'batas_kirim_per_menit' => 5,
        'kata_kunci_wbs' => [
            'korupsi', 'pungli', 'pungutan liar', 'pungutan tidak resmi', 'suap', 'sogok', 'gratifikasi',
            'dana bos', 'penggelapan', 'penyalahgunaan dana', 'manipulasi', 'mark up', 'markup',
            'benturan kepentingan', 'kekerasan', 'perundungan', 'bullying', 'pelecehan', 'pemukulan',
            'pemerasan', 'ancaman', 'balas dendam',
        ],
    ],

];
