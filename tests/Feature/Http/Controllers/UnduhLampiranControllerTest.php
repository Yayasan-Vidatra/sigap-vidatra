<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\AksiAudit;
use App\Enums\KedudukanTerlapor;
use App\Enums\Peran;
use App\Enums\StatusPindai;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Lampiran\PenyimpananLampiran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MembuatBerkasUji;
use Tests\TestCase;

class UnduhLampiranControllerTest extends TestCase
{
    use MembuatBerkasUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('lampiran');
    }

    public function test_admin_downloads_a_complaint_attachment_and_it_is_logged(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        $sumber = $this->pdfUji();
        $lampiran = $this->lampiran(Laporan::factory()->create(), $sumber, 'surat keluhan.pdf');

        $response = $this->actingAs($admin)->get($lampiran->tautanUnduhSementara());

        $response->assertOk();
        $response->assertDownload('surat keluhan.pdf');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(file_get_contents($sumber), $response->streamedContent());
        $this->assertDatabaseHas('log_audit', [
            'user_id' => $admin->id,
            'aksi' => AksiAudit::MembukaLampiran->value,
            'objek' => "lampiran:{$lampiran->id}",
        ]);
    }

    public function test_ketua_tim_wbs_downloads_the_decrypted_wbs_attachment(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();
        $sumber = $this->mp4Uji(ukuran: 150_000);
        $lampiran = $this->lampiran(Laporan::factory()->wbs()->create(), $sumber, 'rekaman.mp4');

        $response = $this->actingAs($ketua)->get($lampiran->tautanUnduhSementara());

        $response->assertOk();
        $this->assertSame(file_get_contents($sumber), $response->streamedContent());
    }

    public function test_link_is_valid_for_ten_minutes(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        $lampiran = $this->lampiran(Laporan::factory()->create(), $this->pdfUji());
        $tautan = $lampiran->tautanUnduhSementara();

        $this->travel(9)->minutes();
        $this->actingAs($admin)->get($tautan)->assertOk();

        $this->travel(2)->minutes();
        $this->actingAs($admin)->get($tautan)->assertForbidden();
    }

    public function test_tampered_or_unsigned_link_is_refused(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        $lampiran = $this->lampiran(Laporan::factory()->create(), $this->pdfUji());
        $lain = $this->lampiran(Laporan::factory()->create(), $this->pdfUji());

        $dipalsukan = str_replace("/lampiran/{$lampiran->id}/", "/lampiran/{$lain->id}/", $lampiran->tautanUnduhSementara());

        $this->actingAs($admin)->get($dipalsukan)->assertForbidden();
        $this->actingAs($admin)->get("/panel/lampiran/{$lampiran->id}/unduh")->assertForbidden();
        $this->assertDatabaseCount('log_audit', 0);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $lampiran = $this->lampiran(Laporan::factory()->create(), $this->pdfUji());

        $this->get($lampiran->tautanUnduhSementara())->assertRedirect('/panel/login');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function aksesDitolak(): array
    {
        return [
            'PIC unit lain' => ['picUnitLain'],
            'Super Admin membuka WBS' => ['superAdminWbs'],
            'Ketua Tim WBS membuka laporan tentang SPI' => ['ketuaLaporanSpi'],
            'Ketua Tim WBS membuka pengaduan' => ['ketuaPengaduan'],
        ];
    }

    #[DataProvider('aksesDitolak')]
    public function test_officers_outside_the_report_scope_are_forbidden(string $kasus): void
    {
        [$user, $laporan] = match ($kasus) {
            'picUnitLain' => [User::factory()->peran(Peran::PicUnit)->denganMfa()->create(), Laporan::factory()->for(Unit::factory())->create()],
            'superAdminWbs' => [User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create(), Laporan::factory()->wbs()->create()],
            'ketuaLaporanSpi' => [User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create(), Laporan::factory()->wbs()->create(['kedudukan_terlapor' => KedudukanTerlapor::SpiTimWbs])],
            'ketuaPengaduan' => [User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create(), Laporan::factory()->create()],
        };
        $lampiran = $this->lampiran($laporan, $this->pdfUji());

        $response = $this->actingAs($user)->get($lampiran->tautanUnduhSementara());

        $response->assertForbidden();
        $this->assertDatabaseMissing('log_audit', ['aksi' => AksiAudit::MembukaLampiran->value]);
    }

    public function test_pic_unit_downloads_attachments_of_their_own_unit(): void
    {
        $unit = Unit::factory()->create();
        $pic = User::factory()->peran(Peran::PicUnit)->for($unit)->denganMfa()->create();
        $lampiran = $this->lampiran(Laporan::factory()->for($unit)->create(), $this->pdfUji());

        $this->actingAs($pic)->get($lampiran->tautanUnduhSementara())->assertOk();
    }

    public function test_quarantined_attachment_cannot_be_downloaded(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        $lampiran = $this->lampiran(Laporan::factory()->create(), $this->pdfUji());
        $lampiran->update(['status_pindai' => StatusPindai::Karantina]);

        $this->actingAs($admin)->get($lampiran->tautanUnduhSementara())->assertForbidden();
    }

    public function test_ketua_tim_wbs_sees_attachment_downloads_in_the_audit_log(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->create();
        $terbuka = $this->lampiran(Laporan::factory()->wbs()->create(), $this->pdfUji());
        $tentangSpi = $this->lampiran(Laporan::factory()->wbs()->create(['kedudukan_terlapor' => KedudukanTerlapor::SpiTimWbs]), $this->pdfUji());
        $logTerbuka = LogAudit::catat(null, AksiAudit::MembukaLampiran, $terbuka);
        $logSpi = LogAudit::catat(null, AksiAudit::MembukaLampiran, $tentangSpi);

        $terlihat = LogAudit::query()->terlihatOleh($ketua)->pluck('id')->all();

        $this->assertContains($logTerbuka->id, $terlihat);
        $this->assertNotContains($logSpi->id, $terlihat);
    }

    private function lampiran(Laporan $laporan, string $sumber, string $namaAsli = 'bukti.pdf'): Lampiran
    {
        return Lampiran::factory()->for($laporan)->create([
            'nama_asli' => $namaAsli,
            'path_acak' => app(PenyimpananLampiran::class)->simpan($sumber, enkripsi: $laporan->isWbs()),
            'mime' => mime_content_type($sumber),
            'ukuran' => filesize($sumber),
            'status_pindai' => StatusPindai::Bersih,
        ]);
    }
}
