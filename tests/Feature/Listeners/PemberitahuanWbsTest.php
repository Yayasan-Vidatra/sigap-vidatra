<?php

namespace Tests\Feature\Listeners;

use App\Enums\KedudukanTerlapor;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Mail\PemberitahuanWbs;
use App\Models\IdentitasPelapor;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengaduan\PesanLaporan;
use App\Services\Wbs\PembuatLaporanWbs;
use App\Services\Wbs\PenangananWbs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Notifikasi WBS menurut matriks penerima (F-19, F-12): tanpa kode lacak, isi laporan, nama terlapor, atau identitas.
 */
class PemberitahuanWbsTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    private User $anggota;

    private User $dewas;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->ketua = User::factory()->peran(Peran::KetuaTimWbs)->create(['name' => 'Siti Rahmawati']);
        $this->anggota = User::factory()->peran(Peran::AnggotaTimWbs)->create(['name' => 'Budi Santoso']);
        $this->dewas = User::factory()->peran(Peran::DewanPengawas)->create(['name' => 'Hendra Wijaya']);
        $this->superAdmin = User::factory()->peran(Peran::SuperAdmin)->create();
    }

    public function test_new_report_notifies_only_the_reviewer_without_any_content(): void
    {
        $laporan = $this->buat(KedudukanTerlapor::GuruPegawai, 'Bendahara Sekolah');

        Mail::assertQueued(PemberitahuanWbs::class, 1);
        Mail::assertQueued(PemberitahuanWbs::class, function (PemberitahuanWbs $surel) use ($laporan): bool {
            $this->assertTrue($surel->hasTo($this->ketua->email));
            $this->assertSame(PemberitahuanWbs::LAPORAN_BARU, $surel->jenis);
            $surel->assertSeeInHtml("/panel/wbs/{$laporan->id}");

            foreach ([$laporan->kode_lacak, 'Dana BOS dipakai pribadi', 'Bendahara Sekolah', 'Andi Pratama', 'andi@example.com', 'Kecurangan'] as $rahasia) {
                $surel->assertDontSeeInHtml($rahasia);
                $surel->assertDontSeeInText($rahasia);
            }
            $this->assertStringNotContainsString($laporan->kode_lacak, $surel->envelope()->subject);

            return true;
        });
    }

    public function test_report_naming_the_ketua_goes_to_dewan_pengawas_instead(): void
    {
        $this->buat(KedudukanTerlapor::GuruPegawai, 'Siti Rahmawati');

        Mail::assertQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($this->dewas->email));
        Mail::assertNotQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($this->ketua->email));
    }

    public function test_reports_about_pengawas_go_to_pembina_and_never_to_super_admin(): void
    {
        $ketuaPembina = User::factory()->peran(Peran::KetuaPembina)->create();

        $this->buat(KedudukanTerlapor::Pengawas, 'Anggota Dewas X');

        Mail::assertQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($ketuaPembina->email));
        foreach ([$this->ketua, $this->dewas, $this->superAdmin, $this->anggota] as $bukanPenerima) {
            Mail::assertNotQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($bukanPenerima->email));
        }
    }

    public function test_correction_that_moves_access_notifies_the_substitute_reviewer(): void
    {
        $laporan = $this->buat(KedudukanTerlapor::GuruPegawai, 'Bendahara');
        Mail::fake();

        app(PenangananWbs::class)->koreksiKedudukan($laporan, $this->ketua, KedudukanTerlapor::SpiTimWbs, 'Terlapor auditor SPI.');

        Mail::assertQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($this->dewas->email) && $surel->jenis === PemberitahuanWbs::DIALIHKAN);
        Mail::assertNotQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($this->ketua->email));
    }

    public function test_assigned_investigator_is_notified(): void
    {
        $laporan = $this->buat(KedudukanTerlapor::GuruPegawai, 'Bendahara');
        $laporan->update(['status' => StatusLaporan::Ditelaah]);
        Mail::fake();

        app(PenangananWbs::class)->mulaiInvestigasi($laporan, $this->ketua, $this->anggota, 'Periksa buku kas.');

        Mail::assertQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($this->anggota->email) && $surel->jenis === PemberitahuanWbs::DITUGASKAN);
    }

    public function test_reporter_message_notifies_handlers_and_team_replies_never_email_the_reporter(): void
    {
        $laporan = $this->buat(KedudukanTerlapor::GuruPegawai, 'Bendahara');
        $laporan->update(['investigator_user_id' => $this->anggota->id, 'status' => StatusLaporan::Diinvestigasi]);
        Mail::fake();

        app(PesanLaporan::class)->kirimDariPelapor($laporan->fresh(), 'Isi rahasia: kuitansi palsu.');

        foreach ([$this->ketua, $this->anggota] as $penerima) {
            Mail::assertQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($penerima->email) && $surel->jenis === PemberitahuanWbs::PESAN_BARU);
        }
        Mail::assertQueued(PemberitahuanWbs::class, function (PemberitahuanWbs $surel): bool {
            $surel->assertDontSeeInText('kuitansi palsu');

            return true;
        });

        Mail::fake();
        app(PesanLaporan::class)->kirimDariPetugas($laporan->fresh(), $this->ketua, 'Mohon kirim bukti tambahan.', internal: false);

        Mail::assertNothingOutgoing();
    }

    private function buat(KedudukanTerlapor $kedudukan, string $terlapor): Laporan
    {
        $laporan = app(PembuatLaporanWbs::class)->buat([
            'mode' => 'anonim',
            'kategori_id' => Kategori::factory()->wbs()->create(['nama' => 'Kecurangan dana'])->id,
            'unit_id' => Unit::factory()->create()->id,
            'terlapor' => [['nama' => $terlapor, 'jabatan' => 'Bendahara']],
            'kedudukan_terlapor' => $kedudukan->value,
            'judul' => 'Dana BOS dipakai pribadi',
            'kronologi' => 'Dana BOS dipakai untuk keperluan pribadi bendahara sejak Juli.',
        ], SumberLaporan::Laman, '582904');

        IdentitasPelapor::factory()->for($laporan)->create(['nama' => 'Andi Pratama', 'email' => 'andi@example.com']);

        return $laporan->fresh();
    }
}
