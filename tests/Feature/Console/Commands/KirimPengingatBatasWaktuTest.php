<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Mail\PengingatBatasWaktu;
use App\Models\Disposisi;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class KirimPengingatBatasWaktuTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private User $pic;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->unit = Unit::factory()->create();
        $this->pic = User::factory()->peran(Peran::PicUnit)->for($this->unit)->create(['email' => 'pic@example.com']);
    }

    public function test_pic_is_reminded_once_when_two_working_days_remain(): void
    {
        // Batas waktu Selasa 8 Des 2026 07.46 WITA; dua hari kerja sebelumnya adalah Jumat 4 Des.
        $laporan = $this->pengaduanDiproses('2026-12-07 23:46:00');

        $this->jalankanPada('2026-12-03 07:00');
        Mail::assertNothingQueued();

        $this->jalankanPada('2026-12-04 07:00', 'Pengingat dikirim: 1. Eskalasi dikirim: 0.');
        Mail::assertQueued(PengingatBatasWaktu::class, fn (PengingatBatasWaktu $surel): bool => $surel->hasTo('pic@example.com')
            && ! $surel->eskalasi
            && str_contains((string) $surel->tautan, "/panel/pengaduan-unit/{$laporan->id}"));
        $this->assertDatabaseHas('log_audit', [
            'user_id' => null,
            'aksi' => AksiAudit::MengirimPengingatBatasWaktu->value,
            'objek' => "laporan:{$laporan->id}",
        ]);

        $this->jalankanPada('2026-12-07 07:00', 'Pengingat dikirim: 0. Eskalasi dikirim: 0.');
        Mail::assertQueuedCount(1);
    }

    public function test_overdue_complaint_is_escalated_once_to_pic_admins_and_foundation_leaders(): void
    {
        $laporan = $this->pengaduanDiproses('2026-12-07 23:46:00');
        $adminYayasan = User::factory()->peran(Peran::AdminPengaduan)->create();
        $adminSekolah = User::factory()->peran(Peran::AdminPengaduan)->for($this->unit)->create();
        $adminSekolahLain = User::factory()->peran(Peran::AdminPengaduan)->for(Unit::factory())->create();
        $ketuaUmum = User::factory()->peran(Peran::KetuaUmum)->create();
        $pengurusHarian = User::factory()->peran(Peran::PengurusHarian)->create();
        $ketuaTimWbs = User::factory()->peran(Peran::KetuaTimWbs)->create();

        $this->jalankanPada('2026-12-08 07:45', 'Pengingat dikirim: 1. Eskalasi dikirim: 0.');
        $this->jalankanPada('2026-12-09 07:00', 'Pengingat dikirim: 0. Eskalasi dikirim: 1.');

        foreach ([$this->pic, $adminYayasan, $adminSekolah, $ketuaUmum, $pengurusHarian] as $penerima) {
            Mail::assertQueued(PengingatBatasWaktu::class, fn (PengingatBatasWaktu $surel): bool => $surel->eskalasi && $surel->hasTo($penerima->email));
        }

        foreach ([$adminSekolahLain, $ketuaTimWbs] as $bukanPenerima) {
            Mail::assertNotQueued(PengingatBatasWaktu::class, fn (PengingatBatasWaktu $surel): bool => $surel->hasTo($bukanPenerima->email));
        }

        Mail::assertQueued(PengingatBatasWaktu::class, fn (PengingatBatasWaktu $surel): bool => $surel->hasTo($ketuaUmum->email) && $surel->tautan === null);
        Mail::assertQueued(PengingatBatasWaktu::class, fn (PengingatBatasWaktu $surel): bool => $surel->hasTo($adminYayasan->email)
            && str_contains((string) $surel->tautan, "/panel/pengaduan/{$laporan->id}"));

        $this->jalankanPada('2026-12-10 07:00', 'Pengingat dikirim: 0. Eskalasi dikirim: 0.');
    }

    public function test_escalation_email_shows_the_deadline_in_wita_without_complaint_content(): void
    {
        $laporan = $this->pengaduanDiproses('2026-12-07 23:46:00');
        $laporan->update(['kronologi' => 'Kronologi rahasia tidak boleh ada di surel.']);

        $surel = new PengingatBatasWaktu($laporan->fresh(), eskalasi: true, tautan: null);

        $surel->assertSeeInText('Selasa, 08 Desember 2026');
        $surel->assertSeeInText($this->pic->name);
        $surel->assertDontSeeInText('Kronologi rahasia');
    }

    public function test_only_complaints_still_in_process_are_reminded(): void
    {
        $this->pengaduanDiproses('2026-12-07 23:46:00', StatusLaporan::MenungguKonfirmasi);
        $wbs = Laporan::factory()->wbs()->create(['batas_waktu' => CarbonImmutable::parse('2026-12-07 23:46:00', 'UTC')]);

        $this->jalankanPada('2026-12-09 07:00', 'Pengingat dikirim: 0. Eskalasi dikirim: 0.');

        Mail::assertNothingQueued();
        $this->assertSame(StatusLaporan::Diterima, $wbs->fresh()->status);
    }

    private function pengaduanDiproses(string $batasUtc, StatusLaporan $status = StatusLaporan::Diproses): Laporan
    {
        $laporan = Laporan::factory()->for($this->unit)->berstatus($status)->create([
            'batas_waktu' => CarbonImmutable::parse($batasUtc, 'UTC'),
        ]);
        Disposisi::factory()->for($laporan)->for($this->unit)->create([
            'pic_user_id' => $this->pic->id,
            'dibuat_pada' => CarbonImmutable::parse('2026-11-18 01:00:00', 'UTC'),
        ]);

        return $laporan;
    }

    private function jalankanPada(string $waktuWita, ?string $keluaran = null): void
    {
        $this->travelTo(CarbonImmutable::parse($waktuWita, 'Asia/Makassar')->utc());

        $perintah = $this->artisan('sigap:pengingat-batas-waktu');

        if ($keluaran !== null) {
            $perintah->expectsOutput($keluaran);
        }

        $perintah->assertSuccessful();
    }
}
