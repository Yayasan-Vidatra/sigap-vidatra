<?php

namespace Tests\Feature\Listeners;

use App\Enums\JenisPelapor;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Events\StatusLaporanBerubah;
use App\Mail\KonfirmasiPengaduan;
use App\Mail\StatusPengaduanBerubah;
use App\Models\IdentitasPelapor;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengaduan\KonfirmasiPelapor;
use App\Services\Pengaduan\PenangananPengaduan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\MemecahkanAltcha;
use Tests\TestCase;

class KirimSurelStatusKePelaporTest extends TestCase
{
    use MemecahkanAltcha;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_a_new_complaint_queues_the_tracking_code_email_to_the_reporter(): void
    {
        $this->kirimPengaduan(['email' => 'rina@example.com']);

        Mail::assertQueued(KonfirmasiPengaduan::class, fn (KonfirmasiPengaduan $surel): bool => $surel->hasTo('rina@example.com')
            && $surel->laporan->is(Laporan::query()->sole()));
        Mail::assertNotQueued(StatusPengaduanBerubah::class);
    }

    public function test_a_student_complaint_emails_the_guardian_contact(): void
    {
        $this->kirimPengaduan([
            'jenis_pelapor' => JenisPelapor::Siswa->value,
            'email' => 'siswa@example.com',
            'kontak_orang_tua' => 'wali.siswa@example.com',
            'persetujuan_orang_tua' => true,
        ]);

        Mail::assertQueued(KonfirmasiPengaduan::class, fn (KonfirmasiPengaduan $surel): bool => $surel->hasTo('wali.siswa@example.com')
            && ! $surel->hasTo('siswa@example.com'));
    }

    public function test_no_email_when_the_reporter_gave_only_a_phone_number(): void
    {
        $this->kirimPengaduan(['email' => '', 'no_hp' => '081234567890']);

        Mail::assertNothingQueued();
    }

    public function test_status_changes_by_officers_email_the_reporter(): void
    {
        $laporan = $this->pengaduanDenganSurel('rina@example.com');
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create();

        app(PenangananPengaduan::class)->tolak($laporan, $admin, 'Di luar kewenangan yayasan.');

        Mail::assertQueued(StatusPengaduanBerubah::class, fn (StatusPengaduanBerubah $surel): bool => $surel->hasTo('rina@example.com')
            && $surel->riwayat->status_baru === StatusLaporan::Ditolak);
    }

    public function test_the_reporters_own_confirmation_does_not_email_them_again(): void
    {
        $laporan = $this->pengaduanDenganSurel('rina@example.com', StatusLaporan::MenungguKonfirmasi);

        app(KonfirmasiPelapor::class)->konfirmasi($laporan);

        Mail::assertNothingQueued();
    }

    public function test_wbs_reports_never_trigger_reporter_email(): void
    {
        $wbs = Laporan::factory()->wbs()->create();
        IdentitasPelapor::factory()->for($wbs)->create(['email' => 'pelapor.wbs@example.com']);

        event(new StatusLaporanBerubah($wbs, $wbs->riwayatStatus()->create(['status_baru' => StatusLaporan::Ditelaah])));

        Mail::assertNothingQueued();
    }

    private function pengaduanDenganSurel(string $surel, StatusLaporan $status = StatusLaporan::Diterima): Laporan
    {
        $laporan = Laporan::factory()->berstatus($status)->create();
        IdentitasPelapor::factory()->for($laporan)->create(['email' => $surel]);

        return $laporan;
    }

    /**
     * @param  array<string, mixed>  $ubah
     */
    private function kirimPengaduan(array $ubah): void
    {
        $this->post('/pengaduan', [
            'unit_id' => Unit::factory()->create()->id,
            'kategori_id' => Kategori::factory()->create()->id,
            'jenis_pelapor' => JenisPelapor::OrangTua->value,
            'nama' => 'Rina Kartika',
            'email' => 'rina@example.com',
            'no_hp' => '',
            'judul' => 'Toilet lantai 2 rusak',
            'kronologi' => 'Keran di toilet lantai 2 bocor sejak hari Senin.',
            'setuju_ketentuan' => true,
            'altcha' => $this->solusiAltcha(),
            ...$ubah,
        ])->assertSessionHasNoErrors();
    }
}
