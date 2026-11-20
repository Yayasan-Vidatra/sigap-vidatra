<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AksiAudit;
use App\Enums\PengirimPesan;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Http\Controllers\LacakController;
use App\Http\Controllers\LacakWbsController;
use App\Models\Laporan;
use App\Models\Pesan;
use App\Models\User;
use App\Services\Lacak\TokenLacakWbs;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use ReflectionProperty;
use Tests\Concerns\MembuatBerkasUji;
use Tests\Concerns\MemecahkanAltcha;
use Tests\Concerns\MengunggahLampiran;
use Tests\TestCase;

/**
 * Lacak WBS (/wbs/lacak; US-08, US-09, UC-04, UC-05; revisi 20-11): dipisah dari Lacak Pengaduan, tanpa sesi dan
 * cookie. Pembatasan percobaan per kode dan global tanpa IP; izin dibawa token bertanda tangan di memori halaman.
 */
class LacakWbsTest extends TestCase
{
    use MembuatBerkasUji;
    use MemecahkanAltcha;
    use MengunggahLampiran;
    use RefreshDatabase;

    private const IP = '203.0.113.77';

    private const AGEN = 'AgenUjiWbs/9.9';

    public function test_wbs_tracking_page_opens_without_cookies_and_with_noindex(): void
    {
        $response = $this->get('/wbs/lacak');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Wbs/Lacak'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_wbs_code_and_pin_open_the_detail_without_any_cookie(): void
    {
        $laporan = $this->laporanWbs();

        $response = $this->masuk(['kode' => ' wbs-7kq2-m9pa ', 'pin' => '582904']);

        $response->assertOk()
            ->assertJsonPath('wbs', true)
            ->assertJsonPath('kodeLacak', 'WBS-7KQ2-M9PA')
            ->assertJsonPath('judul', 'Dana kegiatan dipotong')
            ->assertJsonPath('status.label', 'Diterima')
            ->assertJsonPath('status.penjelasan', 'Laporan Anda sudah masuk dan menunggu ditelaah.')
            ->assertJsonPath('maksTautan', 5);
        $this->assertTrue(collect($response->json('aturanLampiran.jenis'))->pluck('label')->contains('Audio'));
        $this->assertTrue(collect($response->json('aturanLampiran.jenis'))->pluck('label')->contains('ZIP'));
        $this->assertNotNull($response->json('tokenUnggahWbs'));
        $this->assertSame($laporan->id, TokenLacakWbs::laporan($response->json('tokenLacak'))?->id);
        $this->assertSame([], $response->headers->getCookies());
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
            $this->masuk($isian)->assertUnprocessable()->assertJsonPath('errors.kode.0', LacakWbsController::PESAN_GAGAL);
        }
    }

    public function test_pengaduan_code_does_not_open_anything_on_wbs_tracking(): void
    {
        Laporan::factory()->create(['kode_lacak' => 'PGD-2611-7KQ2M9']);

        $this->masuk(['kode' => 'PGD-2611-7KQ2M9', 'pin' => '582904'])->assertJsonPath('errors.kode.0', LacakWbsController::PESAN_GAGAL);
    }

    public function test_complaint_tracking_rejects_wbs_codes_without_confirming_they_exist(): void
    {
        $this->laporanWbs();

        $this->from('/lacak')->post('/lacak', ['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904'])
            ->assertRedirect('/lacak')
            ->assertSessionHasErrors(['kode' => LacakController::PESAN_GAGAL]);
        $this->from('/lacak')->post('/lacak', ['kode' => 'WBS-ZZZZ-ZZZZ'])
            ->assertSessionHasErrors(['kode' => LacakController::PESAN_GAGAL]);
        $this->get('/lacak/laporan')->assertRedirect('/lacak');
    }

    public function test_five_failures_on_a_code_require_captcha_and_a_pause_from_any_ip(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-18 02:00:00', 'UTC'));
        $this->laporanWbs();

        foreach (range(1, 5) as $i) {
            $jawaban = $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$i}"])
                ->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '00000'.$i]);
        }

        $jawaban->assertJsonPath('errors.perlu_captcha.0', 'ya')->assertJsonPath('errors.sisa_jeda.0', '60');

        // Berganti IP tidak membuka kunci: penghitung ada pada kode, bukan pada alamat.
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.200'])
            ->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904', 'altcha' => $this->solusiAltcha()])
            ->assertJsonPath('errors.kode.0', 'Terlalu banyak percobaan. Coba lagi dalam 60 detik.');

        $this->travel(61)->seconds();
        $this->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904'])->assertJsonValidationErrors('altcha');
        $this->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904', 'altcha' => $this->solusiAltcha()])->assertOk();
    }

    public function test_per_code_counter_does_not_block_other_codes(): void
    {
        $this->laporanWbs();
        Laporan::factory()->wbs()->create(['kode_lacak' => 'WBS-AAAA-BBBB', 'pin_hash' => '246813']);

        foreach (range(1, 5) as $i) {
            $this->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '00000'.$i]);
        }

        $this->masuk(['kode' => 'WBS-AAAA-BBBB', 'pin' => '246813'])->assertOk();
    }

    public function test_global_counter_requires_captcha_for_every_wbs_code(): void
    {
        config(['sigap.lacak.wbs_maks_gagal_global' => 3]);
        $this->laporanWbs();

        foreach (['WBS-AAAA-AAA2', 'WBS-AAAA-AAA3', 'WBS-AAAA-AAA4'] as $kode) {
            $this->masuk(['kode' => $kode, 'pin' => '135792']);
        }

        $this->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904'])->assertJsonValidationErrors('altcha');
        $this->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904', 'altcha' => $this->solusiAltcha()])->assertOk();
    }

    public function test_wbs_tracking_leaves_no_ip_in_counters_sessions_cache_or_logs(): void
    {
        config(['session.driver' => 'database']);
        $catatan = [];
        Event::listen(MessageLogged::class, function (MessageLogged $pesan) use (&$catatan): void {
            $catatan[] = $pesan->message.' '.json_encode($pesan->context);
        });
        $this->laporanWbs();

        foreach (range(1, 6) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => self::IP])->withHeader('User-Agent', self::AGEN)
                ->masuk(['kode' => 'WBS-ZZZZ-ZZZ'.$i, 'pin' => '135792']);
        }

        $berhasil = $this->withServerVariables(['REMOTE_ADDR' => self::IP])->withHeader('User-Agent', self::AGEN)
            ->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904', 'altcha' => $this->solusiAltcha()]);

        $this->assertSame([], $berhasil->headers->getCookies());
        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertSame(0, RateLimiter::attempts('lacak:gagal:'.hash('sha256', self::IP)));
        $this->assertTidakAdaJejakIp($this->kunciCache());
        $this->assertTidakAdaJejakIp($catatan);
    }

    public function test_tracking_token_expires_after_the_idle_period(): void
    {
        $this->freezeTime();
        $this->laporanWbs();
        $token = $this->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904'])->json('tokenLacak');

        $segar = $this->postJson('/wbs/lacak/detail', ['token' => $token]);
        $segar->assertOk();

        $this->travel(TokenLacakWbs::masaMenit() + 1)->minutes();

        $this->postJson('/wbs/lacak/detail', ['token' => $token])->assertStatus(419);
        $this->postJson('/wbs/lacak/pesan', ['token' => $token, 'isi' => 'Halo'])->assertStatus(419);
        $this->postJson('/wbs/lacak/detail', ['token' => 'palsu.palsu'])->assertStatus(419);
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

        $this->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904'])
            ->assertJsonCount(2, 'riwayat')
            ->assertJsonPath('riwayat.0.catatan', null)
            ->assertJsonPath('riwayat.1.catatan', 'Bukti belum cukup untuk investigasi.')
            ->assertJsonCount(1, 'pesan')
            ->assertJsonPath('pesan.0.dari', 'Tim WBS')
            ->assertJsonPath('pesan.0.isi', 'Mohon kirim bukti transfer.')
            ->assertJsonPath('bolehKirimPesan', false);
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

        $this->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904'])
            ->assertJsonCount(1, 'riwayat')
            ->assertJsonPath('riwayat.0.status', 'Diterima');
    }

    public function test_reporter_sends_a_message_with_encrypted_wbs_evidence_and_a_drive_link(): void
    {
        Storage::fake('lampiran');
        $laporan = $this->laporanWbs();
        $detail = $this->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904']);
        $this->tokenUnggahWbs = $detail->json('tokenUnggahWbs');
        $id = $this->idUnggahanSiap($this->mp3Uji(), 'rekaman.mp3', 'wbs');

        $jawaban = $this->postJson('/wbs/lacak/pesan', [
            'token' => $detail->json('tokenLacak'),
            'isi' => 'Ini rekamannya.',
            'lampiran' => [$id],
            'tautan' => ['https://drive.google.com/file/d/abc123/view'],
            'setuju_tautan' => true,
        ]);

        $jawaban->assertOk()->assertJsonPath('berhasil', 'Pesan terkirim.')->assertJsonPath('pesan.0.jumlahTautan', 1);
        $this->assertSame([], $jawaban->headers->getCookies());
        $pesan = $laporan->pesan()->sole();
        $this->assertSame(PengirimPesan::Pelapor, $pesan->pengirim);
        $this->assertStringStartsWith('SIGAPENC1', Storage::disk('lampiran')->get($pesan->lampiran()->sole()->path_acak));
        $tautan = $pesan->tautanBukti()->sole();
        $this->assertSame('https://drive.google.com/file/d/abc123/view', $tautan->url);
        $this->assertStringNotContainsString('drive.google.com', DB::table('tautan_bukti')->value('url'));
    }

    public function test_message_links_need_consent_and_must_be_google_drive(): void
    {
        $this->laporanWbs();
        $token = $this->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904'])->json('tokenLacak');

        $this->postJson('/wbs/lacak/pesan', ['token' => $token, 'isi' => 'Tautan', 'tautan' => ['https://drive.google.com/file/d/x/view']])
            ->assertJsonValidationErrors('setuju_tautan');
        $this->postJson('/wbs/lacak/pesan', ['token' => $token, 'isi' => 'Tautan', 'tautan' => ['https://dropbox.com/s/x'], 'setuju_tautan' => true])
            ->assertJsonValidationErrors('tautan.0');
        $this->assertSame(0, Pesan::query()->count());
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

        $this->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904'])
            ->assertJsonCount(3, 'pesan')
            ->assertJsonPath('pesan.0.dari', 'Petugas Pengaduan')
            ->assertJsonPath('pesan.1.dari', 'Anda')
            ->assertJsonPath('pesan.2.dari', 'Tim WBS')
            ->assertDontSee('Bu Admin Pengaduan')
            ->assertDontSee('Pak Ketua SPI');
    }

    public function test_messages_on_a_wbs_report_submitted_directly_stay_labelled_as_the_wbs_team(): void
    {
        $laporan = $this->laporanWbs();
        Pesan::factory()->for($laporan)->create(['pengirim' => PengirimPesan::Petugas, 'isi' => 'Halo.', 'dibuat_pada' => $laporan->dibuat_pada->subMinute()]);

        $this->masuk(['kode' => 'WBS-7KQ2-M9PA', 'pin' => '582904'])->assertJsonPath('pesan.0.dari', 'Tim WBS');
    }

    /**
     * @param  array<string, mixed>  $isian
     */
    private function masuk(array $isian): TestResponse
    {
        return $this->postJson('/wbs/lacak', $isian);
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
