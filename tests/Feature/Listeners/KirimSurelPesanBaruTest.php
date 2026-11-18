<?php

namespace Tests\Feature\Listeners;

use App\Enums\PengirimPesan;
use App\Enums\Peran;
use App\Events\PesanLaporanTerkirim;
use App\Mail\PesanBaru;
use App\Models\IdentitasPelapor;
use App\Models\Laporan;
use App\Models\Pesan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengaduan\PesanLaporan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class KirimSurelPesanBaruTest extends TestCase
{
    use RefreshDatabase;

    private PesanLaporan $pesan;

    private Unit $unit;

    private Laporan $laporan;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->pesan = app(PesanLaporan::class);
        $this->unit = Unit::factory()->create();
        $this->laporan = Laporan::factory()->for($this->unit)->create(['kode_lacak' => 'PGD-2611-ABC234']);
        $this->admin = User::factory()->peran(Peran::AdminPengaduan)->create();
    }

    public function test_a_reporter_message_notifies_the_foundation_and_school_admins_without_its_content(): void
    {
        $adminSekolah = User::factory()->peran(Peran::AdminPengaduan)->for($this->unit)->create();
        $adminSekolahLain = User::factory()->peran(Peran::AdminPengaduan)->for(Unit::factory())->create();

        $this->pesan->kirimDariPelapor($this->laporan, 'Isi rahasia: guru kelas 7B membentak anak saya.');

        foreach ([$this->admin, $adminSekolah] as $penerima) {
            Mail::assertQueued(PesanBaru::class, fn (PesanBaru $surel): bool => $surel->hasTo($penerima->email) && $surel->untukPetugas);
        }
        Mail::assertNotQueued(PesanBaru::class, fn (PesanBaru $surel): bool => $surel->hasTo($adminSekolahLain->email));
        Mail::assertQueued(PesanBaru::class, function (PesanBaru $surel): bool {
            $surel->assertHasSubject('Ada pesan baru untuk laporan PGD-2611-ABC234');
            $surel->assertSeeInHtml("/panel/pengaduan/{$this->laporan->id}");
            $surel->assertDontSeeInText('Isi rahasia');
            $surel->assertDontSeeInText('membentak');

            return true;
        });
    }

    public function test_an_admin_reply_notifies_the_reporter_with_a_tracking_link_but_not_the_content(): void
    {
        IdentitasPelapor::factory()->for($this->laporan)->create(['email' => 'rina@example.com']);

        $this->pesan->kirimDariPetugas($this->laporan, $this->admin, 'Mohon kirim foto toilet terbaru.', internal: false);

        Mail::assertQueued(PesanBaru::class, function (PesanBaru $surel): bool {
            $surel->assertSeeInHtml(route('lacak'));
            $surel->assertDontSeeInText('foto toilet');
            $surel->assertDontSeeInText($this->admin->name);

            return $surel->hasTo('rina@example.com') && ! $surel->untukPetugas;
        });
    }

    public function test_a_student_reporter_is_notified_through_the_guardian_contact(): void
    {
        IdentitasPelapor::factory()->siswa()->for($this->laporan)->create(['kontak_orang_tua' => 'wali@example.com']);

        $this->pesan->kirimDariPetugas($this->laporan, $this->admin, 'Kami sudah menghubungi wali kelas.', internal: false);

        Mail::assertQueued(PesanBaru::class, fn (PesanBaru $surel): bool => $surel->hasTo('wali@example.com'));
    }

    public function test_internal_notes_system_messages_and_wbs_reports_send_nothing(): void
    {
        IdentitasPelapor::factory()->for($this->laporan)->create(['email' => 'rina@example.com']);

        $this->pesan->kirimDariPetugas($this->laporan, $this->admin, 'Catatan internal.', internal: true);
        event(new PesanLaporanTerkirim(Pesan::factory()->for($this->laporan)->create(['pengirim' => PengirimPesan::Sistem])));
        $wbs = Laporan::factory()->wbs()->create();
        event(new PesanLaporanTerkirim(Pesan::factory()->for($wbs)->create(['pengirim' => PengirimPesan::Pelapor])));

        Mail::assertNothingQueued();
    }

    public function test_at_most_one_email_per_report_per_direction_every_thirty_minutes(): void
    {
        IdentitasPelapor::factory()->for($this->laporan)->create(['email' => 'rina@example.com']);

        $this->pesan->kirimDariPelapor($this->laporan, 'Pesan pertama.');
        $this->pesan->kirimDariPelapor($this->laporan, 'Pesan kedua.');
        $this->pesan->kirimDariPetugas($this->laporan, $this->admin, 'Balasan pertama.', internal: false);
        $this->pesan->kirimDariPetugas($this->laporan, $this->admin, 'Balasan kedua.', internal: false);

        Mail::assertQueuedCount(2);

        $this->travel(29)->minutes();
        $this->pesan->kirimDariPelapor($this->laporan, 'Pesan ketiga.');
        Mail::assertQueuedCount(2);

        $this->travel(2)->minutes();
        $this->pesan->kirimDariPelapor($this->laporan, 'Pesan keempat.');
        Mail::assertQueuedCount(3);
    }
}
