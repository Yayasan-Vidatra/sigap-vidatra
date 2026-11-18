<?php

namespace Tests\Feature\Services\Pengaduan;

use App\Enums\AksiAudit;
use App\Enums\PengirimPesan;
use App\Enums\Peran;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Jobs\ProsesLampiran;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengaduan\PesanLaporan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MembuatBerkasUji;
use Tests\Concerns\MengunggahLampiran;
use Tests\TestCase;

class PesanLaporanTest extends TestCase
{
    use MembuatBerkasUji;
    use MengunggahLampiran;
    use RefreshDatabase;

    private PesanLaporan $pesan;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('lampiran');
        Storage::fake('local');
        $this->pesan = app(PesanLaporan::class);
    }

    public function test_reporter_message_carries_extra_attachments_that_are_queued_for_scanning(): void
    {
        Queue::fake();
        $laporan = Laporan::factory()->create();
        $idUnggahan = $this->idUnggahanSiap($this->pdfUji(), 'kuitansi.pdf');

        $pesan = $this->pesan->kirimDariPelapor($laporan, '  Ini kuitansi perbaikan sebelumnya.  ', [$idUnggahan]);

        $this->assertSame(PengirimPesan::Pelapor, $pesan->pengirim);
        $this->assertNull($pesan->pengirim_user_id);
        $this->assertFalse($pesan->internal);
        $this->assertSame('Ini kuitansi perbaikan sebelumnya.', $pesan->isi);
        $lampiran = $pesan->lampiran()->sole();
        $this->assertSame('kuitansi.pdf', $lampiran->nama_asli);
        $this->assertSame($laporan->id, $lampiran->laporan_id);
        Queue::assertPushed(ProsesLampiran::class);
        $this->assertDatabaseHas('log_audit', ['user_id' => null, 'aksi' => AksiAudit::PelaporMengirimPesan->value]);
    }

    public function test_admin_reply_records_the_officer_and_can_be_an_internal_note(): void
    {
        $laporan = Laporan::factory()->create();
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create();

        $balasan = $this->pesan->kirimDariPetugas($laporan, $admin, 'Mohon kirim foto terbaru.', internal: false);
        $catatan = $this->pesan->kirimDariPetugas($laporan, $admin, 'Pelapor sudah dua kali melapor hal ini.', internal: true);

        $this->assertSame(PengirimPesan::Petugas, $balasan->pengirim);
        $this->assertSame($admin->id, $balasan->pengirim_user_id);
        $this->assertTrue($laporan->pesanUntukPelapor()->whereKey($balasan->id)->exists());
        $this->assertFalse($laporan->pesanUntukPelapor()->whereKey($catatan->id)->exists());
        $this->assertDatabaseHas('log_audit', ['user_id' => $admin->id, 'aksi' => AksiAudit::MembalasPesan->value]);
    }

    public function test_pic_and_school_admins_of_other_units_cannot_send_messages(): void
    {
        $laporan = Laporan::factory()->create();

        foreach ([
            User::factory()->peran(Peran::PicUnit)->create(['unit_id' => $laporan->unit_id]),
            User::factory()->peran(Peran::AdminPengaduan)->for(Unit::factory())->create(),
        ] as $bukanAdmin) {
            try {
                $this->pesan->kirimDariPetugas($laporan, $bukanAdmin, 'Halo.', internal: false);
                $this->fail('Pesan dari petugas tanpa hak seharusnya ditolak.');
            } catch (AuthorizationException) {
                // Diharapkan.
            }
        }

        $this->assertDatabaseCount('pesan', 0);
    }

    public function test_empty_messages_are_rejected(): void
    {
        $laporan = Laporan::factory()->create();

        $this->expectExceptionObject(AksiPengaduanTidakSah::pesanKosong());

        $this->pesan->kirimDariPelapor($laporan, "  \n ");
    }
}
