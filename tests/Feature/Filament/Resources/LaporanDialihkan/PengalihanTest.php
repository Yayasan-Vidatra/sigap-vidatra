<?php

namespace Tests\Feature\Filament\Resources\LaporanDialihkan;

use App\Enums\AksiAudit;
use App\Enums\KedudukanTerlapor;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Enums\TujuanPengalihan;
use App\Exceptions\AksiPengalihanTidakSah;
use App\Filament\Resources\LaporanDialihkan\Pages\ListLaporanDialihkan;
use App\Filament\Resources\LaporanDialihkan\Pages\ViewLaporanDialihkan;
use App\Filament\Resources\LaporanWbs\Pages\ViewLaporanWbs;
use App\Filament\Resources\Pengaduan\Pages\ViewPengaduan;
use App\Mail\PemberitahuanPengalihan;
use App\Models\IdentitasPelapor;
use App\Models\Laporan;
use App\Models\Pengalihan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengalihan\PengalihanLaporan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pengalihan laporan (UC-19, US-19, US-20) dari H-11 dan H-13, dan H-17 Laporan Dialihkan.
 */
class PengalihanTest extends TestCase
{
    use RefreshDatabase;

    private Unit $sd;

    private User $admin;

    private User $ketua;

    private User $kepsekSd;

    private User $kepsekSmp;

    private User $etik;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->sd = Unit::factory()->create(['nama' => 'SD YPVDP']);
        $this->admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        $this->ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();
        $this->kepsekSd = User::factory()->peran(Peran::KepalaSekolah)->for($this->sd)->denganMfa()->create(['name' => 'Rudi Hartono']);
        $this->kepsekSmp = User::factory()->peran(Peran::KepalaSekolah)->for(Unit::factory())->denganMfa()->create();
        $this->etik = User::factory()->peran(Peran::KomiteEtik)->denganMfa()->create();
    }

    public function test_admin_redirects_a_complaint_to_the_school_head_with_an_encrypted_summary(): void
    {
        $laporan = Laporan::factory()->for($this->sd)->create();
        $this->actingAs($this->admin);

        Livewire::test(ViewPengaduan::class, ['record' => $laporan->id])
            ->callAction('alihkan', data: [
                'tujuan' => TujuanPengalihan::KepalaSekolah->value,
                'unit_tujuan_id' => $this->sd->id,
                'kepala_sekolah_terlibat' => false,
                'ringkasan' => 'Dugaan perundungan di kelas 4B saat jam istirahat.',
            ])
            ->assertHasNoActionErrors();

        $laporan->refresh();
        $this->assertSame(StatusLaporan::DialihkanKeSekolah, $laporan->status);
        $this->assertSame(TujuanPengalihan::KepalaSekolah, $laporan->dialihkan_ke);
        $mentah = DB::table('pengalihan')->value('ringkasan');
        $this->assertSame('Dugaan perundungan di kelas 4B saat jam istirahat.', Crypt::decryptString($mentah));
        $this->assertDatabaseHas('log_audit', ['user_id' => $this->admin->id, 'aksi' => AksiAudit::MengalihkanLaporan->value, 'objek' => "laporan:{$laporan->id}"]);
        Mail::assertQueued(PemberitahuanPengalihan::class, fn (PemberitahuanPengalihan $surel): bool => $surel->hasTo($this->kepsekSd->email));
        Mail::assertNotQueued(PemberitahuanPengalihan::class, fn (PemberitahuanPengalihan $surel): bool => $surel->hasTo($this->kepsekSmp->email));
        Mail::assertQueued(PemberitahuanPengalihan::class, function (PemberitahuanPengalihan $surel) use ($laporan): bool {
            $surel->assertDontSeeInText('perundungan');
            $surel->assertDontSeeInText($laporan->kode_lacak);

            return true;
        });
    }

    public function test_school_head_confirms_and_records_follow_up_without_seeing_reporter_identity(): void
    {
        $laporan = Laporan::factory()->for($this->sd)->create(['kronologi' => 'Kronologi lengkap pengaduan.']);
        IdentitasPelapor::factory()->for($laporan)->create(['nama' => 'Ibu Ratna', 'no_hp' => '0812-9999-0000']);
        $pengalihan = app(PengalihanLaporan::class)->alihkan($laporan, $this->admin, TujuanPengalihan::KepalaSekolah, $this->sd, 'Ringkasan perundungan.', false);

        $this->actingAs($this->kepsekSd)->get("/panel/dialihkan/{$pengalihan->id}")
            ->assertOk()
            ->assertSee('Ringkasan perundungan.')
            ->assertDontSee('Ibu Ratna')
            ->assertDontSee('0812-9999-0000')
            ->assertDontSee('Kronologi lengkap pengaduan.')
            ->assertDontSee($laporan->kode_lacak);
        $this->assertDatabaseHas('log_audit', ['user_id' => $this->kepsekSd->id, 'aksi' => AksiAudit::MembukaPengalihan->value, 'objek' => "pengalihan:{$pengalihan->id}"]);

        $this->actingAs($this->kepsekSd);
        Livewire::test(ViewLaporanDialihkan::class, ['record' => $pengalihan->id])
            ->callAction('konfirmasiTerima')
            ->callAction('catatTindakLanjut', data: ['catatan' => 'Orang tua kedua siswa dipanggil 19 November.'])
            ->assertHasNoActionErrors();

        $pengalihan->refresh();
        $this->assertSame($this->kepsekSd->id, $pengalihan->diterima_oleh);
        $this->assertSame('Orang tua kedua siswa dipanggil 19 November.', Crypt::decryptString(DB::table('pengalihan')->value('catatan_tindak_lanjut')));
        foreach ([AksiAudit::MengonfirmasiPengalihan, AksiAudit::MencatatTindakLanjutPengalihan] as $aksi) {
            $this->assertDatabaseHas('log_audit', ['user_id' => $this->kepsekSd->id, 'aksi' => $aksi->value]);
        }
    }

    public function test_redirect_to_a_school_head_who_is_reported_is_refused(): void
    {
        $pengaduan = Laporan::factory()->for($this->sd)->create();

        try {
            app(PengalihanLaporan::class)->alihkan($pengaduan, $this->admin, TujuanPengalihan::KepalaSekolah, $this->sd, 'Ringkasan.', true);
            $this->fail('Pengalihan ke Kepala Sekolah terlapor seharusnya ditolak.');
        } catch (AksiPengalihanTidakSah) {
            $this->assertSame(0, Pengalihan::query()->count());
        }

        $wbs = Laporan::factory()->wbs()->for($this->sd)->create(['kedudukan_terlapor' => KedudukanTerlapor::GuruPegawai]);
        $wbs->terlapor()->create(['nama' => 'Rudi Hartono', 'jabatan' => 'Kepala Sekolah']);

        $this->expectException(AksiPengalihanTidakSah::class);
        app(PengalihanLaporan::class)->alihkan($wbs->fresh(), $this->ketua, TujuanPengalihan::KepalaSekolah, $this->sd, 'Ringkasan.', false);
    }

    public function test_ketua_refers_to_pokja_bsan_and_ketua_umum_and_dewan_pengawas_are_notified(): void
    {
        $ketuaUmum = User::factory()->peran(Peran::KetuaUmum)->create();
        $dewas = User::factory()->peran(Peran::DewanPengawas)->create();
        $wbs = Laporan::factory()->wbs()->for($this->sd)->create(['kedudukan_terlapor' => KedudukanTerlapor::GuruPegawai]);
        $wbs->terlapor()->create(['nama' => 'Rudi Hartono']);
        $this->actingAs($this->ketua);

        Livewire::test(ViewLaporanWbs::class, ['record' => $wbs->id])
            ->callAction('alihkan', data: ['tujuan' => TujuanPengalihan::PokjaBsan->value, 'kepala_sekolah_terlibat' => true, 'ringkasan' => 'Kekerasan oleh Kepala Sekolah.'])
            ->assertHasNoActionErrors();

        $this->assertSame(StatusLaporan::DirujukKePokjaBsan, $wbs->fresh()->status);
        foreach ([$ketuaUmum, $dewas] as $penerima) {
            Mail::assertQueued(PemberitahuanPengalihan::class, fn (PemberitahuanPengalihan $surel): bool => $surel->hasTo($penerima->email) && $surel->rujukanPokja);
        }
        Mail::assertNotQueued(PemberitahuanPengalihan::class, fn (PemberitahuanPengalihan $surel): bool => $surel->hasTo($this->kepsekSd->email));
    }

    public function test_school_head_never_sees_a_redirect_about_themselves_or_another_unit(): void
    {
        $wbs = Laporan::factory()->wbs()->for($this->sd)->create();
        $wbs->terlapor()->create(['nama' => 'Guru lain']);
        $untukSd = app(PengalihanLaporan::class)->alihkan($wbs->fresh(), $this->ketua, TujuanPengalihan::KepalaSekolah, $this->sd, 'Ringkasan SD.', false);
        $wbs->terlapor()->create(['nama' => 'Rudi Hartono']);
        $keEtik = Pengalihan::factory()->create(['tujuan' => TujuanPengalihan::KomiteEtik]);

        $this->actingAs($this->kepsekSd);
        Livewire::test(ListLaporanDialihkan::class)->assertCanNotSeeTableRecords([$untukSd, $keEtik]);
        $this->assertContains($this->actingAs($this->kepsekSd)->get("/panel/dialihkan/{$untukSd->id}")->status(), [403, 404]);

        $this->actingAs($this->kepsekSmp);
        Livewire::test(ListLaporanDialihkan::class)->assertCanNotSeeTableRecords([$untukSd, $keEtik]);

        $this->actingAs($this->etik);
        Livewire::test(ListLaporanDialihkan::class)->assertCanSeeTableRecords([$keEtik])->assertCanNotSeeTableRecords([$untukSd]);
    }

    public function test_final_statuses_cannot_be_redirected(): void
    {
        foreach ([StatusLaporan::Selesai, StatusLaporan::Ditolak] as $status) {
            $laporan = Laporan::factory()->berstatus($status)->create();

            try {
                app(PengalihanLaporan::class)->alihkan($laporan, $this->admin, TujuanPengalihan::KomiteEtik, null, 'Ringkasan.', false);
                $this->fail("Status {$status->value} seharusnya tidak bisa dialihkan.");
            } catch (AksiPengalihanTidakSah) {
                $this->assertSame($status, $laporan->fresh()->status);
            }
        }
    }

    public function test_super_admin_and_other_roles_are_refused_the_redirect_pages(): void
    {
        $pengalihan = Pengalihan::factory()->create(['tujuan' => TujuanPengalihan::KomiteEtik]);

        foreach ([Peran::SuperAdmin, Peran::KetuaTimWbs, Peran::AdminPengaduan, Peran::DewanPengawas] as $peran) {
            $user = User::factory()->peran($peran)->denganMfa()->create();

            $this->actingAs($user)->get('/panel/dialihkan')->assertForbidden();
            $this->assertContains($this->actingAs($user)->get("/panel/dialihkan/{$pengalihan->id}")->status(), [403, 404]);
        }
    }
}
