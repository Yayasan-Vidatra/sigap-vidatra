<?php

namespace Tests\Unit\Enums;

use App\Enums\JenisLaporan;
use App\Enums\StatusLaporan;
use PHPUnit\Framework\TestCase;

class StatusLaporanTest extends TestCase
{
    public function test_pengaduan_uses_the_statuses_of_bagian_6(): void
    {
        $this->assertSame([
            StatusLaporan::Diterima,
            StatusLaporan::Terverifikasi,
            StatusLaporan::Diproses,
            StatusLaporan::MenungguKonfirmasi,
            StatusLaporan::Selesai,
            StatusLaporan::Ditolak,
            StatusLaporan::DipindahKeWbs,
            StatusLaporan::DialihkanKeSekolah,
            StatusLaporan::DirujukKePokjaBsan,
            StatusLaporan::DialihkanKeKomiteEtik,
        ], StatusLaporan::untuk(JenisLaporan::Pengaduan));
    }

    public function test_wbs_uses_the_statuses_of_bagian_6(): void
    {
        $this->assertSame([
            StatusLaporan::Diterima,
            StatusLaporan::Selesai,
            StatusLaporan::Ditelaah,
            StatusLaporan::Diinvestigasi,
            StatusLaporan::DiinvestigasiDiperpanjang,
            StatusLaporan::TidakDitindaklanjuti,
            StatusLaporan::DialihkanKeSekolah,
            StatusLaporan::DirujukKePokjaBsan,
            StatusLaporan::DialihkanKeKomiteEtik,
        ], StatusLaporan::untuk(JenisLaporan::Wbs));
    }

    public function test_labels_match_the_text_seen_by_pelapor(): void
    {
        $this->assertSame('Dialihkan ke sekolah (BSAN)', StatusLaporan::DialihkanKeSekolah->getLabel());
        $this->assertSame('Diinvestigasi (diperpanjang)', StatusLaporan::DiinvestigasiDiperpanjang->getLabel());
    }
}
