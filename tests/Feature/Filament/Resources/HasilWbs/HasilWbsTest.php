<?php

namespace Tests\Feature\Filament\Resources\HasilWbs;

use App\Enums\AksiAudit;
use App\Enums\KedudukanTerlapor;
use App\Enums\KesimpulanWbs;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Filament\Resources\HasilWbs\Pages\ListHasilWbs;
use App\Filament\Resources\HasilWbs\Pages\ViewHasilWbs;
use App\Mail\PemberitahuanWbs;
use App\Models\IdentitasPelapor;
use App\Models\Laporan;
use App\Models\User;
use App\Services\Wbs\PenangananWbs;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * H-16 Hasil WBS untuk Saya dan sisa US-17: perpanjangan dengan persetujuan penerima hasil, pengiriman kesimpulan dan
 * rekomendasi tanpa identitas pelapor, penandaan tindak lanjut.
 */
class HasilWbsTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    private User $dewas;

    private User $ketuaPembina;

    private User $ketuaUmum;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();
        $this->dewas = User::factory()->peran(Peran::DewanPengawas)->denganMfa()->create();
        $this->ketuaPembina = User::factory()->peran(Peran::KetuaPembina)->denganMfa()->create();
        $this->ketuaUmum = User::factory()->peran(Peran::KetuaUmum)->denganMfa()->create();
    }

    public function test_extension_requires_approval_from_the_result_recipient(): void
    {
        $laporan = $this->diinvestigasi(KedudukanTerlapor::GuruPegawai);
        $batasSemula = $laporan->batas_waktu;

        app(PenangananWbs::class)->ajukanPerpanjangan($laporan, $this->ketua, 'Saksi kunci baru bisa ditemui bulan depan.');

        $laporan->refresh();
        $this->assertSame(StatusLaporan::Diinvestigasi, $laporan->status);
        $this->assertNotNull($laporan->perpanjangan_diajukan_pada);
        Mail::assertQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($this->dewas->email) && $surel->jenis === PemberitahuanWbs::PERPANJANGAN);
        Mail::assertNotQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($this->ketuaUmum->email));

        $this->actingAs($this->dewas);
        Livewire::test(ViewHasilWbs::class, ['record' => $laporan->getRouteKey()])
            ->assertSee('Saksi kunci baru bisa ditemui bulan depan.')
            ->callAction('setujuiPerpanjangan')
            ->assertHasNoActionErrors();

        $laporan->refresh();
        $this->assertSame(StatusLaporan::DiinvestigasiDiperpanjang, $laporan->status);
        $this->assertSame($this->dewas->id, $laporan->perpanjangan_disetujui_oleh);
        $this->assertNull($laporan->perpanjangan_diajukan_pada);
        $this->assertTrue($laporan->batas_waktu->greaterThan($batasSemula));
        $this->assertDatabaseHas('log_audit', ['user_id' => $this->dewas->id, 'aksi' => AksiAudit::MenyetujuiPerpanjangan->value]);
        $this->assertDatabaseHas('log_audit', ['user_id' => $this->ketua->id, 'aksi' => AksiAudit::MengajukanPerpanjangan->value]);
    }

    public function test_only_the_matrix_recipient_may_decide_the_extension(): void
    {
        $laporan = $this->diinvestigasi(KedudukanTerlapor::Pengurus);
        app(PenangananWbs::class)->ajukanPerpanjangan($laporan, $this->ketua, 'Butuh audit eksternal.');

        foreach ([$this->dewas, $this->ketuaUmum, $this->ketua] as $bukanPenerima) {
            $this->assertFalse($bukanPenerima->can('putuskanHasilWbs', $laporan->fresh()), $bukanPenerima->peran->value);
        }

        $this->expectException(AuthorizationException::class);
        app(PenangananWbs::class)->setujuiPerpanjangan($laporan->fresh(), $this->dewas);
    }

    public function test_rejected_extension_keeps_the_original_deadline(): void
    {
        $laporan = $this->diinvestigasi(KedudukanTerlapor::GuruPegawai);
        $batasSemula = $laporan->batas_waktu->toDateString();
        app(PenangananWbs::class)->ajukanPerpanjangan($laporan, $this->ketua, 'Alasan.');

        app(PenangananWbs::class)->tolakPerpanjangan($laporan->fresh(), $this->dewas, 'Bukti sudah cukup untuk disimpulkan.');

        $laporan->refresh();
        $this->assertSame(StatusLaporan::Diinvestigasi, $laporan->status);
        $this->assertNull($laporan->perpanjangan_diajukan_pada);
        $this->assertSame($batasSemula, $laporan->batas_waktu->toDateString());
        $this->assertDatabaseHas('log_audit', ['user_id' => $this->dewas->id, 'aksi' => AksiAudit::MenolakPerpanjangan->value]);
    }

    public function test_conclusion_is_sent_to_recipients_with_copy_and_never_shows_reporter_identity(): void
    {
        $laporan = $this->diinvestigasi(KedudukanTerlapor::GuruPegawai);
        IdentitasPelapor::factory()->for($laporan)->create(['nama' => 'Andi Pratama', 'email' => 'andi@example.com']);

        app(PenangananWbs::class)->simpulkan($laporan, $this->ketua, KesimpulanWbs::Terbukti, 'Kembalikan dana dan beri sanksi sesuai aturan.', null);

        foreach ([$this->dewas, $this->ketuaUmum] as $penerima) {
            Mail::assertQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($penerima->email) && $surel->jenis === PemberitahuanWbs::HASIL);
        }
        Mail::assertNotQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($this->ketuaPembina->email));

        foreach ([$this->dewas, $this->ketuaUmum] as $penerima) {
            $halaman = $this->actingAs($penerima)->get("/panel/hasil-wbs/{$laporan->id}");
            $halaman->assertOk()
                ->assertSee('Kembalikan dana dan beri sanksi sesuai aturan.')
                ->assertSee('Bendahara Sekolah')
                ->assertDontSee('Andi Pratama')
                ->assertDontSee('andi@example.com')
                ->assertDontSee($laporan->kode_lacak)
                ->assertDontSee('Kronologi rahasia');
            $this->assertFalse($penerima->can('lihatIdentitasPelapor', $laporan));
            $this->assertDatabaseHas('log_audit', ['user_id' => $penerima->id, 'aksi' => AksiAudit::MembukaHasilWbs->value, 'objek' => "laporan:{$laporan->id}"]);
        }
    }

    public function test_recipient_marks_the_result_as_followed_up_and_copy_holder_cannot(): void
    {
        $laporan = $this->diinvestigasi(KedudukanTerlapor::GuruPegawai);
        app(PenangananWbs::class)->simpulkan($laporan, $this->ketua, KesimpulanWbs::BuktiTidakCukup, 'Pantau ulang.', null);

        $this->actingAs($this->ketuaUmum);
        Livewire::test(ViewHasilWbs::class, ['record' => $laporan->getRouteKey()])->assertActionHidden('tandaiDitindaklanjuti');

        $this->actingAs($this->dewas);
        Livewire::test(ViewHasilWbs::class, ['record' => $laporan->getRouteKey()])
            ->callAction('tandaiDitindaklanjuti', data: ['catatan' => 'Dibahas dalam rapat pengawas 20 November.'])
            ->assertHasNoActionErrors();

        $this->assertNotNull($laporan->fresh()->hasil_ditindaklanjuti_pada);
        $this->assertDatabaseHas('log_audit', ['user_id' => $this->dewas->id, 'aksi' => AksiAudit::MenandaiHasilDitindaklanjuti->value]);
    }

    public function test_results_page_lists_only_reports_addressed_to_the_user_by_the_matrix(): void
    {
        $guru = $this->selesai(KedudukanTerlapor::GuruPegawai);
        $pengurus = $this->selesai(KedudukanTerlapor::Pengurus);
        $masihDitelaah = $this->diinvestigasi(KedudukanTerlapor::GuruPegawai);
        $menyebutDewas = $this->selesai(KedudukanTerlapor::GuruPegawai, $this->dewas->name);

        $this->actingAs($this->dewas);
        Livewire::test(ListHasilWbs::class)
            ->assertCanSeeTableRecords([$guru])
            ->assertCanNotSeeTableRecords([$pengurus, $masihDitelaah, $menyebutDewas]);

        $this->actingAs($this->ketuaPembina);
        Livewire::test(ListHasilWbs::class)->assertCanSeeTableRecords([$pengurus, $menyebutDewas])->assertCanNotSeeTableRecords([$guru]);

        $this->actingAs($this->dewas)->get("/panel/hasil-wbs/{$pengurus->id}")->assertNotFound();
    }

    public function test_super_admin_ketua_and_school_heads_are_refused(): void
    {
        $laporan = $this->selesai(KedudukanTerlapor::GuruPegawai);
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();

        foreach ([$superAdmin, $this->ketua] as $user) {
            $this->actingAs($user)->get('/panel/hasil-wbs')->assertForbidden();
            $this->assertContains($this->actingAs($user)->get("/panel/hasil-wbs/{$laporan->id}")->status(), [403, 404]);
        }

        $spi = $this->diinvestigasi(KedudukanTerlapor::SpiTimWbs);
        $this->assertFalse($this->ketua->can('bukaLaporanWbs', $spi));
        $this->assertFalse($this->ketua->can('bukaHasilWbs', $spi));
    }

    private function diinvestigasi(KedudukanTerlapor $kedudukan, string $terlapor = 'Bendahara Sekolah'): Laporan
    {
        $laporan = Laporan::factory()->wbs()->create([
            'kedudukan_terlapor' => $kedudukan,
            'status' => StatusLaporan::Diinvestigasi,
            'kronologi' => 'Kronologi rahasia tentang dana kegiatan.',
            'batas_waktu' => now()->addDays(10),
        ]);
        $laporan->terlapor()->create(['nama' => $terlapor, 'jabatan' => 'Bendahara']);

        return $laporan->fresh();
    }

    private function selesai(KedudukanTerlapor $kedudukan, string $terlapor = 'Bendahara Sekolah'): Laporan
    {
        $laporan = $this->diinvestigasi($kedudukan, $terlapor);
        $laporan->update(['status' => StatusLaporan::Selesai, 'kesimpulan' => KesimpulanWbs::Terbukti, 'rekomendasi' => 'Rekomendasi uji.']);

        return $laporan->fresh();
    }
}
