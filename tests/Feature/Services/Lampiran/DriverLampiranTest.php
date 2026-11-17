<?php

namespace Tests\Feature\Services\Lampiran;

use App\Enums\JenisBerkas;
use App\Enums\StatusPindai;
use App\Services\Lampiran\PembersihMetadata;
use App\Services\Lampiran\PembersihMetadataAlat;
use App\Services\Lampiran\PembersihMetadataLokal;
use App\Services\Lampiran\PemindaiClamav;
use App\Services\Lampiran\PemindaiLokal;
use App\Services\Lampiran\PemindaiVirus;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\Concerns\MembuatBerkasUji;
use Tests\TestCase;

/**
 * Driver pemindai virus dan pembersih metadata (UC-17): "alat" untuk VPS, "lokal" untuk Laragon.
 */
class DriverLampiranTest extends TestCase
{
    use MembuatBerkasUji;

    public function test_clamav_reports_a_clean_file(): void
    {
        Process::fake(['*' => Process::result(exitCode: 0)]);

        $hasil = (new PemindaiClamav)->pindai($this->pdfUji());

        $this->assertSame(StatusPindai::Bersih, $hasil['status']);
        Process::assertRan(fn (PendingProcess $proses): bool => $proses->command[0] === 'clamscan');
    }

    public function test_clamav_quarantines_an_infected_file_with_the_threat_name(): void
    {
        Process::fake(['*' => Process::result(output: '/tmp/bukti.pdf: Win.Test.EICAR_HDB-1 FOUND', exitCode: 1)]);

        $hasil = (new PemindaiClamav)->pindai($this->pdfUji());

        $this->assertSame(StatusPindai::Karantina, $hasil['status']);
        $this->assertSame('Win.Test.EICAR_HDB-1', $hasil['keterangan']);
    }

    public function test_clamav_error_is_raised_so_the_job_retries(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'database not found', exitCode: 2)]);

        $this->expectException(RuntimeException::class);

        (new PemindaiClamav)->pindai($this->pdfUji());
    }

    public function test_local_scanner_reports_not_scanned_and_logs_a_warning(): void
    {
        Log::spy();

        $hasil = (new PemindaiLokal)->pindai($this->pdfUji());

        $this->assertSame(StatusPindai::TidakDipindai, $hasil['status']);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $pesan): bool => str_contains($pesan, 'TIDAK DIPINDAI'));
    }

    public function test_exiftool_strips_photo_and_pdf_metadata(): void
    {
        Process::fake();
        $pembersih = new PembersihMetadataAlat;

        $this->assertTrue($pembersih->bersihkan($this->jpgUji(), JenisBerkas::Foto, 'image/jpeg'));
        $this->assertTrue($pembersih->bersihkan($this->pdfUji(), JenisBerkas::Dokumen, 'application/pdf'));

        Process::assertRanTimes(fn (PendingProcess $proses): bool => $proses->command[0] === 'exiftool'
            && in_array('-all=', $proses->command, true), 2);
    }

    public function test_ffmpeg_strips_video_metadata_and_replaces_the_file(): void
    {
        $video = $this->mp4Uji();
        Process::fake(function (PendingProcess $proses) {
            file_put_contents(end($proses->command), 'video-tanpa-metadata');

            return Process::result();
        });

        $dibersihkan = (new PembersihMetadataAlat)->bersihkan($video, JenisBerkas::Video, 'video/mp4');

        $this->assertTrue($dibersihkan);
        $this->assertSame('video-tanpa-metadata', file_get_contents($video));
        Process::assertRan(fn (PendingProcess $proses): bool => $proses->command[0] === 'ffmpeg'
            && in_array('-map_metadata', $proses->command, true));
    }

    public function test_office_documents_are_left_unchanged(): void
    {
        Process::fake();

        $dibersihkan = (new PembersihMetadataAlat)->bersihkan($this->docxUji(), JenisBerkas::Dokumen, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->assertFalse($dibersihkan);
        Process::assertNothingRan();
    }

    public function test_local_metadata_cleaner_does_nothing_and_logs_a_warning(): void
    {
        Log::spy();

        $dibersihkan = (new PembersihMetadataLokal)->bersihkan($this->jpgUji(), JenisBerkas::Foto, 'image/jpeg');

        $this->assertFalse($dibersihkan);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $pesan): bool => str_contains($pesan, 'TIDAK DIBERSIHKAN'));
    }

    public function test_driver_is_chosen_from_configuration(): void
    {
        config(['sigap.lampiran.pemindai' => 'alat', 'sigap.lampiran.pembersih_metadata' => 'alat']);
        $this->assertInstanceOf(PemindaiClamav::class, app(PemindaiVirus::class));
        $this->assertInstanceOf(PembersihMetadataAlat::class, app(PembersihMetadata::class));

        config(['sigap.lampiran.pemindai' => 'lokal', 'sigap.lampiran.pembersih_metadata' => 'lokal']);
        $this->assertInstanceOf(PemindaiLokal::class, app(PemindaiVirus::class));
        $this->assertInstanceOf(PembersihMetadataLokal::class, app(PembersihMetadata::class));
    }
}
