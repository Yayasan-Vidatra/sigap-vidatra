<?php

namespace Tests\Feature\Models;

use App\Enums\JenisLaporan;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Exceptions\PelanggaranIntegritasData;
use App\Models\Disposisi;
use App\Models\IdentitasPelapor;
use App\Models\Kategori;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\Pengalihan;
use App\Models\Pesan;
use App\Models\RiwayatStatus;
use App\Models\TerlaporWbs;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use RefreshDatabase;

    public function test_wbs_judul_kronologi_and_lokasi_are_stored_encrypted(): void
    {
        $laporan = Laporan::factory()->wbs()->create([
            'judul' => 'Dana BOS dipakai pribadi',
            'kronologi' => 'Bendahara memindahkan dana ke rekening pribadi.',
            'lokasi' => 'Ruang bendahara',
        ]);

        $mentah = DB::table('laporan')->where('id', $laporan->id)->first();

        $this->assertNotSame('Dana BOS dipakai pribadi', $mentah->judul);
        $this->assertSame('Dana BOS dipakai pribadi', Crypt::decryptString($mentah->judul));
        $this->assertNotSame('Bendahara memindahkan dana ke rekening pribadi.', $mentah->kronologi);
        $this->assertSame('Bendahara memindahkan dana ke rekening pribadi.', Crypt::decryptString($mentah->kronologi));
        $this->assertNotSame('Ruang bendahara', $mentah->lokasi);
        $this->assertSame('Ruang bendahara', Crypt::decryptString($mentah->lokasi));

        $tersimpan = $laporan->fresh();

        $this->assertSame('Dana BOS dipakai pribadi', $tersimpan->judul);
        $this->assertSame('Bendahara memindahkan dana ke rekening pribadi.', $tersimpan->kronologi);
        $this->assertSame('Ruang bendahara', $tersimpan->lokasi);
    }

    public function test_pengaduan_judul_kronologi_and_lokasi_are_stored_as_plain_text(): void
    {
        $laporan = Laporan::factory()->create([
            'judul' => 'Toilet lantai 2 rusak',
            'kronologi' => 'Keran bocor sejak Senin.',
            'lokasi' => 'Gedung B lantai 2',
        ]);

        $mentah = DB::table('laporan')->where('id', $laporan->id)->first();

        $this->assertSame('Toilet lantai 2 rusak', $mentah->judul);
        $this->assertSame('Keran bocor sejak Senin.', $mentah->kronologi);
        $this->assertSame('Gedung B lantai 2', $mentah->lokasi);
    }

    public function test_wbs_content_is_encrypted_when_jenis_comes_after_judul_in_the_input(): void
    {
        $kategori = Kategori::factory()->wbs()->create();

        $laporan = Laporan::create([
            'kode_lacak' => 'WBS-7KQ2-M9PA',
            'judul' => 'Suap pengadaan',
            'kronologi' => 'Penyedia memberi uang kepada panitia.',
            'jenis' => JenisLaporan::Wbs,
            'kategori_id' => $kategori->id,
            'status' => StatusLaporan::Diterima,
        ]);

        $mentah = DB::table('laporan')->where('id', $laporan->id)->value('judul');

        $this->assertSame('Suap pengadaan', Crypt::decryptString($mentah));
    }

    public function test_changing_jenis_of_a_saved_laporan_is_rejected(): void
    {
        $laporan = Laporan::factory()->create();

        try {
            $laporan->update(['jenis' => JenisLaporan::Wbs]);
            $this->fail('Perubahan jenis seharusnya ditolak.');
        } catch (PelanggaranIntegritasData) {
        }

        $this->assertSame('pengaduan', DB::table('laporan')->where('id', $laporan->id)->value('jenis'));
    }

    public function test_reading_conditionally_encrypted_column_without_jenis_is_rejected(): void
    {
        Laporan::factory()->wbs()->create();
        $tanpaJenis = Laporan::query()->select(['id', 'judul'])->first();

        $this->expectException(PelanggaranIntegritasData::class);

        $tanpaJenis->judul;
    }

    public function test_status_outside_its_kanal_is_rejected(): void
    {
        $this->expectException(PelanggaranIntegritasData::class);

        try {
            Laporan::factory()->berstatus(StatusLaporan::Ditelaah)->create();
        } finally {
            $this->assertDatabaseCount('laporan', 0);
        }
    }

    public function test_pin_is_stored_as_a_hash(): void
    {
        $laporan = Laporan::factory()->wbs()->create(['pin_hash' => '482915']);

        $mentah = DB::table('laporan')->where('id', $laporan->id)->value('pin_hash');

        $this->assertNotSame('482915', $mentah);
        $this->assertTrue(Hash::check('482915', $mentah));
        $this->assertArrayNotHasKey('pin_hash', $laporan->toArray());
    }

    public function test_wbs_laporan_relations_return_their_records(): void
    {
        $unit = Unit::factory()->create();
        $kategori = Kategori::factory()->wbs()->create();
        $penyetuju = User::factory()->peran(Peran::DewanPengawas)->create();
        $laporan = Laporan::factory()->wbs()->for($unit)->for($kategori)->create([
            'perpanjangan_disetujui_oleh' => $penyetuju->id,
        ]);
        $identitas = IdentitasPelapor::factory()->for($laporan)->create();
        $terlapor = TerlaporWbs::factory()->count(2)->for($laporan)->create();
        $pesan = Pesan::factory()->internal()->for($laporan)->create();
        $lampiran = Lampiran::factory()->for($laporan)->create(['pesan_id' => $pesan->id]);
        $riwayat = RiwayatStatus::factory()->for($laporan)->create();
        $pengalihan = Pengalihan::factory()->for($laporan)->create();

        $laporan->refresh();

        $this->assertTrue($laporan->unit->is($unit));
        $this->assertTrue($laporan->kategori->is($kategori));
        $this->assertTrue($laporan->perpanjanganDisetujuiOleh->is($penyetuju));
        $this->assertTrue($laporan->identitasPelapor->is($identitas));
        $this->assertEqualsCanonicalizing($terlapor->modelKeys(), $laporan->terlapor->modelKeys());
        $this->assertSame([$pesan->id], $laporan->pesan->modelKeys());
        $this->assertSame([$lampiran->id], $laporan->lampiran->modelKeys());
        $this->assertSame([$riwayat->id], $laporan->riwayatStatus->modelKeys());
        $this->assertSame([$pengalihan->id], $laporan->pengalihan->modelKeys());
    }

    public function test_pengaduan_laporan_has_its_disposisi(): void
    {
        $laporan = Laporan::factory()->create();
        $disposisi = Disposisi::factory()->for($laporan)->create();

        $this->assertSame([$disposisi->id], $laporan->disposisi->modelKeys());
    }

    public function test_anonymous_wbs_laporan_has_no_identitas_pelapor(): void
    {
        $laporan = Laporan::factory()->wbs()->create();

        $this->assertNull($laporan->identitasPelapor);
    }
}
