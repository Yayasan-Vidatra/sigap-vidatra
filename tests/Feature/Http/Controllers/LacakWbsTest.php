<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AksiAudit;
use App\Enums\PengirimPesan;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Http\Controllers\LacakController;
use App\Models\Laporan;
use App\Models\Pesan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use ReflectionProperty;
use Tests\Concerns\MembuatBerkasUji;
use Tests\Concerns\MemecahkanAltcha;
use Tests\Concerns\MengunggahLampiran;
use Tests\TestCase;

/**
 * Lacak Laporan (H-06) dan Detail Laporan (H-07) untuk kode WBS + PIN (US-08, UC-04): pembatasan percobaan per kode
 * dan global tanpa IP, sesi tanpa IP dan user agent.
 */
class LacakWbsTest extends TestCase
{
    use MembuatBerkasUji;
    use MemecahkanAltcha;
    use MengunggahLampiran;
    use RefreshDatabase;

    private const IP = '203.0.113.77';

    private const AGEN = 'AgenUjiWbs/9.9';

    public function test_wbs_code_and_pin_open_the_detail_page(): void
    {
        $laporan = $this->laporanWbs();

        $this->post('/lacak', ['kode' => ' wbs-7kq2-m9pa ', 'pin' => '582904'])->assertRedirect('/lacak/laporan');

        $this->get('/lacak/laporan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lacak/Detail')
                ->where('wbs', true)
                ->where('kodeLacak', 'WBS-7KQ2-M9PA')
                ->where('judul', 'Dana kegiatan dipotong')
                ->where('status.label', 'Diterima')
                ->where('status.penjelasan', 'Laporan Anda sudah masuk dan menunggu ditelaah.')
                ->where('bolehMenanggapi', false)
                ->where('bolehMenilai', false)
                ->where('aturanLampiran.jenis', fn ($jenis): bool => collect($jenis)->pluck('label')->contains('Audio'))
                ->whereNot('tokenUnggahWbs', null));
        $this->assertDatabaseHas('log_audit', [
            'aksi' => AksiAudit::PelaporMembukaLaporan->value,
            'objek' => "laporan:{$laporan->id}",
        ]);
    }

    public function test_wrong_missing_pin_and_unknown_code_get_the_same_message(): void
    {
        $this->laporanWbs();

        foreach ([
            ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '000001'],
            ['kode' => 'WBS-7KQ2-M9PA'],
            ['kode' => 'WBS-ZZZZ-ZZZZ', 'pin' => '582904'],
        ] as $isian) {
            $this->from('/lacak')->post('/lacak', $isian)
                ->assertRedirect('/lacak')
                ->assertSessionHasErrors(['kode' => LacakController::PESAN_GAGAL]);
        }
    }

    public function test_pengaduan_code_with_a_wbs_report_pin_does_not_open_a_wbs_report(): void
    {
        $this->laporanWbs();

        $this->post('/lacak', ['kode' => 'PGD-2611-7KQ2M9', 'pin' => '582904'])
            ->assertSessionHasErrors(['kode' => LacakController::PESAN_GAGAL]);
    }

    public function test_five_failures_on_a_code_require_captcha_and_a_pause_from_any_ip(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 02:00:00', 'UTC'));
        $this->laporanWbs();

        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$i}"])
                ->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '00000'.$i])
                ->assertSessionHasErrors(['kode' => LacakController::PESAN_GAGAL]);
        }

        $this->get('/lacak')->assertInertia(fn (Assert $page) => $page->where('perluCaptcha', true)->where('sisaJeda', 60));

        // Berganti IP tidak membuka kunci: penghitung ada pada kode, bukan pada alamat.
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.200'])
            ->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904', 'altcha' => $this->solusiAltcha()])
            ->assertSessionHasErrors(['kode' => 'Terlalu banyak percobaan. Coba lagi dalam 60 detik.']);

        $this->travel(61)->seconds();
        $this->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904'])->assertSessionHasErrors('altcha');
        $this->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904', 'altcha' => $this->solusiAltcha()])
            ->assertRedirect('/lacak/laporan');
    }

    public function test_per_code_counter_does_not_block_other_codes(): void
    {
        $this->laporanWbs();
        Laporan::factory()->wbs()->create(['kode_lacak' => 'WBS-AAAA-BBBB', 'pin_hash' => '246813']);

        foreach (range(1, 5) as $i) {
            $this->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '00000'.$i]);
        }

        $this->flushSession();
        $this->post('/lacak', ['kode' => 'WBS-AAAA-BBBB', 'pin' => '246813'])->assertRedirect('/lacak/laporan');
    }

    public function test_global_counter_requires_captcha_for_every_wbs_code(): void
    {
        config(['sigap.lacak.wbs_maks_gagal_global' => 3]);
        $this->laporanWbs();

        foreach (['WBS-AAAA-AAA2', 'WBS-AAAA-AAA3', 'WBS-AAAA-AAA4'] as $kode) {
            $this->post('/lacak', ['kode' => $kode, 'pin' => '135792']);
        }

        $this->flushSession();
        $this->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904'])->assertSessionHasErrors('altcha');
        $this->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904', 'altcha' => $this->solusiAltcha()])
            ->assertRedirect('/lacak/laporan');
    }

    public function test_wbs_failures_never_touch_ip_keyed_counters_or_logs(): void
    {
        $catatan = [];
        Event::listen(MessageLogged::class, function (MessageLogged $pesan) use (&$catatan): void {
            $catatan[] = $pesan->message.' '.json_encode($pesan->context);
        });
        $this->laporanWbs();

        foreach (range(1, 6) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => self::IP])->withHeader('User-Agent', self::AGEN)
                ->post('/lacak', ['kode' => 'WBS-ZZZZ-ZZZ'.$i, 'pin' => '135792']);
        }

        $this->assertSame(0, RateLimiter::attempts('lacak:gagal:'.hash('sha256', self::IP)));
        $this->assertTidakAdaJejakIp($this->kunciCache());
        $this->assertTidakAdaJejakIp($catatan);
    }

    public function test_tracking_session_for_a_wbs_report_stores_no_ip_or_user_agent(): void
    {
        config(['session.driver' => 'database']);
        $this->laporanWbs();

        $this->withServerVariables(['REMOTE_ADDR' => self::IP])->withHeader('User-Agent', self::AGEN)
            ->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904'])
            ->assertRedirect('/lacak/laporan');

        $baris = DB::table('sessions')->get();
        $this->assertNotEmpty($baris);
        foreach ($baris as $sesi) {
            $this->assertNull($sesi->ip_address);
            $this->assertNull($sesi->user_agent);
            $this->assertStringNotContainsString(self::IP, base64_decode($sesi->payload));
        }
    }

    public function test_detail_shows_only_notes_for_the_reporter_and_messages_from_the_wbs_team(): void
    {
        $laporan = $this->laporanWbs();
        $laporan->riwayatStatus()->create(['status_lama' => StatusLaporan::Diterima, 'status_baru' => StatusLaporan::Ditelaah, 'catatan' => 'Catatan internal telaah']);
        $laporan->update(['status' => StatusLaporan::TidakDitindaklanjuti]);
        $laporan->riwayatStatus()->create([
            'status_lama' => StatusLaporan::Ditelaah,
            'status_baru' => StatusLaporan::TidakDitindaklanjuti,
            'catatan' => 'Bukti belum cukup untuk investigasi.',
            'tampil_ke_pelapor' => true,
        ]);
        Pesan::factory()->for($laporan)->create(['pengirim' => PengirimPesan::Petugas, 'isi' => 'Mohon kirim bukti transfer.']);
        Pesan::factory()->for($laporan)->internal()->create(['pengirim' => PengirimPesan::Petugas, 'isi' => 'Rahasia tim']);

        $this->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904']);

        $this->get('/lacak/laporan')->assertInertia(fn (Assert $page) => $page
            ->has('riwayat', 2)
            ->where('riwayat.0.catatan', null)
            ->where('riwayat.1.catatan', 'Bukti belum cukup untuk investigasi.')
            ->has('pesan', 1)
            ->where('pesan.0.dari', 'Tim WBS')
            ->where('pesan.0.isi', 'Mohon kirim bukti transfer.')
            ->where('bolehKirimPesan', false));
    }

    public function test_history_rows_without_a_status_change_stay_internal(): void
    {
        $laporan = $this->laporanWbs();
        $laporan->riwayatStatus()->create(['status_lama' => null, 'status_baru' => StatusLaporan::Diterima]);
        $laporan->riwayatStatus()->create([
            'status_lama' => StatusLaporan::Diterima,
            'status_baru' => StatusLaporan::Diterima,
            'catatan' => 'Kedudukan terlapor dikoreksi.',
        ]);

        $this->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904']);

        $this->get('/lacak/laporan')->assertInertia(fn (Assert $page) => $page->has('riwayat', 1)->where('riwayat.0.status', 'Diterima'));
    }

    public function test_reporter_sends_a_message_with_encrypted_wbs_evidence(): void
    {
        Storage::fake('lampiran');
        $laporan = $this->laporanWbs();
        $this->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904']);
        $this->tokenUnggahWbs = $this->get('/lacak/laporan')->viewData('page')['props']['tokenUnggahWbs'];
        $id = $this->idUnggahanSiap($this->mp3Uji(), 'rekaman.mp3', 'wbs');

        $this->from('/lacak/laporan')
            ->post('/lacak/laporan/pesan', ['isi' => 'Ini rekamannya.', 'lampiran' => [$id]])
            ->assertRedirect('/lacak/laporan')
            ->assertSessionHasNoErrors();

        $pesan = $laporan->pesan()->sole();
        $this->assertSame(PengirimPesan::Pelapor, $pesan->pengirim);
        $lampiran = $pesan->lampiran()->sole();
        $this->assertStringStartsWith('SIGAPENC1', Storage::disk('lampiran')->get($lampiran->path_acak));
    }

    public function test_messages_moved_from_a_complaint_are_labelled_as_complaint_staff_and_later_ones_as_the_wbs_team(): void
    {
        $laporan = $this->laporanWbs();
        $laporan->forceFill(['sumber' => SumberLaporan::Pengaduan, 'dibuat_pada' => '2026-11-18 03:00:00'])->save();
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create(['name' => 'Bu Admin Pengaduan']);
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->create(['name' => 'Pak Ketua SPI']);
        Pesan::factory()->for($laporan)->create(['pengirim' => PengirimPesan::Petugas, 'pengirim_user_id' => $admin->id, 'isi' => 'Mohon foto kerusakan.', 'dibuat_pada' => '2026-11-17 01:00:00']);
        Pesan::factory()->for($laporan)->create(['pengirim' => PengirimPesan::Pelapor, 'isi' => 'Ini fotonya.', 'dibuat_pada' => '2026-11-17 02:00:00']);
        Pesan::factory()->for($laporan)->create(['pengirim' => PengirimPesan::Petugas, 'pengirim_user_id' => $ketua->id, 'isi' => 'Laporan Anda kami telaah.', 'dibuat_pada' => '2026-11-18 05:00:00']);

        $this->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904']);

        $this->get('/lacak/laporan')
            ->assertInertia(fn (Assert $page) => $page
                ->has('pesan', 3)
                ->where('pesan.0.dari', 'Petugas Pengaduan')
                ->where('pesan.1.dari', 'Anda')
                ->where('pesan.2.dari', 'Tim WBS'))
            ->assertDontSee('Bu Admin Pengaduan')
            ->assertDontSee('Pak Ketua SPI');
    }

    public function test_messages_on_a_wbs_report_submitted_directly_stay_labelled_as_the_wbs_team(): void
    {
        $laporan = $this->laporanWbs();
        Pesan::factory()->for($laporan)->create(['pengirim' => PengirimPesan::Petugas, 'isi' => 'Halo.', 'dibuat_pada' => $laporan->dibuat_pada->subMinute()]);

        $this->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904']);

        $this->get('/lacak/laporan')->assertInertia(fn (Assert $page) => $page->where('pesan.0.dari', 'Tim WBS'));
    }

    private function laporanWbs(): Laporan
    {
        return Laporan::factory()->wbs()->create([
            'kode_lacak' => 'WBS-7KQ2-M9PA',
            'judul' => 'Dana kegiatan dipotong',
            'pin_hash' => '582904',
        ]);
    }

    /**
     * @return list<string>
     */
    private function kunciCache(): array
    {
        $simpanan = new ReflectionProperty(Cache::store()->getStore(), 'storage');

        return array_keys($simpanan->getValue(Cache::store()->getStore()));
    }

    /**
     * @param  list<string>  $teks
     */
    private function assertTidakAdaJejakIp(array $teks): void
    {
        $jejak = [self::IP, self::AGEN, hash('sha256', self::IP), sha1(self::IP), md5(self::IP)];

        foreach ($teks as $baris) {
            foreach ($jejak as $calon) {
                $this->assertStringNotContainsString($calon, $baris);
            }
        }
    }
}
