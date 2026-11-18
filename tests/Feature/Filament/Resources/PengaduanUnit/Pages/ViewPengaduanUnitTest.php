<?php

namespace Tests\Feature\Filament\Resources\PengaduanUnit\Pages;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Filament\Resources\PengaduanUnit\Pages\ViewPengaduanUnit;
use App\Models\Disposisi;
use App\Models\IdentitasPelapor;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ViewPengaduanUnitTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('lampiran');
        $this->unit = Unit::factory()->create();
        $this->pic = User::factory()->peran(Peran::PicUnit)->for($this->unit)->denganMfa()->create();
    }

    public function test_opening_the_page_is_logged_and_shows_no_reporter_identity(): void
    {
        [$laporan] = $this->pengaduanDiproses();
        IdentitasPelapor::factory()->for($laporan)->create(['nama' => 'Ratna Kusumawati', 'no_hp' => '081299887766']);

        $response = $this->actingAs($this->pic)->get("/panel/pengaduan-unit/{$laporan->id}");

        $response->assertOk();
        $response->assertSee($laporan->kode_lacak);
        $response->assertDontSee('Ratna Kusumawati');
        $response->assertDontSee('081299887766');
        $this->assertDatabaseHas('log_audit', [
            'user_id' => $this->pic->id,
            'aksi' => AksiAudit::MembukaLaporan->value,
            'objek' => "laporan:{$laporan->id}",
        ]);
    }

    public function test_complaint_of_another_unit_or_not_yet_disposed_gives_not_found(): void
    {
        $unitLain = Laporan::factory()->berstatus(StatusLaporan::Diproses)->create();
        Disposisi::factory()->for($unitLain)->create(['unit_id' => $unitLain->unit_id]);
        $belumDisposisi = Laporan::factory()->for($this->unit)->create();

        $this->actingAs($this->pic)->get("/panel/pengaduan-unit/{$unitLain->id}")->assertNotFound();
        $this->actingAs($this->pic)->get("/panel/pengaduan-unit/{$belumDisposisi->id}")->assertNotFound();
        $this->assertDatabaseCount('log_audit', 0);
    }

    public function test_pic_records_follow_up_with_evidence_and_the_page_updates_right_away(): void
    {
        Queue::fake();
        [$laporan, $disposisi] = $this->pengaduanDiproses();

        $this->actingAs($this->pic);

        Livewire::test(ViewPengaduanUnit::class, ['record' => $laporan->id])
            ->assertSeeText('Belum ada bukti penyelesaian.')
            ->callAction('catatTindakLanjut', data: [
                'tindak_lanjut' => 'Keran toilet lantai 2 sudah diganti.',
                'bukti' => [UploadedFile::fake()->image('setelah-perbaikan.jpg')],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Tindak lanjut tersimpan.')
            ->assertSeeText('Keran toilet lantai 2 sudah diganti.')
            ->assertSeeText('setelah-perbaikan.jpg')
            ->assertDontSeeText('Belum ada bukti penyelesaian.');

        $this->assertSame('Keran toilet lantai 2 sudah diganti.', $disposisi->fresh()->tindak_lanjut);
        $this->assertSame(1, $disposisi->bukti()->count());
    }

    public function test_asking_for_confirmation_changes_the_status_on_the_page_right_away(): void
    {
        [$laporan] = $this->pengaduanDiproses(['tindak_lanjut' => 'Sudah diperbaiki.']);

        $this->actingAs($this->pic);

        Livewire::test(ViewPengaduanUnit::class, ['record' => $laporan->id])
            ->assertActionVisible('mintaKonfirmasi')
            ->callAction('mintaKonfirmasi', data: ['ringkasan' => 'Keran toilet sudah diganti baru.'])
            ->assertHasNoActionErrors()
            ->assertNotified('Konfirmasi pelapor diminta.')
            ->assertSeeText('Keran toilet sudah diganti baru.')
            ->assertSeeText('Menunggu Konfirmasi')
            ->assertActionHidden('mintaKonfirmasi')
            ->assertActionHidden('catatTindakLanjut');

        $this->assertSame(StatusLaporan::MenungguKonfirmasi, $laporan->fresh()->status);
    }

    public function test_asking_for_confirmation_without_follow_up_shows_the_reason_and_keeps_the_status(): void
    {
        [$laporan] = $this->pengaduanDiproses();

        $this->actingAs($this->pic);

        Livewire::test(ViewPengaduanUnit::class, ['record' => $laporan->id])
            ->callAction('mintaKonfirmasi', data: ['ringkasan' => 'Sudah diperbaiki.'])
            ->assertNotified('Isi tindak lanjut terlebih dahulu sebelum meminta konfirmasi pelapor.');

        $this->assertSame(StatusLaporan::Diproses, $laporan->fresh()->status);
    }

    public function test_the_summary_for_the_reporter_is_required_in_the_form(): void
    {
        [$laporan] = $this->pengaduanDiproses(['tindak_lanjut' => 'Sudah diperbaiki.']);

        $this->actingAs($this->pic);

        Livewire::test(ViewPengaduanUnit::class, ['record' => $laporan->id])
            ->callAction('mintaKonfirmasi', data: ['ringkasan' => ''])
            ->assertHasActionErrors(['ringkasan' => 'required']);

        $this->assertSame(StatusLaporan::Diproses, $laporan->fresh()->status);
    }

    public function test_admin_only_notes_are_hidden_from_pic(): void
    {
        [$laporan] = $this->pengaduanDiproses();
        $laporan->riwayatStatus()->create([
            'status_baru' => StatusLaporan::Diproses,
            'catatan' => 'Unit diubah dari SD YPVDP ke SMP YPVDP',
            'tampil_ke_pelapor' => false,
        ]);

        $this->actingAs($this->pic);

        Livewire::test(ViewPengaduanUnit::class, ['record' => $laporan->id])
            ->assertDontSeeText('Unit diubah dari SD YPVDP ke SMP YPVDP');
    }

    /**
     * @param  array<string, mixed>  $disposisi
     * @return array{Laporan, Disposisi}
     */
    private function pengaduanDiproses(array $disposisi = []): array
    {
        $laporan = Laporan::factory()->for($this->unit)->berstatus(StatusLaporan::Diproses)->create();

        return [$laporan, Disposisi::factory()->for($laporan)->for($this->unit)->create([
            'pic_user_id' => $this->pic->id,
            'tindak_lanjut' => null,
            ...$disposisi,
        ])];
    }
}
