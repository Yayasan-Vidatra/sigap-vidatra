<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sigap:bersihkan-unggahan')->hourly();

// Waktu terjadwal di bawah memakai WITA (Bontang); lihat config sigap.zona_waktu.
Schedule::command('sigap:tutup-pengaduan-otomatis')->dailyAt('00:10')->timezone(config('sigap.zona_waktu.nama'));
