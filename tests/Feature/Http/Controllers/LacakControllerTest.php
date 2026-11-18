<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AksiAudit;
use App\Enums\PengirimPesan;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Http\Controllers\LacakController;
use App\Mail\HasilPengaduanDitolakPelapor;
use App\Models\Disposisi;
use App\Models\IdentitasPelapor;
use App\Models\Laporan;
use App\Models\Pesan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\MembuatBerkasUji;
use Tests\Concerns\MemecahkanAltcha;
use Tests\Concerns\MengunggahLampiran;
use Tests\TestCase;

class LacakControllerTest extends TestCase
{
    use MembuatBerkasUji;
    use MemecahkanAltcha;
    use MengunggahLampiran;
    use RefreshDatabase;

    public function test_the_tracking_page_asks_for_the_tracking_code(): void
    {
        $this->get('/lacak')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lacak/Masuk')
                ->where('perluCaptcha', false)
                ->where('sisaJeda', 0));
    }

    public function test_a_valid_tracking_code_opens_the_detail_page_and_is_logged(): void
    {
        $laporan = Laporan::factory()->create(['kode_lacak' => 'PGD-2611-ABC234']);

        $this->post('/lacak', ['kode' => ' pgd-2611-abc234 '])->assertRedirect('/lacak/laporan');

        $this->get('/lacak/laporan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lacak/Detail')
                ->where('kodeLacak', 'PGD-2611-ABC234')
                ->where('status.label', 'Diterima'));
        $this->assertDatabaseHas('log_audit', [
            'user_id' => null,
            'aksi' => AksiAudit::PelaporMembukaLaporan->value,
            'objek' => "laporan:{$laporan->id}",
        ]);
    }

    #[TestWith(['PGD-2611-ZZZZZZ'])]
    #[TestWith(['asal-tebak'])]
    #[TestWith(['WBS-7KQ2-M9PA'])]
    public function test_unknown_wrong_or_wbs_codes_get_the_same_message(string $kode): void
    {
        Laporan::factory()->create(['kode_lacak' => 'PGD-2611-ABC234']);
        Laporan::factory()->wbs()->create(['kode_lacak' => 'WBS-7KQ2-M9PA']);

        $this->from('/lacak')->post('/lacak', ['kode' => $kode, 'pin' => '123456'])
            ->assertRedirect('/lacak')
            ->assertSessionHasErrors(['kode' => LacakController::PESAN_GAGAL]);
    }

    public function test_after_five_failures_captcha_and_a_pause_are_required(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 02:00:00', 'UTC'));
        Laporan::factory()->create(['kode_lacak' => 'PGD-2611-ABC234']);

        for ($i = 1; $i <= 4; $i++) {
            $this->post('/lacak', ['kode' => "PGD-2611-SALAH{$i}"])->assertSessionHasErrors(['kode' => LacakController::PESAN_GAGAL]);
        }

        $this->get('/lacak')->assertInertia(fn (Assert $page) => $page->where('perluCaptcha', false)->where('sisaJeda', 0));

        $this->post('/lacak', ['kode' => 'PGD-2611-SALAH5'])->assertSessionHasErrors(['kode' => LacakController::PESAN_GAGAL]);
        $this->get('/lacak')->assertInertia(fn (Assert $page) => $page->where('perluCaptcha', true)->where('sisaJeda', 60));

        // Selama jeda, kode yang benar pun belum diperiksa.
        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234', 'altcha' => $this->solusiAltcha()])
            ->assertSessionHasErrors(['kode' => 'Terlalu banyak percobaan. Coba lagi dalam 60 detik.']);

        $this->travel(61)->seconds();
        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234'])->assertSessionHasErrors('altcha');

        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234', 'altcha' => $this->solusiAltcha()])
            ->assertRedirect('/lacak/laporan');
        $this->get('/lacak')->assertInertia(fn (Assert $page) => $page->where('perluCaptcha', false));
    }

    public function test_the_detail_page_cannot_be_opened_from_the_url_alone(): void
    {
        Laporan::factory()->create(['kode_lacak' => 'PGD-2611-ABC234']);

        $this->get('/lacak/laporan')
            ->assertRedirect('/lacak')
            ->assertSessionHas('lacak_pesan', LacakController::PESAN_SESI_BERAKHIR);
    }

    public function test_the_report_is_chosen_from_the_session_and_the_code_never_appears_in_a_url(): void
    {
        Laporan::factory()->create(['kode_lacak' => 'PGD-2611-ABC234']);
        Laporan::factory()->create(['kode_lacak' => 'PGD-2611-XYZ789']);

        $masuk = $this->post('/lacak', ['kode' => 'PGD-2611-ABC234']);
        $this->assertStringNotContainsString('PGD-2611-ABC234', (string) $masuk->headers->get('Location'));
        $this->get('/lacak/laporan')->assertInertia(fn (Assert $page) => $page->where('kodeLacak', 'PGD-2611-ABC234'));

        // Masuk dengan kode lain mengganti izin; laporan pertama tidak lagi terbuka.
        $this->post('/lacak', ['kode' => 'PGD-2611-XYZ789'])->assertRedirect('/lacak/laporan');
        $this->get('/lacak/laporan')->assertInertia(fn (Assert $page) => $page->where('kodeLacak', 'PGD-2611-XYZ789'));

        // Alamat lama berisi kode tidak lagi ada.
        $this->get('/lacak/PGD-2611-ABC234')->assertNotFound();
    }

    public function test_the_session_ends_after_fifteen_minutes_without_activity(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 02:00:00', 'UTC'));
        Laporan::factory()->create(['kode_lacak' => 'PGD-2611-ABC234']);
        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234']);

        $this->travel(14)->minutes();
        $this->get('/lacak/laporan')->assertOk();

        // Aktivitas tadi memperpanjang sesi: 14 menit lagi masih boleh.
        $this->travel(14)->minutes();
        $this->get('/lacak/laporan')->assertOk();

        $this->travel(16)->minutes();
        $this->get('/lacak/laporan')->assertRedirect('/lacak');
        $this->travel(1)->minutes();
        $this->get('/lacak/laporan')->assertRedirect('/lacak');
    }

    public function test_only_reporter_visible_notes_messages_and_no_officer_data_are_shown(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::MenungguKonfirmasi)->create(['kode_lacak' => 'PGD-2611-ABC234']);
        IdentitasPelapor::factory()->for($laporan)->create(['nama' => 'Rina Kartika']);
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create(['name' => 'Dewi Admin']);
        $laporan->riwayatStatus()->create(['status_baru' => StatusLaporan::Diproses, 'catatan' => 'Unit diubah dari SD YPVDP ke SMP YPVDP', 'tampil_ke_pelapor' => false, 'oleh_user_id' => $admin->id]);
        $laporan->riwayatStatus()->create(['status_baru' => StatusLaporan::MenungguKonfirmasi, 'catatan' => 'Keran sudah diganti baru.', 'tampil_ke_pelapor' => true]);
        Disposisi::factory()->for($laporan)->create(['unit_id' => $laporan->unit_id, 'tindak_lanjut' => 'Stok keran tinggal 1.']);
        Pesan::factory()->for($laporan)->create(['pengirim' => PengirimPesan::Petugas, 'pengirim_user_id' => $admin->id, 'isi' => 'Mohon kirim foto terbaru.']);
        Pesan::factory()->internal()->for($laporan)->create(['isi' => 'Pelapor ini sering melapor.']);

        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234']);
        $response = $this->get('/lacak/laporan');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('riwayat.0.catatan', null)
            ->where('riwayat.1.catatan', 'Keran sudah diganti baru.')
            ->has('pesan', 1)
            ->where('pesan.0.dari', 'Petugas')
            ->where('pesan.0.isi', 'Mohon kirim foto terbaru.')
            ->where('bolehMenanggapi', true));
        foreach (['Unit diubah dari SD YPVDP', 'Stok keran', 'Pelapor ini sering melapor', 'Dewi Admin', 'Rina Kartika'] as $rahasia) {
            $response->assertDontSee($rahasia);
        }
    }

    public function test_reporter_sends_a_message_with_an_extra_attachment(): void
    {
        Storage::fake('lampiran');
        Storage::fake('local');
        $laporan = Laporan::factory()->create(['kode_lacak' => 'PGD-2611-ABC234']);
        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234']);
        $id = $this->idUnggahanSiap($this->pdfUji(), 'kuitansi.pdf');

        $this->from('/lacak/laporan')
            ->post('/lacak/laporan/pesan', ['isi' => 'Ini kuitansinya.', 'lampiran' => [$id]])
            ->assertRedirect('/lacak/laporan')
            ->assertSessionHasNoErrors();

        $pesan = $laporan->pesan()->sole();
        $this->assertSame(PengirimPesan::Pelapor, $pesan->pengirim);
        $this->assertSame('kuitansi.pdf', $pesan->lampiran()->sole()->nama_asli);
        $this->get('/lacak/laporan')->assertInertia(fn (Assert $page) => $page
            ->where('pesan.0.dari', 'Anda')
            ->where('pesan.0.lampiran', ['kuitansi.pdf']));
    }

    #[TestWith([StatusLaporan::Selesai])]
    #[TestWith([StatusLaporan::Ditolak])]
    public function test_reporter_cannot_send_messages_after_the_report_is_closed(StatusLaporan $status): void
    {
        $laporan = Laporan::factory()->berstatus($status)->create(['kode_lacak' => 'PGD-2611-ABC234']);
        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234']);

        $this->get('/lacak/laporan')->assertInertia(fn (Assert $page) => $page->where('bolehKirimPesan', false));
        $this->post('/lacak/laporan/pesan', ['isi' => 'Masih ada masalah.'])
            ->assertSessionHasErrors(['isi' => 'Laporan sudah ditutup. Bila ada masalah baru, silakan buat pengaduan baru.']);

        $this->assertSame(0, $laporan->pesan()->count());
    }

    public function test_messages_cannot_be_sent_without_permission_or_content(): void
    {
        $laporan = Laporan::factory()->create(['kode_lacak' => 'PGD-2611-ABC234']);

        $this->post('/lacak/laporan/pesan', ['isi' => 'Halo'])->assertRedirect('/lacak');

        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234']);
        $this->post('/lacak/laporan/pesan', ['isi' => ''])->assertSessionHasErrors(['isi' => 'Tulis pesan terlebih dahulu.']);

        $this->assertDatabaseCount('pesan', 0);
        $this->assertSame(0, $laporan->pesan()->count());
    }

    public function test_reporter_confirms_the_result(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::MenungguKonfirmasi)->create(['kode_lacak' => 'PGD-2611-ABC234']);
        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234']);

        $this->post('/lacak/laporan/konfirmasi')->assertSessionHasNoErrors();

        $this->assertSame(StatusLaporan::Selesai, $laporan->fresh()->status);
    }

    public function test_rejecting_the_result_requires_a_reason_and_emails_the_admin(): void
    {
        Mail::fake();
        $laporan = Laporan::factory()->berstatus(StatusLaporan::MenungguKonfirmasi)->create(['kode_lacak' => 'PGD-2611-ABC234']);
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create();
        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234']);

        $this->post('/lacak/laporan/tolak', ['alasan' => ''])->assertSessionHasErrors(['alasan' => 'Tuliskan alasan Anda menolak hasilnya.']);
        $this->assertSame(StatusLaporan::MenungguKonfirmasi, $laporan->fresh()->status);

        $this->post('/lacak/laporan/tolak', ['alasan' => 'Keran masih bocor.'])->assertSessionHasNoErrors();

        $this->assertSame(StatusLaporan::Diproses, $laporan->fresh()->status);
        Mail::assertQueued(HasilPengaduanDitolakPelapor::class, fn (HasilPengaduanDitolakPelapor $surel): bool => $surel->hasTo($admin->email));
    }

    public function test_responding_is_refused_when_not_waiting_for_confirmation(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Diproses)->create(['kode_lacak' => 'PGD-2611-ABC234']);
        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234']);

        $this->post('/lacak/laporan/konfirmasi')->assertSessionHasErrors('tanggapan');

        $this->assertSame(StatusLaporan::Diproses, $laporan->fresh()->status);
    }

    public function test_rating_can_be_given_once_after_the_complaint_is_finished(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Selesai)->create(['kode_lacak' => 'PGD-2611-ABC234']);
        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234']);
        $this->get('/lacak/laporan')->assertInertia(fn (Assert $page) => $page->where('bolehMenilai', true));

        $this->post('/lacak/laporan/penilaian', ['nilai' => 5])->assertSessionHasNoErrors();
        $this->post('/lacak/laporan/penilaian', ['nilai' => 1])->assertSessionHasErrors('nilai');

        $this->assertSame(5, $laporan->fresh()->penilaian);
        $this->get('/lacak/laporan')->assertInertia(fn (Assert $page) => $page->where('bolehMenilai', false)->where('penilaian', 5));
    }

    public function test_rating_outside_one_to_five_is_rejected(): void
    {
        Laporan::factory()->berstatus(StatusLaporan::Selesai)->create(['kode_lacak' => 'PGD-2611-ABC234']);
        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234']);

        $this->post('/lacak/laporan/penilaian', ['nilai' => 6])->assertSessionHasErrors(['nilai' => 'Pilih penilaian 1 sampai 5.']);
    }

    public function test_times_are_shown_in_wita(): void
    {
        Laporan::factory()->create([
            'kode_lacak' => 'PGD-2611-ABC234',
            'dibuat_pada' => CarbonImmutable::parse('2026-11-17 23:46:00', 'UTC'),
        ]);
        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234']);

        $this->get('/lacak/laporan')->assertInertia(fn (Assert $page) => $page
            ->where('dibuatPada', 'Rabu, 18 November 2026, 07.46 WITA'));
    }

    public function test_leaving_removes_the_permission(): void
    {
        Laporan::factory()->create(['kode_lacak' => 'PGD-2611-ABC234']);
        $this->post('/lacak', ['kode' => 'PGD-2611-ABC234']);

        $this->post('/lacak/keluar')->assertRedirect('/lacak');

        $this->get('/lacak/laporan')->assertRedirect('/lacak');
    }
}
