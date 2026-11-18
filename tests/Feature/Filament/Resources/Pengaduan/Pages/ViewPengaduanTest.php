<?php

namespace Tests\Feature\Filament\Resources\Pengaduan\Pages;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Enums\Prioritas;
use App\Enums\StatusLaporan;
use App\Enums\StatusPindai;
use App\Filament\Resources\Pengaduan\Pages\ViewPengaduan;
use App\Models\Disposisi;
use App\Models\IdentitasPelapor;
use App\Models\Kategori;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViewPengaduanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
    }

    public function test_opening_a_complaint_is_logged_in_the_audit_log(): void
    {
        $laporan = Laporan::factory()->create();

        $response = $this->actingAs($this->admin)->get("/panel/pengaduan/{$laporan->id}");

        $response->assertOk();
        $response->assertSee($laporan->kode_lacak);
        $this->assertDatabaseHas('log_audit', [
            'user_id' => $this->admin->id,
            'aksi' => AksiAudit::MembukaLaporan->value,
            'objek' => "laporan:{$laporan->id}",
        ]);
    }

    public function test_school_admin_gets_not_found_for_another_units_complaint_and_nothing_is_logged(): void
    {
        $adminSekolah = User::factory()->peran(Peran::AdminPengaduan)->for(Unit::factory())->denganMfa()->create();
        $laporan = Laporan::factory()->create();

        $response = $this->actingAs($adminSekolah)->get("/panel/pengaduan/{$laporan->id}");

        $response->assertNotFound();
        $this->assertDatabaseCount('log_audit', 0);
    }

    public function test_a_wbs_report_cannot_be_opened_through_the_complaint_inbox(): void
    {
        $wbs = Laporan::factory()->wbs()->create(['judul' => 'Dana BOS dipakai pribadi']);

        $response = $this->actingAs($this->admin)->get("/panel/pengaduan/{$wbs->id}");

        $response->assertNotFound();
        $response->assertDontSee('Dana BOS dipakai pribadi');
    }

    public function test_pic_unit_cannot_open_a_complaint_of_their_own_unit_through_the_inbox(): void
    {
        $laporan = Laporan::factory()->create();
        $pic = User::factory()->peran(Peran::PicUnit)->denganMfa()->create(['unit_id' => $laporan->unit_id]);

        $response = $this->actingAs($pic)->get("/panel/pengaduan/{$laporan->id}");

        $response->assertNotFound();
        $this->assertDatabaseCount('log_audit', 0);
    }

    public function test_admin_verifies_and_categorises_a_new_complaint(): void
    {
        $laporan = Laporan::factory()->create();
        $kategori = Kategori::factory()->create();

        $this->actingAs($this->admin);

        Livewire::test(ViewPengaduan::class, ['record' => $laporan->id])
            ->assertActionVisible('verifikasi')
            ->assertActionHidden('disposisi')
            ->callAction('verifikasi', data: ['kategori_id' => $kategori->id])
            ->assertHasNoActionErrors()
            ->assertNotified('Pengaduan terverifikasi.')
            ->assertActionHidden('verifikasi')
            ->assertActionVisible('disposisi');

        $this->assertSame(StatusLaporan::Terverifikasi, $laporan->fresh()->status);
        $this->assertSame($kategori->id, $laporan->fresh()->kategori_id);
    }

    public function test_the_whole_detail_page_updates_right_after_each_action_without_reloading(): void
    {
        $kategoriLama = Kategori::factory()->create(['nama' => 'Kategori Pilihan Pelapor']);
        $kategoriBaru = Kategori::factory()->create(['nama' => 'Sarana dan Prasarana']);
        $laporan = Laporan::factory()->for($kategoriLama)->create();
        $unitTujuan = Unit::factory()->create(['nama' => 'SMA YPVDP']);
        $pic = User::factory()->peran(Peran::PicUnit)->for($unitTujuan)->create(['name' => 'Bu Sari PIC']);

        $this->actingAs($this->admin);

        $halaman = Livewire::test(ViewPengaduan::class, ['record' => $laporan->id])
            ->assertSeeText('Diterima')
            ->assertSeeText('Kategori Pilihan Pelapor')
            ->assertSeeText('Belum didisposisikan.');

        $halaman->callAction('verifikasi', data: ['kategori_id' => $kategoriBaru->id])
            ->assertSeeText('Terverifikasi')
            ->assertDontSeeText('Diterima')
            ->assertSeeText('Sarana dan Prasarana')
            ->assertDontSeeText('Kategori Pilihan Pelapor');

        $halaman->callAction('disposisi', data: [
            'unit_id' => $unitTujuan->id,
            'pic_user_id' => $pic->id,
            'prioritas' => Prioritas::Tinggi->value,
        ])
            ->assertSeeText('Diproses')
            ->assertSeeText('Tinggi')
            ->assertSeeText('Bu Sari PIC')
            ->assertSeeText('SMA YPVDP')
            ->assertDontSeeText('Belum didisposisikan.')
            ->assertDontSeeText('Dihitung saat disposisi');
    }

    public function test_status_and_reason_appear_right_after_rejecting(): void
    {
        $laporan = Laporan::factory()->create();

        $this->actingAs($this->admin);

        Livewire::test(ViewPengaduan::class, ['record' => $laporan->id])
            ->callAction('tolak', data: ['alasan' => 'Laporan ganda dengan PGD lain.'])
            ->assertSeeText('Ditolak')
            ->assertSeeText('Laporan ganda dengan PGD lain.')
            ->assertDontSeeText('Diterima');
    }

    public function test_rejecting_without_a_reason_shows_a_validation_error_and_keeps_the_status(): void
    {
        $laporan = Laporan::factory()->create();

        $this->actingAs($this->admin);

        Livewire::test(ViewPengaduan::class, ['record' => $laporan->id])
            ->callAction('tolak', data: ['alasan' => ''])
            ->assertHasActionErrors(['alasan' => 'required']);

        $this->assertSame(StatusLaporan::Diterima, $laporan->fresh()->status);
    }

    public function test_admin_rejects_a_complaint_with_a_reason(): void
    {
        $laporan = Laporan::factory()->create();

        $this->actingAs($this->admin);

        Livewire::test(ViewPengaduan::class, ['record' => $laporan->id])
            ->callAction('tolak', data: ['alasan' => 'Bukan wewenang yayasan.'])
            ->assertHasNoActionErrors()
            ->assertActionHidden('tolak')
            ->assertActionHidden('verifikasi');

        $this->assertSame(StatusLaporan::Ditolak, $laporan->fresh()->status);
        $this->assertSame('Bukan wewenang yayasan.', $laporan->riwayatStatus()->latest('id')->first()->catatan);
    }

    public function test_reject_is_not_offered_once_a_verified_complaint_has_a_disposition(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Terverifikasi)->create();
        Disposisi::factory()->for($laporan)->create(['unit_id' => $laporan->unit_id]);

        $this->actingAs($this->admin);

        Livewire::test(ViewPengaduan::class, ['record' => $laporan->id])
            ->assertActionHidden('tolak');
    }

    public function test_admin_disposes_a_verified_complaint_to_a_unit_pic(): void
    {
        $kategori = Kategori::factory()->create(['sla_hari' => 14]);
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Terverifikasi)->for($kategori)->create([
            'dibuat_pada' => CarbonImmutable::parse('2026-11-20 09:00:00'),
        ]);
        $pic = User::factory()->peran(Peran::PicUnit)->create(['unit_id' => $laporan->unit_id]);

        $this->actingAs($this->admin);

        Livewire::test(ViewPengaduan::class, ['record' => $laporan->id])
            ->callAction('disposisi', data: [
                'unit_id' => $laporan->unit_id,
                'pic_user_id' => $pic->id,
                'prioritas' => Prioritas::Tinggi->value,
                'instruksi' => 'Perbaiki sebelum Senin.',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Pengaduan didisposisikan ke unit.');

        $laporan->refresh();
        $this->assertSame(StatusLaporan::Diproses, $laporan->status);
        $this->assertSame(Prioritas::Tinggi, $laporan->prioritas);
        $this->assertSame('2026-12-10', $laporan->batas_waktu->toDateString());
        $this->assertDatabaseHas('disposisi', ['laporan_id' => $laporan->id, 'pic_user_id' => $pic->id]);
    }

    public function test_disposition_form_rejects_a_pic_who_is_not_in_the_chosen_unit(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Terverifikasi)->create();
        $picUnitLain = User::factory()->peran(Peran::PicUnit)->create();

        $this->actingAs($this->admin);

        Livewire::test(ViewPengaduan::class, ['record' => $laporan->id])
            ->callAction('disposisi', data: [
                'unit_id' => $laporan->unit_id,
                'pic_user_id' => $picUnitLain->id,
                'prioritas' => Prioritas::Sedang->value,
            ])
            ->assertHasActionErrors(['pic_user_id']);

        $this->assertSame(StatusLaporan::Terverifikasi, $laporan->fresh()->status);
        $this->assertDatabaseCount('disposisi', 0);
    }

    public function test_a_stale_page_cannot_verify_a_complaint_that_was_already_rejected(): void
    {
        $laporan = Laporan::factory()->create();

        $this->actingAs($this->admin);

        $halaman = Livewire::test(ViewPengaduan::class, ['record' => $laporan->id]);
        $laporan->forceFill(['status' => StatusLaporan::Ditolak])->save();

        $halaman->callAction('verifikasi', data: ['kategori_id' => $laporan->kategori_id]);

        $this->assertSame(StatusLaporan::Ditolak, $laporan->fresh()->status);
        $this->assertDatabaseMissing('riwayat_status', ['laporan_id' => $laporan->id, 'status_baru' => StatusLaporan::Terverifikasi->value]);
    }

    public function test_reporter_identity_is_shown_on_request_and_the_opening_is_logged(): void
    {
        $laporan = Laporan::factory()->create();
        $identitas = IdentitasPelapor::factory()->for($laporan)->create(['nama' => 'Ratna Kusumawati']);

        $this->actingAs($this->admin);

        $halaman = Livewire::test(ViewPengaduan::class, ['record' => $laporan->id])
            ->assertDontSee('Ratna Kusumawati');

        $this->assertDatabaseMissing('log_audit', ['aksi' => AksiAudit::MembukaIdentitasPelapor->value]);

        $halaman->mountAction('lihatIdentitas')
            ->assertMountedActionModalSee(['Ratna Kusumawati', $identitas->no_hp]);

        $this->assertDatabaseHas('log_audit', [
            'user_id' => $this->admin->id,
            'aksi' => AksiAudit::MembukaIdentitasPelapor->value,
            'objek' => "identitas_pelapor:{$identitas->id}",
        ]);
    }

    public function test_attachments_get_a_temporary_download_link_except_quarantined_ones(): void
    {
        $laporan = Laporan::factory()->create();
        $bersih = Lampiran::factory()->for($laporan)->create(['nama_asli' => 'foto-toilet.jpg', 'status_pindai' => StatusPindai::Bersih]);
        $karantina = Lampiran::factory()->for($laporan)->create(['nama_asli' => 'virus.pdf', 'status_pindai' => StatusPindai::Karantina]);

        $this->actingAs($this->admin);

        Livewire::test(ViewPengaduan::class, ['record' => $laporan->id])
            ->assertSee('foto-toilet.jpg')
            ->assertSee('virus.pdf')
            ->assertSeeHtml("lampiran/{$bersih->id}/unduh?expires=")
            ->assertDontSeeHtml("lampiran/{$karantina->id}/unduh")
            ->assertSee('Dikarantina, tidak bisa diunduh');
    }

    public function test_move_to_wbs_and_redirect_are_prepared_but_not_active_yet(): void
    {
        $laporan = Laporan::factory()->create();

        $this->actingAs($this->admin);

        Livewire::test(ViewPengaduan::class, ['record' => $laporan->id])
            ->assertActionVisible('pindahKeWbs')
            ->assertActionDisabled('pindahKeWbs')
            ->assertActionVisible('alihkan')
            ->assertActionDisabled('alihkan');
    }
}
