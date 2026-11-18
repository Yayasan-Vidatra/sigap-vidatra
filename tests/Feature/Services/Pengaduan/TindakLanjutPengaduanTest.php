<?php

namespace Tests\Feature\Services\Pengaduan;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Enums\StatusPindai;
use App\Events\StatusLaporanBerubah;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Jobs\ProsesLampiran;
use App\Mail\StatusPengaduanBerubah;
use App\Models\Disposisi;
use App\Models\IdentitasPelapor;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Lampiran\BerkasDitolak;
use App\Services\Pengaduan\TindakLanjutPengaduan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\MembuatBerkasUji;
use Tests\TestCase;

class TindakLanjutPengaduanTest extends TestCase
{
    use MembuatBerkasUji;
    use RefreshDatabase;

    private TindakLanjutPengaduan $tindakLanjut;

    private Unit $unit;

    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('lampiran');
        $this->tindakLanjut = app(TindakLanjutPengaduan::class);
        $this->unit = Unit::factory()->create();
        $this->pic = User::factory()->peran(Peran::PicUnit)->for($this->unit)->create();
    }

    public function test_pic_records_the_follow_up_on_the_disposition_of_their_unit(): void
    {
        [$laporan, $disposisi] = $this->pengaduanDiproses();

        $this->tindakLanjut->simpanTindakLanjut($laporan, $this->pic, '  Keran toilet sudah diganti.  ');

        $this->assertSame('Keran toilet sudah diganti.', $disposisi->fresh()->tindak_lanjut);
        $this->assertDatabaseHas('log_audit', [
            'user_id' => $this->pic->id,
            'aksi' => AksiAudit::MencatatTindakLanjut->value,
            'objek' => "laporan:{$laporan->id}",
        ]);
    }

    public function test_pic_of_another_unit_cannot_follow_up(): void
    {
        [$laporan] = $this->pengaduanDiproses();
        $picLain = User::factory()->peran(Peran::PicUnit)->create();

        $this->expectException(AuthorizationException::class);

        $this->tindakLanjut->simpanTindakLanjut($laporan, $picLain, 'Sudah.');
    }

    #[TestWith([StatusLaporan::MenungguKonfirmasi])]
    #[TestWith([StatusLaporan::Selesai])]
    public function test_follow_up_and_evidence_are_only_accepted_while_in_process(StatusLaporan $status): void
    {
        [$laporan, $disposisi] = $this->pengaduanDiproses(['status' => $status]);

        try {
            $this->tindakLanjut->simpanTindakLanjut($laporan, $this->pic, 'Ubah setelah selesai.');
            $this->fail('Tindak lanjut di luar status Diproses seharusnya ditolak.');
        } catch (AksiPengaduanTidakSah) {
            // Diharapkan.
        }

        $this->expectException(AksiPengaduanTidakSah::class);

        try {
            $this->tindakLanjut->lampirkanBukti($laporan, $this->pic, [['path' => $this->pdfUji(), 'nama_asli' => 'bukti.pdf']]);
        } finally {
            $this->assertNull($disposisi->fresh()->tindak_lanjut);
            $this->assertDatabaseCount('lampiran', 0);
        }
    }

    public function test_evidence_is_stored_privately_under_a_random_name_tied_to_the_disposition_and_queued_for_scanning(): void
    {
        Queue::fake();
        [$laporan, $disposisi] = $this->pengaduanDiproses();

        [$bukti] = $this->tindakLanjut->lampirkanBukti($laporan, $this->pic, [
            ['path' => $this->jpgUji(), 'nama_asli' => 'toilet-setelah-perbaikan.jpg'],
        ]);

        $this->assertSame($disposisi->id, $bukti->disposisi_id);
        $this->assertSame($laporan->id, $bukti->laporan_id);
        $this->assertSame('toilet-setelah-perbaikan.jpg', $bukti->nama_asli);
        $this->assertSame(StatusPindai::Menunggu, $bukti->status_pindai);
        $this->assertStringNotContainsString('toilet', $bukti->path_acak);
        Storage::disk('lampiran')->assertExists($bukti->path_acak);
        $this->assertTrue($laporan->buktiPenyelesaian()->whereKey($bukti->id)->exists());
        $this->assertFalse($laporan->lampiranPelapor()->whereKey($bukti->id)->exists());
        Queue::assertPushed(ProsesLampiran::class, fn (ProsesLampiran $job): bool => $job->lampiran->is($bukti));
        $this->assertDatabaseHas('log_audit', [
            'aksi' => AksiAudit::MengunggahBuktiPenyelesaian->value,
            'objek' => "lampiran:{$bukti->id}",
        ]);
    }

    public function test_evidence_in_a_format_that_is_not_accepted_is_rejected(): void
    {
        [$laporan] = $this->pengaduanDiproses();

        $this->expectException(BerkasDitolak::class);

        try {
            $this->tindakLanjut->lampirkanBukti($laporan, $this->pic, [['path' => $this->mp3Uji(), 'nama_asli' => 'rekaman.mp3']]);
        } finally {
            $this->assertDatabaseCount('lampiran', 0);
        }
    }

    public function test_asking_for_confirmation_requires_a_recorded_follow_up(): void
    {
        [$laporan] = $this->pengaduanDiproses();

        try {
            $this->tindakLanjut->mintaKonfirmasi($laporan, $this->pic, 'Keran sudah diganti.');
            $this->fail('Permintaan konfirmasi tanpa tindak lanjut seharusnya ditolak.');
        } catch (AksiPengaduanTidakSah $galat) {
            $this->assertSame(AksiPengaduanTidakSah::tindakLanjutKosong()->getMessage(), $galat->getMessage());
        }

        $this->assertSame(StatusLaporan::Diproses, $laporan->fresh()->status);
    }

    public function test_asking_for_confirmation_moves_the_complaint_to_waiting_for_confirmation(): void
    {
        Event::fake([StatusLaporanBerubah::class]);
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(2, 0));
        [$laporan, $disposisi] = $this->pengaduanDiproses(disposisi: ['tindak_lanjut' => 'Keran sudah diganti.']);

        $this->tindakLanjut->mintaKonfirmasi($laporan, $this->pic, '  Keran toilet lantai 2 sudah diganti baru.  ');

        $this->assertSame(StatusLaporan::MenungguKonfirmasi, $laporan->fresh()->status);
        $this->assertSame('2026-11-18 02:00', $disposisi->fresh()->selesai_pada->format('Y-m-d H:i'));
        $this->assertDatabaseHas('riwayat_status', [
            'laporan_id' => $laporan->id,
            'status_lama' => StatusLaporan::Diproses->value,
            'status_baru' => StatusLaporan::MenungguKonfirmasi->value,
            'oleh_user_id' => $this->pic->id,
            'tampil_ke_pelapor' => true,
        ]);
        $this->assertSame('Keran toilet lantai 2 sudah diganti baru.', $laporan->riwayatStatus()->latest('id')->first()->catatan);
        $this->assertDatabaseHas('log_audit', [
            'aksi' => AksiAudit::MemintaKonfirmasiPelapor->value,
            'objek' => "laporan:{$laporan->id}",
        ]);
        Event::assertDispatched(StatusLaporanBerubah::class, fn (StatusLaporanBerubah $event): bool => $event->beritahuPelapor
            && $event->riwayat->status_baru === StatusLaporan::MenungguKonfirmasi);
    }

    #[TestWith([''])]
    #[TestWith(["  \n  "])]
    public function test_asking_for_confirmation_requires_a_summary_for_the_reporter(string $ringkasan): void
    {
        [$laporan] = $this->pengaduanDiproses(disposisi: ['tindak_lanjut' => 'Keran sudah diganti.']);

        $this->expectExceptionObject(AksiPengaduanTidakSah::ringkasanPelaporWajib());

        try {
            $this->tindakLanjut->mintaKonfirmasi($laporan, $this->pic, $ringkasan);
        } finally {
            $this->assertSame(StatusLaporan::Diproses, $laporan->fresh()->status);
            $this->assertDatabaseCount('riwayat_status', 0);
        }
    }

    public function test_summary_for_the_reporter_has_a_length_limit(): void
    {
        [$laporan] = $this->pengaduanDiproses(disposisi: ['tindak_lanjut' => 'Keran sudah diganti.']);

        $this->expectExceptionObject(AksiPengaduanTidakSah::ringkasanPelaporTerlaluPanjang(TindakLanjutPengaduan::MAKS_RINGKASAN_PELAPOR));

        $this->tindakLanjut->mintaKonfirmasi($laporan, $this->pic, str_repeat('a', TindakLanjutPengaduan::MAKS_RINGKASAN_PELAPOR + 1));
    }

    public function test_the_waiting_for_confirmation_email_carries_the_summary_but_not_the_internal_follow_up(): void
    {
        Mail::fake();
        [$laporan] = $this->pengaduanDiproses(disposisi: ['tindak_lanjut' => 'Internal: biaya Rp450.000 dari kas unit, teknisi Pak Joko.']);
        IdentitasPelapor::factory()->for($laporan)->create(['email' => 'rina@example.com']);

        $this->tindakLanjut->mintaKonfirmasi($laporan, $this->pic, 'Keran toilet lantai 2 sudah diganti baru.');

        Mail::assertQueued(StatusPengaduanBerubah::class, function (StatusPengaduanBerubah $surel): bool {
            $surel->assertSeeInText('Keran toilet lantai 2 sudah diganti baru.');
            $surel->assertDontSeeInText('Rp450.000');
            $surel->assertDontSeeInText('Pak Joko');

            return $surel->hasTo('rina@example.com') && $surel->riwayat->status_baru === StatusLaporan::MenungguKonfirmasi;
        });
    }

    #[TestWith([StatusLaporan::Terverifikasi])]
    #[TestWith([StatusLaporan::MenungguKonfirmasi])]
    public function test_confirmation_can_only_be_requested_from_in_process(StatusLaporan $status): void
    {
        [$laporan] = $this->pengaduanDiproses(['status' => $status], ['tindak_lanjut' => 'Sudah.']);

        $this->expectException(AksiPengaduanTidakSah::class);

        try {
            $this->tindakLanjut->mintaKonfirmasi($laporan, $this->pic, 'Sudah diperbaiki.');
        } finally {
            $this->assertSame($status, $laporan->fresh()->status);
        }
    }

    /**
     * @param  array<string, mixed>  $laporan
     * @param  array<string, mixed>  $disposisi
     * @return array{Laporan, Disposisi}
     */
    private function pengaduanDiproses(array $laporan = [], array $disposisi = []): array
    {
        $pengaduan = Laporan::factory()->for($this->unit)->berstatus(StatusLaporan::Diproses)->create($laporan);

        return [$pengaduan, Disposisi::factory()->for($pengaduan)->for($this->unit)->create([
            'pic_user_id' => $this->pic->id,
            'tindak_lanjut' => null,
            ...$disposisi,
        ])];
    }
}
