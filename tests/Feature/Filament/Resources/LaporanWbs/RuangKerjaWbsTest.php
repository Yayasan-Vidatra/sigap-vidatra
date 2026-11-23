<?php

namespace Tests\Feature\Filament\Resources\LaporanWbs;

use App\Enums\AksiAudit;
use App\Enums\KedudukanTerlapor;
use App\Enums\KesimpulanWbs;
use App\Enums\PengirimPesan;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Enums\StatusPindai;
use App\Filament\Resources\LaporanWbs\LaporanWbsResource;
use App\Filament\Resources\LaporanWbs\Pages\ListLaporanWbs;
use App\Filament\Resources\LaporanWbs\Pages\ViewLaporanWbs;
use App\Models\IdentitasPelapor;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Lampiran\PenyimpananLampiran;
use App\Services\Wbs\PenangananWbs;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * H-13 Ruang Kerja WBS (US-14–US-17): hak akses menurut matriks di daftar, URL langsung, pencarian global, dan unduh
 * lampiran; aksi telaah, investigasi, kesimpulan; identitas pelapor; pesan Tim WBS dan catatan internal.
 */
class RuangKerjaWbsTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    private User $anggota;

    private User $dewas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create(['name' => 'Siti Rahmawati']);
        $this->anggota = User::factory()->peran(Peran::AnggotaTimWbs)->denganMfa()->create(['name' => 'Budi Santoso']);
        $this->dewas = User::factory()->peran(Peran::DewanPengawas)->denganMfa()->create(['name' => 'Hendra Wijaya']);
    }

    public function test_ketua_lists_only_reports_open_to_them_under_the_matrix(): void
    {
        $terbuka = $this->laporan(KedudukanTerlapor::GuruPegawai);
        $tertutup = [
            $this->laporan(KedudukanTerlapor::SpiTimWbs),
            $this->laporan(KedudukanTerlapor::Pengawas),
            $this->laporan(KedudukanTerlapor::Pembina),
            $this->laporan(KedudukanTerlapor::GuruPegawai, 'Siti Rahmawati'),
        ];
        $pengaduan = Laporan::factory()->create();

        $this->actingAs($this->ketua);

        Livewire::test(ListLaporanWbs::class)
            ->assertCanSeeTableRecords([$terbuka])
            ->assertCanNotSeeTableRecords([...$tertutup, $pengaduan])
            ->assertCountTableRecords(1);
    }

    public function test_dewan_pengawas_receives_reports_about_spi_or_naming_the_ketua(): void
    {
        $spi = $this->laporan(KedudukanTerlapor::SpiTimWbs);
        $menyebutKetua = $this->laporan(KedudukanTerlapor::GuruPegawai, 'Siti Rahmawati');
        $biasa = $this->laporan(KedudukanTerlapor::GuruPegawai);

        $this->actingAs($this->dewas);

        Livewire::test(ListLaporanWbs::class)
            ->assertCanSeeTableRecords([$spi, $menyebutKetua])
            ->assertCanNotSeeTableRecords([$biasa]);
    }

    public function test_ketua_cannot_open_a_closed_report_by_direct_url_and_nothing_is_logged(): void
    {
        foreach ([KedudukanTerlapor::SpiTimWbs, KedudukanTerlapor::Pengawas, KedudukanTerlapor::Pembina] as $kedudukan) {
            $laporan = $this->laporan($kedudukan);

            $response = $this->actingAs($this->ketua)->get("/panel/wbs/{$laporan->id}");

            $response->assertNotFound();
            $response->assertDontSee('Dana BOS dipakai pribadi');
        }

        $this->assertDatabaseCount('log_audit', 0);
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranTanpaRuangKerja(): array
    {
        return [
            'super admin' => [Peran::SuperAdmin],
            'kepala sekolah' => [Peran::KepalaSekolah],
            'admin pengaduan' => [Peran::AdminPengaduan],
            'pic unit' => [Peran::PicUnit],
            'komite etik' => [Peran::KomiteEtik],
            'ketua umum' => [Peran::KetuaUmum],
        ];
    }

    #[DataProvider('peranTanpaRuangKerja')]
    public function test_roles_outside_the_matrix_are_refused_list_and_detail(Peran $peran): void
    {
        $user = User::factory()->peran($peran)->denganMfa()->create(['unit_id' => $peran->wajibUnit() ? Unit::factory()->create()->id : null]);
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai);

        $this->actingAs($user)->get('/panel/wbs')->assertForbidden();
        $detail = $this->actingAs($user)->get("/panel/wbs/{$laporan->id}");
        $this->assertContains($detail->status(), [403, 404]);
        $detail->assertDontSee('Dana BOS dipakai pribadi');
        $this->actingAs($user)->get('/panel/wbs/catat')->assertForbidden();
        $this->assertDatabaseCount('log_audit', 0);
    }

    public function test_kepala_sekolah_never_sees_a_wbs_report_about_themselves(): void
    {
        $unit = Unit::factory()->create();
        $kepsek = User::factory()->peran(Peran::KepalaSekolah)->for($unit)->denganMfa()->create(['name' => 'Rudi Hartono']);
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai, 'Rudi Hartono');
        $laporan->update(['unit_id' => $unit->id]);

        $this->actingAs($kepsek)->get('/panel/wbs')->assertForbidden();
        $this->assertContains($this->actingAs($kepsek)->get("/panel/wbs/{$laporan->id}")->status(), [403, 404]);
        $this->assertContains($this->actingAs($kepsek)->get("/panel/pengaduan/{$laporan->id}")->status(), [403, 404]);
        $this->assertSame(0, Laporan::query()->wbsTerbukaBagi($kepsek)->count());
    }

    public function test_wbs_reports_never_appear_in_global_search(): void
    {
        $tertutup = $this->laporan(KedudukanTerlapor::SpiTimWbs);
        $terbuka = $this->laporan(KedudukanTerlapor::GuruPegawai);
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();

        $this->assertFalse(LaporanWbsResource::canGloballySearch());

        foreach ([$this->ketua, $superAdmin, $admin] as $user) {
            $this->actingAs($user);
            Filament::setCurrentPanel('panel');

            foreach ([$tertutup->kode_lacak, $terbuka->kode_lacak, 'WBS-'] as $kata) {
                $hasil = Filament::getGlobalSearchProvider()->getResults($kata);
                $this->assertSame([], $hasil?->getCategories()->flatten()->all() ?? [], "{$user->peran->value} mencari {$kata}");
            }
        }
    }

    public function test_attachment_downloads_follow_the_matrix_and_are_logged(): void
    {
        Storage::fake('lampiran');
        $tertutup = $this->lampiranUntuk($this->laporan(KedudukanTerlapor::SpiTimWbs));
        $terbuka = $this->lampiranUntuk($this->laporan(KedudukanTerlapor::GuruPegawai));
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();

        $this->actingAs($this->ketua)->get($tertutup->tautanUnduhSementara())->assertForbidden();
        $this->actingAs($superAdmin)->get($terbuka->tautanUnduhSementara())->assertForbidden();

        $unduhan = $this->actingAs($this->ketua)->get($terbuka->tautanUnduhSementara());
        $unduhan->assertOk();
        $this->assertSame('isi bukti asli', $unduhan->streamedContent());
        $this->assertDatabaseHas('log_audit', ['user_id' => $this->ketua->id, 'aksi' => AksiAudit::MembukaLampiran->value, 'objek' => "lampiran:{$terbuka->id}"]);
        $this->assertDatabaseMissing('log_audit', ['objek' => "lampiran:{$tertutup->id}"]);
    }

    public function test_opening_a_report_shows_decrypted_content_and_is_logged(): void
    {
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai);

        $response = $this->actingAs($this->ketua)->get("/panel/wbs/{$laporan->id}");

        $response->assertOk();
        $response->assertSee('Dana BOS dipakai pribadi');
        $response->assertSee('Bendahara Sekolah');
        $this->assertDatabaseHas('log_audit', ['user_id' => $this->ketua->id, 'aksi' => AksiAudit::MembukaLaporan->value, 'objek' => "laporan:{$laporan->id}"]);
    }

    public function test_review_investigation_and_conclusion_flow(): void
    {
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai);
        $this->actingAs($this->ketua);

        Livewire::test(ViewLaporanWbs::class, ['record' => $laporan->getRouteKey()])
            ->assertActionHidden('simpulkan')
            ->callAction('mulaiTelaah')
            ->assertHasNoActionErrors()
            ->callAction('mulaiInvestigasi', data: ['investigator_user_id' => $this->anggota->id, 'rencana' => 'Bukti awal cukup; periksa buku kas.'])
            ->assertHasNoActionErrors();

        $laporan->refresh();
        $this->assertSame(StatusLaporan::Diinvestigasi, $laporan->status);
        $this->assertTrue($laporan->investigator->is($this->anggota));
        $this->assertNotNull($laporan->batas_waktu);

        $this->actingAs($this->anggota);
        Livewire::test(ViewLaporanWbs::class, ['record' => $laporan->getRouteKey()])
            ->assertActionHidden('mulaiInvestigasi')
            ->callAction('simpulkan', data: [
                'kesimpulan' => KesimpulanWbs::Terbukti->value,
                'rekomendasi' => 'Pengembalian dana dan pemeriksaan ulang pembukuan.',
                'catatan_pelapor' => 'Terima kasih, laporan telah selesai ditangani.',
            ])
            ->assertHasNoActionErrors();

        $laporan->refresh();
        $this->assertSame(StatusLaporan::Selesai, $laporan->status);
        $this->assertSame(KesimpulanWbs::Terbukti, $laporan->kesimpulan);
        $mentah = DB::table('laporan')->where('id', $laporan->id)->value('rekomendasi');
        $this->assertSame('Pengembalian dana dan pemeriksaan ulang pembukuan.', Crypt::decryptString($mentah));
        $this->assertDatabaseHas('log_audit', ['aksi' => AksiAudit::MenunjukInvestigator->value, 'objek' => "laporan:{$laporan->id}"]);
        $this->assertDatabaseHas('log_audit', ['aksi' => AksiAudit::MenetapkanKesimpulanWbs->value, 'user_id' => $this->anggota->id]);
    }

    public function test_not_worth_investigating_requires_a_general_reason_visible_to_the_reporter(): void
    {
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai);
        $this->actingAs($this->ketua);

        Livewire::test(ViewLaporanWbs::class, ['record' => $laporan->getRouteKey()])
            ->callAction('tidakDitindaklanjuti', data: ['alasan' => ''])
            ->assertHasActionErrors(['alasan']);

        Livewire::test(ViewLaporanWbs::class, ['record' => $laporan->getRouteKey()])
            ->callAction('tidakDitindaklanjuti', data: ['alasan' => 'Informasi belum cukup untuk ditelusuri.'])
            ->assertHasNoActionErrors();

        $laporan->refresh();
        $this->assertSame(StatusLaporan::TidakDitindaklanjuti, $laporan->status);
        $riwayat = $laporan->riwayatStatus()->latest('id')->first();
        $this->assertTrue($riwayat->tampil_ke_pelapor);
        $this->assertSame('Informasi belum cukup untuk ditelusuri.', $riwayat->catatan);
    }

    public function test_correcting_the_position_moves_access_to_the_substitute_reviewer(): void
    {
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai);
        $this->actingAs($this->ketua);

        Livewire::test(ViewLaporanWbs::class, ['record' => $laporan->getRouteKey()])
            ->callAction('koreksiKedudukan', data: ['kedudukan' => KedudukanTerlapor::SpiTimWbs->value, 'alasan' => 'Terlapor ternyata auditor SPI.'])
            ->assertRedirect(LaporanWbsResource::getUrl('index'));

        $laporan->refresh();
        $this->assertSame(KedudukanTerlapor::SpiTimWbs, $laporan->kedudukan_terlapor);
        $this->assertFalse($this->ketua->can('bukaLaporanWbs', $laporan));
        $this->assertTrue($this->dewas->can('bukaLaporanWbs', $laporan));
        $this->assertDatabaseHas('log_audit', ['aksi' => AksiAudit::MengoreksiKedudukanTerlapor->value, 'user_id' => $this->ketua->id]);
        $koreksi = $laporan->riwayatStatus()->latest('id')->first();
        $this->assertSame($koreksi->status_lama, $koreksi->status_baru);
        $this->assertFalse($koreksi->tampil_ke_pelapor);
    }

    public function test_members_cannot_review_and_the_server_refuses_even_without_the_button(): void
    {
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai);
        $this->actingAs($this->anggota);

        Livewire::test(ViewLaporanWbs::class, ['record' => $laporan->getRouteKey()])
            ->assertActionHidden('mulaiTelaah')
            ->assertActionHidden('koreksiKedudukan')
            ->assertActionHidden('lihatIdentitas');

        $this->expectException(AuthorizationException::class);
        app(PenangananWbs::class)->mulaiTelaah($laporan, $this->anggota);
    }

    public function test_only_the_reviewer_opens_the_identity_and_each_opening_is_logged(): void
    {
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai);
        IdentitasPelapor::factory()->for($laporan)->create(['nama' => 'Andi Pratama']);
        $this->actingAs($this->ketua);

        Livewire::test(ViewLaporanWbs::class, ['record' => $laporan->getRouteKey()])
            ->mountAction('lihatIdentitas')
            ->assertMountedActionModalSee('Andi Pratama');

        $this->assertDatabaseHas('log_audit', ['user_id' => $this->ketua->id, 'aksi' => AksiAudit::MembukaIdentitasPelapor->value]);
        $this->assertFalse($this->anggota->can('lihatIdentitasPelapor', $laporan));
    }

    public function test_team_reply_shows_as_tim_wbs_on_lacak_and_internal_notes_never_do(): void
    {
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai);
        $laporan->update(['pin_hash' => '582904']);
        $this->actingAs($this->ketua);

        Livewire::test(ViewLaporanWbs::class, ['record' => $laporan->getRouteKey()])
            ->callAction('kirimPesan', data: ['isi' => 'Mohon kirim foto kuitansinya.', 'internal' => false])
            ->callAction('kirimPesan', data: ['isi' => 'Catatan wawancara: rahasia tim.', 'internal' => true]);

        $this->assertSame(2, $laporan->pesan()->where('pengirim', PengirimPesan::Petugas->value)->count());

        auth()->logout();
        $this->postJson('/wbs/lacak', ['kode' => $laporan->kode_lacak, 'pin' => '582904'])
            ->assertOk()
            ->assertJsonCount(1, 'pesan')
            ->assertJsonPath('pesan.0.dari', 'Tim WBS')
            ->assertJsonPath('pesan.0.isi', 'Mohon kirim foto kuitansinya.')
            ->assertDontSee('rahasia tim')
            ->assertDontSee($this->ketua->name);
    }

    private function laporan(KedudukanTerlapor $kedudukan, string $namaTerlapor = 'Bendahara Sekolah'): Laporan
    {
        $laporan = Laporan::factory()->wbs()->create(['kedudukan_terlapor' => $kedudukan, 'judul' => 'Dana BOS dipakai pribadi']);
        $laporan->terlapor()->create(['nama' => $namaTerlapor, 'jabatan' => 'Bendahara']);

        return $laporan->fresh();
    }

    private function lampiranUntuk(Laporan $laporan): Lampiran
    {
        $sumber = (string) tempnam(sys_get_temp_dir(), 'uji');
        file_put_contents($sumber, 'isi bukti asli');

        return Lampiran::factory()->for($laporan)->create([
            'path_acak' => app(PenyimpananLampiran::class)->simpan($sumber, enkripsi: true),
            'mime' => 'text/plain',
            'status_pindai' => StatusPindai::Bersih,
        ]);
    }
}
