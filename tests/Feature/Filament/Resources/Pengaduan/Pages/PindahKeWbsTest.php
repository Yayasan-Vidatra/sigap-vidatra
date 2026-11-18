<?php

namespace Tests\Feature\Filament\Resources\Pengaduan\Pages;

use App\Enums\AksiAudit;
use App\Enums\JenisLaporan;
use App\Enums\KedudukanTerlapor;
use App\Enums\PengirimPesan;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Enums\StatusPindai;
use App\Enums\SumberLaporan;
use App\Enums\TujuanPengalihan;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Filament\Resources\LaporanWbs\Pages\ViewLaporanWbs;
use App\Filament\Resources\Pengaduan\Pages\ViewPengaduan;
use App\Mail\PemberitahuanPengalihan;
use App\Mail\PemberitahuanWbs;
use App\Models\IdentitasPelapor;
use App\Models\Kategori;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengaduan\PesanLaporan;
use App\Services\Wbs\PemindahPengaduanKeWbs;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pindah ke WBS dari Kotak Masuk (H-11, UC-09 1a, Rancangan r1 Bagian 6), bagian dari US-10.
 */
class PindahKeWbsTest extends TestCase
{
    use RefreshDatabase;

    private Unit $sd;

    private User $admin;

    private User $ketua;

    private User $dewas;

    private Kategori $kategoriWbs;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('lampiran');
        $this->sd = Unit::factory()->create(['nama' => 'SD YPVDP']);
        $this->admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        $this->ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();
        $this->dewas = User::factory()->peran(Peran::DewanPengawas)->denganMfa()->create();
        $this->kategoriWbs = Kategori::factory()->wbs()->create(['nama' => 'Penyalahgunaan dana']);
    }

    public function test_admin_moves_a_complaint_to_wbs_and_its_content_is_moved_encrypted(): void
    {
        $pengaduan = $this->pengaduan();
        $this->actingAs($this->admin);

        Livewire::test(ViewPengaduan::class, ['record' => $pengaduan->id])
            ->callAction('pindahKeWbs', data: $this->isian())
            ->assertHasNoActionErrors()
            ->assertNotified('Pengaduan dipindah ke kanal WBS. Tim WBS akan menyampaikan kode lacak baru kepada pelapor.');

        $wbs = Laporan::query()->where('jenis', JenisLaporan::Wbs->value)->sole();
        $this->assertNotSame($pengaduan->kode_lacak, $wbs->kode_lacak);
        $this->assertStringStartsWith('WBS-', $wbs->kode_lacak);
        $this->assertSame(SumberLaporan::Pengaduan, $wbs->sumber);
        $this->assertSame(StatusLaporan::Diterima, $wbs->status);
        $this->assertSame(KedudukanTerlapor::GuruPegawai, $wbs->kedudukan_terlapor);
        $this->assertNotNull($wbs->batas_waktu);

        $baris = DB::table('laporan')->where('id', $wbs->id)->first();
        $this->assertSame('Dana kegiatan dipotong bendahara tanpa kuitansi.', Crypt::decryptString($baris->kronologi));
        $this->assertSame('Ruang TU', Crypt::decryptString($baris->lokasi));
        $this->assertSame('Bendahara', Crypt::decryptString(DB::table('terlapor_wbs')->where('laporan_id', $wbs->id)->value('nama')));
        $this->assertSame('Sari Pelapor', $wbs->identitasPelapor->nama);
        $this->assertSame('sari@contoh.id', $wbs->identitasPelapor->email);

        $pesan = $wbs->pesan()->sole();
        $this->assertSame('Ada bukti tambahan.', $pesan->isi);
        $lampiran = $wbs->lampiran()->sole();
        $this->assertSame('kuitansi.pdf', $lampiran->nama_asli);
        $this->assertStringStartsWith('SIGAPENC1', Storage::disk('lampiran')->get($lampiran->path_acak));
        Storage::disk('lampiran')->assertMissing('berkas/lama-pengaduan');

        $pengaduan->refresh();
        $this->assertSame(StatusLaporan::DipindahKeWbs, $pengaduan->status);
        $this->assertSame(PemindahPengaduanKeWbs::KRONOLOGI_PENGGANTI, $pengaduan->kronologi);
        $this->assertNull($pengaduan->lokasi);
        $this->assertSame(0, $pengaduan->pesan()->count());
        $this->assertSame(0, $pengaduan->lampiran()->count());

        foreach ([$pengaduan, $wbs] as $objek) {
            $this->assertDatabaseHas('log_audit', ['user_id' => $this->admin->id, 'aksi' => AksiAudit::MemindahkanPengaduanKeWbs->value, 'objek' => "laporan:{$objek->id}"]);
        }
        Mail::assertQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($this->ketua->email));
    }

    public function test_after_the_move_admin_and_pic_no_longer_see_the_content_or_the_new_code(): void
    {
        $pengaduan = $this->pengaduan(StatusLaporan::Diproses);
        $pic = User::factory()->peran(Peran::PicUnit)->for($this->sd)->denganMfa()->create();
        $pengaduan->disposisi()->create(['unit_id' => $this->sd->id, 'pic_user_id' => $pic->id]);
        $wbs = app(PemindahPengaduanKeWbs::class)->pindahkan($pengaduan, $this->admin, $this->isian());

        foreach ([[$this->admin, "/panel/pengaduan/{$pengaduan->id}"], [$pic, "/panel/pengaduan-unit/{$pengaduan->id}"]] as [$user, $alamat]) {
            $this->actingAs($user)->get($alamat)
                ->assertOk()
                ->assertSee('Dipindah ke WBS')
                ->assertDontSee('Dana kegiatan dipotong')
                ->assertDontSee('kuitansi.pdf')
                ->assertDontSee($wbs->kode_lacak);
        }

        $this->assertContains($this->actingAs($this->admin)->get("/panel/wbs/{$wbs->id}")->status(), [403, 404]);

        $this->actingAs($this->admin);
        Livewire::test(ViewPengaduan::class, ['record' => $pengaduan->id])
            ->assertActionHidden('pindahKeWbs')
            ->assertActionHidden('kirimPesan')
            ->assertActionHidden('alihkan');

        $this->expectException(AksiPengaduanTidakSah::class);
        app(PesanLaporan::class)->kirimDariPetugas($pengaduan->fresh(), $this->admin, 'Halo', false);
    }

    public function test_reporter_tracking_the_old_code_sees_the_status_but_cannot_reach_the_wbs_report(): void
    {
        $pengaduan = $this->pengaduan();
        $wbs = app(PemindahPengaduanKeWbs::class)->pindahkan($pengaduan, $this->admin, $this->isian());

        $this->post('/lacak', ['kode' => $pengaduan->kode_lacak])->assertRedirect('/lacak/laporan');
        $this->get('/lacak/laporan')
            ->assertOk()
            ->assertSee('Dipindah ke WBS')
            ->assertDontSee($wbs->kode_lacak)
            ->assertDontSee('Dana kegiatan dipotong');

        $this->assertFalse(PesanLaporan::pelaporBolehMengirim($pengaduan->fresh()));
        $this->assertStringNotContainsString($wbs->kode_lacak, StatusLaporan::DipindahKeWbs->penjelasanUntukPelapor());
    }

    public function test_reviewer_creates_a_temporary_pin_once_and_the_reporter_can_then_track_the_wbs_report(): void
    {
        $wbs = app(PemindahPengaduanKeWbs::class)->pindahkan($this->pengaduan(), $this->admin, $this->isian());
        $this->actingAs($this->ketua);

        $halaman = Livewire::test(ViewLaporanWbs::class, ['record' => $wbs->id])
            ->callAction('buatAksesPelapor')
            ->assertHasNoActionErrors();

        $akses = $halaman->get('aksesPelapor');
        $this->assertSame($wbs->kode_lacak, $akses['kode_lacak']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $akses['pin']);
        $this->assertTrue(Hash::check($akses['pin'], DB::table('laporan')->where('id', $wbs->id)->value('pin_hash')));
        $this->assertDatabaseHas('log_audit', ['user_id' => $this->ketua->id, 'aksi' => AksiAudit::MembuatPinSementaraPelapor->value, 'objek' => "laporan:{$wbs->id}"]);
        $this->assertStringNotContainsString($akses['pin'], json_encode(session()->all()));

        auth()->logout();
        $this->post('/lacak', ['kode' => $wbs->kode_lacak, 'pin' => $akses['pin']])->assertRedirect('/lacak/laporan');
    }

    public function test_only_the_reviewer_can_create_the_pin_and_only_for_moved_reports(): void
    {
        $wbs = app(PemindahPengaduanKeWbs::class)->pindahkan($this->pengaduan(), $this->admin, $this->isian());
        $biasa = Laporan::factory()->wbs()->for($this->sd)->create();

        $this->actingAs($this->ketua);
        Livewire::test(ViewLaporanWbs::class, ['record' => $biasa->id])->assertActionHidden('buatAksesPelapor');

        foreach ([$this->admin, User::factory()->peran(Peran::AnggotaTimWbs)->denganMfa()->create()] as $bukanPenelaah) {
            try {
                app(PemindahPengaduanKeWbs::class)->buatAksesPelapor($wbs, $bukanPenelaah);
                $this->fail('Bukan penelaah utama tidak boleh membuat PIN.');
            } catch (AuthorizationException) {
                $this->assertDatabaseMissing('log_audit', ['user_id' => $bukanPenelaah->id, 'aksi' => AksiAudit::MembuatPinSementaraPelapor->value]);
            }
        }

        $this->expectException(AksiPengaduanTidakSah::class);
        app(PemindahPengaduanKeWbs::class)->buatAksesPelapor($biasa, $this->ketua);
    }

    public function test_the_new_report_is_routed_by_the_chosen_position_so_the_ketua_cannot_open_it(): void
    {
        $wbs = app(PemindahPengaduanKeWbs::class)->pindahkan($this->pengaduan(), $this->admin, [
            ...$this->isian(),
            'kedudukan_terlapor' => KedudukanTerlapor::SpiTimWbs->value,
        ]);

        $this->assertContains($this->actingAs($this->ketua)->get("/panel/wbs/{$wbs->id}")->status(), [403, 404]);
        $this->actingAs($this->dewas)->get("/panel/wbs/{$wbs->id}")->assertOk();
        Mail::assertQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($this->dewas->email));
        Mail::assertNotQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($this->ketua->email));
    }

    public function test_position_category_and_reported_party_are_required(): void
    {
        $pengaduan = $this->pengaduan();
        $this->actingAs($this->admin);

        Livewire::test(ViewPengaduan::class, ['record' => $pengaduan->id])
            ->callAction('pindahKeWbs', data: ['kategori_id' => null, 'kedudukan_terlapor' => null])
            ->assertHasActionErrors(['kategori_id' => 'required', 'kedudukan_terlapor' => 'required']);

        try {
            app(PemindahPengaduanKeWbs::class)->pindahkan($pengaduan, $this->admin, [
                ...$this->isian(),
                'kategori_id' => $pengaduan->kategori_id,
            ]);
            $this->fail('Kategori pengaduan tidak boleh dipakai untuk WBS.');
        } catch (AksiPengaduanTidakSah) {
        }

        $this->expectException(AksiPengaduanTidakSah::class);
        app(PemindahPengaduanKeWbs::class)->pindahkan($pengaduan, $this->admin, [...$this->isian(), 'terlapor' => [['nama' => ' ']]]);
    }

    public function test_final_statuses_cannot_be_moved(): void
    {
        foreach ([StatusLaporan::Selesai, StatusLaporan::Ditolak, StatusLaporan::DipindahKeWbs, StatusLaporan::DialihkanKeKomiteEtik] as $status) {
            $pengaduan = Laporan::factory()->berstatus($status)->for($this->sd)->create();
            $this->actingAs($this->admin);

            Livewire::test(ViewPengaduan::class, ['record' => $pengaduan->id])->assertActionHidden('pindahKeWbs');

            try {
                app(PemindahPengaduanKeWbs::class)->pindahkan($pengaduan, $this->admin, $this->isian());
                $this->fail("Status {$status->value} tidak boleh dipindah.");
            } catch (AksiPengaduanTidakSah) {
                $this->assertSame($status, $pengaduan->fresh()->status);
            }
        }

        $this->assertSame(0, Laporan::query()->where('jenis', JenisLaporan::Wbs->value)->count());
    }

    public function test_only_the_admin_who_may_handle_the_complaint_can_move_it(): void
    {
        $pengaduan = $this->pengaduan();
        $bukanAdmin = [
            User::factory()->peran(Peran::AdminPengaduan)->for(Unit::factory())->denganMfa()->create(),
            User::factory()->peran(Peran::PicUnit)->for($this->sd)->denganMfa()->create(),
            $this->ketua,
            User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create(),
        ];

        foreach ($bukanAdmin as $user) {
            try {
                app(PemindahPengaduanKeWbs::class)->pindahkan($pengaduan, $user, $this->isian());
                $this->fail("Peran {$user->peran->value} tidak boleh memindahkan pengaduan.");
            } catch (AuthorizationException) {
            }
        }

        $this->assertSame(StatusLaporan::Diterima, $pengaduan->fresh()->status);
        $this->assertSame(0, Laporan::query()->where('jenis', JenisLaporan::Wbs->value)->count());
    }

    public function test_ketua_can_refer_a_moved_report_to_pokja_bsan_when_the_school_head_is_involved(): void
    {
        User::factory()->peran(Peran::KetuaUmum)->denganMfa()->create();
        $kepsek = User::factory()->peran(Peran::KepalaSekolah)->for($this->sd)->denganMfa()->create(['name' => 'Rudi Hartono']);
        $wbs = app(PemindahPengaduanKeWbs::class)->pindahkan($this->pengaduan(), $this->admin, [
            ...$this->isian(),
            'terlapor' => [['nama' => 'Rudi Hartono', 'jabatan' => 'Kepala Sekolah']],
        ]);
        $this->actingAs($this->ketua);

        Livewire::test(ViewLaporanWbs::class, ['record' => $wbs->id])
            ->callAction('alihkan', data: ['tujuan' => TujuanPengalihan::PokjaBsan->value, 'kepala_sekolah_terlibat' => true, 'ringkasan' => 'Kekerasan oleh Kepala Sekolah.'])
            ->assertHasNoActionErrors();

        $this->assertSame(StatusLaporan::DirujukKePokjaBsan, $wbs->fresh()->status);
        Mail::assertNotQueued(PemberitahuanPengalihan::class, fn (PemberitahuanPengalihan $surel): bool => $surel->hasTo($kepsek->email));
    }

    private function pengaduan(StatusLaporan $status = StatusLaporan::Diterima): Laporan
    {
        $pengaduan = Laporan::factory()->berstatus($status)->for($this->sd)->create([
            'judul' => 'Potongan dana kegiatan',
            'kronologi' => 'Dana kegiatan dipotong bendahara tanpa kuitansi.',
            'lokasi' => 'Ruang TU',
        ]);
        IdentitasPelapor::factory()->for($pengaduan)->create(['nama' => 'Sari Pelapor', 'email' => 'sari@contoh.id']);
        $pesan = $pengaduan->pesan()->create(['pengirim' => PengirimPesan::Pelapor, 'isi' => 'Ada bukti tambahan.', 'internal' => false]);
        Storage::disk('lampiran')->put('berkas/lama-pengaduan', '%PDF-1.4 kuitansi');
        Lampiran::query()->create([
            'laporan_id' => $pengaduan->id,
            'pesan_id' => $pesan->id,
            'nama_asli' => 'kuitansi.pdf',
            'path_acak' => 'berkas/lama-pengaduan',
            'mime' => 'application/pdf',
            'ukuran' => 17,
            'hash_sha256' => hash('sha256', '%PDF-1.4 kuitansi'),
            'status_pindai' => StatusPindai::Bersih,
        ]);

        return $pengaduan;
    }

    /**
     * @return array{kategori_id: int, kedudukan_terlapor: string, terlapor: list<array{nama: string, jabatan: string}>}
     */
    private function isian(): array
    {
        return [
            'kategori_id' => $this->kategoriWbs->id,
            'kedudukan_terlapor' => KedudukanTerlapor::GuruPegawai->value,
            'terlapor' => [['nama' => 'Bendahara', 'jabatan' => 'Bendahara sekolah']],
        ];
    }
}
