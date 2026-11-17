<?php

namespace Tests\Feature\Services\Pengaduan;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Enums\Prioritas;
use App\Enums\StatusLaporan;
use App\Events\PengaduanDidisposisikan;
use App\Events\StatusLaporanBerubah;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Models\Disposisi;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengaduan\PenangananPengaduan;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PenangananPengaduanTest extends TestCase
{
    use RefreshDatabase;

    private PenangananPengaduan $penanganan;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->penanganan = app(PenangananPengaduan::class);
        $this->admin = User::factory()->peran(Peran::AdminPengaduan)->create();
    }

    public function test_verification_categorises_the_complaint_and_records_history_and_audit(): void
    {
        Event::fake([StatusLaporanBerubah::class]);
        $laporan = Laporan::factory()->create();
        $kategoriBaru = Kategori::factory()->create();

        $hasil = $this->penanganan->verifikasi($laporan, $this->admin, $kategoriBaru);

        $this->assertSame(StatusLaporan::Terverifikasi, $hasil->status);
        $this->assertDatabaseHas('laporan', [
            'id' => $laporan->id,
            'status' => StatusLaporan::Terverifikasi->value,
            'kategori_id' => $kategoriBaru->id,
        ]);
        $this->assertDatabaseHas('riwayat_status', [
            'laporan_id' => $laporan->id,
            'status_lama' => StatusLaporan::Diterima->value,
            'status_baru' => StatusLaporan::Terverifikasi->value,
            'oleh_user_id' => $this->admin->id,
            'tampil_ke_pelapor' => false,
        ]);
        $this->assertDatabaseHas('log_audit', [
            'user_id' => $this->admin->id,
            'aksi' => AksiAudit::MemverifikasiPengaduan->value,
            'objek' => "laporan:{$laporan->id}",
        ]);
        Event::assertDispatched(StatusLaporanBerubah::class, fn (StatusLaporanBerubah $event): bool => $event->laporan->is($laporan)
            && $event->riwayat->status_baru === StatusLaporan::Terverifikasi);
    }

    public function test_verification_rejects_a_wbs_category(): void
    {
        $laporan = Laporan::factory()->create();

        $this->expectException(AksiPengaduanTidakSah::class);

        try {
            $this->penanganan->verifikasi($laporan, $this->admin, Kategori::factory()->wbs()->create());
        } finally {
            $this->assertSame(StatusLaporan::Diterima, $laporan->fresh()->status);
        }
    }

    public function test_rejection_stores_the_reason_in_the_status_history(): void
    {
        $laporan = Laporan::factory()->create();

        $this->penanganan->tolak($laporan, $this->admin, '  Bukan wewenang yayasan.  ');

        $this->assertSame(StatusLaporan::Ditolak, $laporan->fresh()->status);
        $riwayat = $laporan->riwayatStatus()->latest('id')->first();
        $this->assertSame(StatusLaporan::Ditolak, $riwayat->status_baru);
        $this->assertSame('Bukan wewenang yayasan.', $riwayat->catatan);
        $this->assertTrue($riwayat->tampil_ke_pelapor);
        $this->assertDatabaseHas('log_audit', [
            'aksi' => AksiAudit::MenolakPengaduan->value,
            'objek' => "laporan:{$laporan->id}",
        ]);
    }

    #[TestWith([''])]
    #[TestWith(["   \n  "])]
    public function test_rejection_requires_a_reason(string $alasan): void
    {
        $laporan = Laporan::factory()->create();

        try {
            $this->penanganan->tolak($laporan, $this->admin, $alasan);
            $this->fail('Penolakan tanpa alasan seharusnya ditolak.');
        } catch (AksiPengaduanTidakSah $galat) {
            $this->assertSame('Alasan penolakan wajib diisi.', $galat->getMessage());
        }

        $this->assertSame(StatusLaporan::Diterima, $laporan->fresh()->status);
        $this->assertDatabaseCount('riwayat_status', 0);
    }

    public function test_a_verified_complaint_can_still_be_rejected(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Terverifikasi)->create();

        $this->penanganan->tolak($laporan, $this->admin, 'Laporan ganda.');

        $this->assertSame(StatusLaporan::Ditolak, $laporan->fresh()->status);
    }

    public function test_a_verified_complaint_that_already_has_a_disposition_cannot_be_rejected(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Terverifikasi)->create();
        Disposisi::factory()->for($laporan)->create(['unit_id' => $laporan->unit_id]);

        $this->assertFalse(PenangananPengaduan::bisa('tolak', $laporan));

        try {
            $this->penanganan->tolak($laporan, $this->admin, 'Laporan ganda.');
            $this->fail('Pengaduan yang sudah didisposisikan seharusnya tidak bisa ditolak.');
        } catch (AksiPengaduanTidakSah $galat) {
            $this->assertSame(AksiPengaduanTidakSah::sudahDidisposisikan()->getMessage(), $galat->getMessage());
        }

        $this->assertSame(StatusLaporan::Terverifikasi, $laporan->fresh()->status);
        $this->assertDatabaseCount('riwayat_status', 0);
    }

    public function test_changing_the_unit_on_disposition_records_old_and_new_unit_for_officers_only(): void
    {
        $unitLama = Unit::factory()->create(['nama' => 'SD YPVDP']);
        $unitBaru = Unit::factory()->create(['nama' => 'SMP YPVDP']);
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Terverifikasi)->for($unitLama)->create();
        $pic = User::factory()->peran(Peran::PicUnit)->for($unitBaru)->create();

        $this->penanganan->disposisi($laporan, $this->admin, $unitBaru, $pic, Prioritas::Sedang, null);

        $riwayat = $laporan->riwayatStatus()->latest('id')->first();
        $this->assertSame(StatusLaporan::Diproses, $riwayat->status_baru);
        $this->assertSame('Unit diubah dari SD YPVDP ke SMP YPVDP', $riwayat->catatan);
        $this->assertFalse($riwayat->tampil_ke_pelapor);
        $this->assertSame($unitBaru->id, $laporan->fresh()->unit_id);
        $this->assertDatabaseHas('log_audit', [
            'user_id' => $this->admin->id,
            'aksi' => AksiAudit::MengubahUnitPengaduan->value,
            'objek' => "laporan:{$laporan->id}",
        ]);
    }

    public function test_disposition_to_the_same_unit_records_no_unit_change(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Terverifikasi)->create();
        $pic = User::factory()->peran(Peran::PicUnit)->create(['unit_id' => $laporan->unit_id]);

        $this->penanganan->disposisi($laporan, $this->admin, $laporan->unit, $pic, Prioritas::Sedang, null);

        $this->assertNull($laporan->riwayatStatus()->latest('id')->first()->catatan);
        $this->assertDatabaseMissing('log_audit', ['aksi' => AksiAudit::MengubahUnitPengaduan->value]);
    }

    public function test_disposition_sets_unit_pic_priority_and_a_working_day_deadline_from_the_category_sla(): void
    {
        Event::fake([StatusLaporanBerubah::class, PengaduanDidisposisikan::class]);
        $kategori = Kategori::factory()->create(['sla_tanggapan_hari' => 2, 'sla_hari' => 14]);
        $unitTujuan = Unit::factory()->create();
        $pic = User::factory()->peran(Peran::PicUnit)->for($unitTujuan)->create();
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Terverifikasi)->for($kategori)->create([
            'dibuat_pada' => CarbonImmutable::parse('2026-11-20 09:00:00'),
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00:00'));

        $this->penanganan->disposisi($laporan, $this->admin, $unitTujuan, $pic, Prioritas::Tinggi, ' Mohon dicek hari ini. ');

        $laporan->refresh();
        $this->assertSame(StatusLaporan::Diproses, $laporan->status);
        $this->assertSame($unitTujuan->id, $laporan->unit_id);
        $this->assertSame(Prioritas::Tinggi, $laporan->prioritas);
        // Jumat 20 Nov + 14 hari kerja (tanpa Sabtu-Minggu) = Kamis 10 Des; 14 hari kalender jatuh 4 Des.
        $this->assertSame('2026-12-10 09:00:00', $laporan->batas_waktu->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('disposisi', [
            'laporan_id' => $laporan->id,
            'unit_id' => $unitTujuan->id,
            'pic_user_id' => $pic->id,
            'instruksi' => 'Mohon dicek hari ini.',
        ]);
        $this->assertDatabaseHas('riwayat_status', [
            'laporan_id' => $laporan->id,
            'status_lama' => StatusLaporan::Terverifikasi->value,
            'status_baru' => StatusLaporan::Diproses->value,
        ]);
        $this->assertDatabaseHas('log_audit', [
            'aksi' => AksiAudit::MendisposisikanPengaduan->value,
            'objek' => "laporan:{$laporan->id}",
        ]);
        Event::assertDispatched(PengaduanDidisposisikan::class, fn (PengaduanDidisposisikan $event): bool => $event->disposisi->pic->is($pic));
        Event::assertDispatched(StatusLaporanBerubah::class);
    }

    /**
     * @return array<string, array{string, StatusLaporan}>
     */
    public static function transisiTidakSah(): array
    {
        return [
            'verifikasi ulang yang sudah terverifikasi' => ['verifikasi', StatusLaporan::Terverifikasi],
            'verifikasi yang sudah ditolak' => ['verifikasi', StatusLaporan::Ditolak],
            'verifikasi yang sedang diproses' => ['verifikasi', StatusLaporan::Diproses],
            'tolak yang sedang diproses' => ['tolak', StatusLaporan::Diproses],
            'tolak yang sudah ditolak' => ['tolak', StatusLaporan::Ditolak],
            'tolak yang sudah selesai' => ['tolak', StatusLaporan::Selesai],
            'disposisi sebelum verifikasi' => ['disposisi', StatusLaporan::Diterima],
            'disposisi ulang yang sedang diproses' => ['disposisi', StatusLaporan::Diproses],
            'disposisi yang sudah ditolak' => ['disposisi', StatusLaporan::Ditolak],
        ];
    }

    #[DataProvider('transisiTidakSah')]
    public function test_actions_outside_the_status_flow_are_refused_without_changes(string $aksi, StatusLaporan $status): void
    {
        $laporan = Laporan::factory()->berstatus($status)->create();
        $pic = User::factory()->peran(Peran::PicUnit)->create(['unit_id' => $laporan->unit_id]);

        try {
            match ($aksi) {
                'verifikasi' => $this->penanganan->verifikasi($laporan, $this->admin, $laporan->kategori),
                'tolak' => $this->penanganan->tolak($laporan, $this->admin, 'Alasan.'),
                'disposisi' => $this->penanganan->disposisi($laporan, $this->admin, $laporan->unit, $pic, Prioritas::Sedang, null),
            };
            $this->fail("Aksi {$aksi} pada status {$status->value} seharusnya ditolak.");
        } catch (AksiPengaduanTidakSah) {
            // Diharapkan.
        }

        $this->assertSame($status, $laporan->fresh()->status);
        $this->assertDatabaseCount('riwayat_status', 0);
        $this->assertDatabaseCount('disposisi', 0);
        $this->assertDatabaseCount('log_audit', 0);
    }

    public function test_disposition_refuses_a_pic_from_another_unit_or_with_another_role(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Terverifikasi)->create();
        $picUnitLain = User::factory()->peran(Peran::PicUnit)->create();
        $kepalaSekolah = User::factory()->peran(Peran::KepalaSekolah)->create(['unit_id' => $laporan->unit_id]);
        $picTerkunci = User::factory()->peran(Peran::PicUnit)->terkunci()->create(['unit_id' => $laporan->unit_id]);

        foreach ([$picUnitLain, $kepalaSekolah, $picTerkunci] as $bukanPic) {
            try {
                $this->penanganan->disposisi($laporan, $this->admin, $laporan->unit, $bukanPic, Prioritas::Sedang, null);
                $this->fail('PIC yang tidak sah seharusnya ditolak.');
            } catch (AksiPengaduanTidakSah $galat) {
                $this->assertSame(AksiPengaduanTidakSah::picTidakSah()->getMessage(), $galat->getMessage());
            }
        }

        $this->assertSame(StatusLaporan::Terverifikasi, $laporan->fresh()->status);
    }

    public function test_disposition_refuses_an_inactive_unit(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Terverifikasi)->create();
        $unitNonaktif = Unit::factory()->create(['aktif' => false]);
        $pic = User::factory()->peran(Peran::PicUnit)->for($unitNonaktif)->create();

        $this->expectExceptionObject(AksiPengaduanTidakSah::unitTidakSah());

        $this->penanganan->disposisi($laporan, $this->admin, $unitNonaktif, $pic, Prioritas::Sedang, null);
    }

    public function test_school_admin_can_only_dispose_to_their_own_unit(): void
    {
        $unit = Unit::factory()->create();
        $adminSekolah = User::factory()->peran(Peran::AdminPengaduan)->for($unit)->create();
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Terverifikasi)->for($unit)->create();
        $unitLain = Unit::factory()->create();
        $picUnitLain = User::factory()->peran(Peran::PicUnit)->for($unitLain)->create();

        try {
            $this->penanganan->disposisi($laporan, $adminSekolah, $unitLain, $picUnitLain, Prioritas::Sedang, null);
            $this->fail('Admin Sekolah seharusnya tidak bisa mendisposisikan ke unit lain.');
        } catch (AksiPengaduanTidakSah $galat) {
            $this->assertSame(AksiPengaduanTidakSah::unitTidakSah()->getMessage(), $galat->getMessage());
        }

        $picSendiri = User::factory()->peran(Peran::PicUnit)->for($unit)->create();
        $this->penanganan->disposisi($laporan, $adminSekolah, $unit, $picSendiri, Prioritas::Rendah, null);
        $this->assertSame(StatusLaporan::Diproses, $laporan->fresh()->status);
    }

    public function test_school_admin_cannot_act_on_a_complaint_of_another_unit(): void
    {
        $adminSekolah = User::factory()->peran(Peran::AdminPengaduan)->for(Unit::factory())->create();
        $laporan = Laporan::factory()->create();

        $this->expectException(AuthorizationException::class);

        $this->penanganan->tolak($laporan, $adminSekolah, 'Alasan.');
    }

    public function test_other_roles_cannot_act_on_complaints(): void
    {
        $laporan = Laporan::factory()->create();
        $pic = User::factory()->peran(Peran::PicUnit)->create(['unit_id' => $laporan->unit_id]);

        $this->expectException(AuthorizationException::class);

        $this->penanganan->verifikasi($laporan, $pic, $laporan->kategori);
    }

    public function test_a_wbs_report_cannot_be_handled_as_a_complaint(): void
    {
        $wbs = Laporan::factory()->wbs()->create();

        $this->expectException(AuthorizationException::class);

        $this->penanganan->tolak($wbs, $this->admin, 'Alasan.');
    }
}
