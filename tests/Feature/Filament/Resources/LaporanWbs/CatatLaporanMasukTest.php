<?php

namespace Tests\Feature\Filament\Resources\LaporanWbs;

use App\Enums\AksiAudit;
use App\Enums\KedudukanTerlapor;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Filament\Resources\LaporanWbs\Pages\CatatLaporanMasuk;
use App\Mail\PemberitahuanWbs;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MembuatBerkasUji;
use Tests\TestCase;

/**
 * H-14 Catat Laporan Masuk (US-18, UC-18, F-17).
 */
class CatatLaporanMasukTest extends TestCase
{
    use MembuatBerkasUji;
    use RefreshDatabase;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('lampiran');
        $this->ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();
    }

    public function test_ketua_records_a_report_from_another_channel_and_sees_the_code_and_pin_once(): void
    {
        $this->actingAs($this->ketua);

        $halaman = Livewire::test(CatatLaporanMasuk::class)
            ->fillForm($this->isian())
            ->set('data.lampiran', [UploadedFile::fake()->createWithContent('tangkapan-layar.png', file_get_contents($this->pngUji()))])
            ->call('create')
            ->assertHasNoFormErrors();

        $laporan = Laporan::query()->sole();
        $hasil = $halaman->get('hasil');
        $this->assertSame($laporan->kode_lacak, $hasil['kode_lacak']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $hasil['pin']);
        $halaman->assertSee($laporan->kode_lacak)->assertSee($hasil['pin']);

        $this->assertSame(SumberLaporan::Surel, $laporan->sumber);
        $this->assertSame(StatusLaporan::Diterima, $laporan->status);
        $this->assertNotNull($laporan->batas_waktu);
        $this->assertTrue(Hash::check($hasil['pin'], DB::table('laporan')->value('pin_hash')));
        $this->assertSame('Isi surel: dana kegiatan dipotong tanpa kuitansi sejak Juli.', Crypt::decryptString(DB::table('laporan')->value('kronologi')));
        $this->assertSame('Bendahara Sekolah', Crypt::decryptString(DB::table('terlapor_wbs')->value('nama')));
        $this->assertSame(0, DB::table('identitas_pelapor')->count());

        $lampiran = $laporan->lampiran()->sole();
        $this->assertSame('tangkapan-layar.png', $lampiran->nama_asli);
        $this->assertStringStartsWith('SIGAPENC1', Storage::disk('lampiran')->get($lampiran->path_acak));

        $this->assertDatabaseHas('log_audit', ['user_id' => $this->ketua->id, 'aksi' => AksiAudit::MencatatLaporanMasuk->value, 'objek' => "laporan:{$laporan->id}"]);
        Mail::assertQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($this->ketua->email));
        $this->assertStringNotContainsString($hasil['pin'], json_encode(session()->all()));
    }

    public function test_named_reporter_identity_is_stored_encrypted(): void
    {
        $this->actingAs($this->ketua);

        Livewire::test(CatatLaporanMasuk::class)
            ->fillForm([...$this->isian(), 'mode' => 'bernama', 'nama' => 'Andi Pratama', 'no_hp' => '0812-1111-2222'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Andi Pratama', Crypt::decryptString(DB::table('identitas_pelapor')->value('nama')));
    }

    public function test_report_closed_to_the_ketua_is_recorded_but_not_opened_for_them(): void
    {
        $dewas = User::factory()->peran(Peran::DewanPengawas)->create();
        $this->actingAs($this->ketua);

        $halaman = Livewire::test(CatatLaporanMasuk::class)
            ->fillForm([...$this->isian(), 'kedudukan_terlapor' => KedudukanTerlapor::SpiTimWbs->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue($halaman->get('hasil')['tertutup']);
        $laporan = Laporan::query()->sole();
        $this->assertFalse($this->ketua->can('bukaLaporanWbs', $laporan));
        Mail::assertQueued(PemberitahuanWbs::class, fn (PemberitahuanWbs $surel): bool => $surel->hasTo($dewas->email));
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs($this->ketua);

        Livewire::test(CatatLaporanMasuk::class)
            ->fillForm(['sumber' => null, 'kedudukan_terlapor' => null, 'kronologi' => ''])
            ->call('create')
            ->assertHasFormErrors(['sumber' => 'required', 'kedudukan_terlapor' => 'required', 'kronologi' => 'required']);

        $this->assertSame(0, Laporan::query()->count());
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranSelainKetua(): array
    {
        return [
            'anggota tim wbs' => [Peran::AnggotaTimWbs],
            'dewan pengawas' => [Peran::DewanPengawas],
            'super admin' => [Peran::SuperAdmin],
            'admin pengaduan' => [Peran::AdminPengaduan],
        ];
    }

    #[DataProvider('peranSelainKetua')]
    public function test_only_the_ketua_may_record_incoming_reports(Peran $peran): void
    {
        $user = User::factory()->peran($peran)->denganMfa()->create();

        $this->actingAs($user)->get('/panel/wbs/catat')->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function isian(): array
    {
        return [
            'sumber' => SumberLaporan::Surel->value,
            'mode' => 'anonim',
            'kategori_id' => Kategori::factory()->wbs()->create()->id,
            'unit_id' => Unit::factory()->create()->id,
            'terlapor' => [['nama' => 'Bendahara Sekolah', 'jabatan' => 'Bendahara']],
            'kedudukan_terlapor' => KedudukanTerlapor::GuruPegawai->value,
            'judul' => 'Dana kegiatan dipotong',
            'kronologi' => 'Isi surel: dana kegiatan dipotong tanpa kuitansi sejak Juli.',
        ];
    }
}
