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

];
