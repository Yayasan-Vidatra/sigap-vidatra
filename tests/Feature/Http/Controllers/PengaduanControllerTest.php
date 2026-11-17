<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\JenisLaporan;
use App\Enums\JenisPelapor;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MemecahkanAltcha;
use Tests\TestCase;

class PengaduanControllerTest extends TestCase
{
    use MemecahkanAltcha;
    use RefreshDatabase;

    public function test_form_offers_only_active_units_and_pengaduan_categories(): void
    {
        $unit = Unit::factory()->create(['nama' => 'SMP YPVDP']);
        Unit::factory()->nonaktif()->create();
        $kategori = Kategori::factory()->create(['nama' => 'Kebersihan']);
        Kategori::factory()->wbs()->create();

        $response = $this->get('/pengaduan');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pengaduan/Formulir')
            ->where('unit', [['id' => $unit->id, 'nama' => 'SMP YPVDP']])
            ->where('kategori', [['id' => $kategori->id, 'nama' => 'Kebersihan']])
            ->has('jenisPelapor', 4)
            ->has('kataKunciWbs'));
    }

    public function test_valid_complaint_is_stored_as_received_and_shows_the_tracking_code(): void
    {
        $isian = $this->isianSah();

        $response = $this->post('/pengaduan', $isian);

        $laporan = Laporan::query()->sole();
        $response->assertRedirect(route('bukti-lapor'));
        $response->assertSessionHas('bukti_lapor.kode_lacak', $laporan->kode_lacak);
        $this->assertMatchesRegularExpression('/^PGD-\d{4}-[23456789A-HJKMNP-Z]{6}$/', $laporan->kode_lacak);
        $this->assertSame(JenisLaporan::Pengaduan, $laporan->jenis);
        $this->assertSame(SumberLaporan::Laman, $laporan->sumber);
        $this->assertSame(StatusLaporan::Diterima, $laporan->status);
        $this->assertSame(JenisPelapor::OrangTua, $laporan->jenis_pelapor);
        $this->assertSame('Wali kelas', $laporan->jabatan_pihak_dikeluhkan);
        $this->assertSame('Rina Kartika', $laporan->identitasPelapor->nama);
        $this->assertSame('rina@example.com', $laporan->identitasPelapor->email);
        $this->assertDatabaseHas('riwayat_status', [
            'laporan_id' => $laporan->id,
            'status_lama' => null,
            'status_baru' => StatusLaporan::Diterima->value,
            'oleh_user_id' => null,
        ]);
    }

    public function test_complaint_content_is_stored_as_plain_text_while_identity_stays_encrypted(): void
    {
        $this->post('/pengaduan', $this->isianSah());

        $laporan = DB::table('laporan')->first();
        $identitas = DB::table('identitas_pelapor')->first();

        $this->assertSame('Toilet lantai 2 rusak', $laporan->judul);
        $this->assertSame('Keran di toilet lantai 2 bocor sejak hari Senin.', $laporan->kronologi);
        $this->assertSame('Gedung B', $laporan->lokasi);
        $this->assertSame('Rina Kartika', Crypt::decryptString($identitas->nama));
    }

    public function test_one_invalid_field_reports_only_that_field_in_indonesian(): void
    {
        $response = $this->post('/pengaduan', $this->isianSah(['judul' => '']));

        $response->assertSessionHasErrors(['judul' => 'Judul wajib diisi.']);
        $response->assertSessionDoesntHaveErrors(['nama', 'email', 'no_hp', 'kronologi', 'unit_id', 'kategori_id']);
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_complaint_without_any_contact_is_rejected(): void
    {
        $response = $this->post('/pengaduan', $this->isianSah(['email' => '', 'no_hp' => '']));

        $response->assertSessionHasErrors(['email', 'no_hp']);
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_phone_number_alone_is_enough_contact(): void
    {
        $response = $this->post('/pengaduan', $this->isianSah(['email' => '', 'no_hp' => '0812-3456-7890']));

        $response->assertSessionHasNoErrors();
        $this->assertSame('0812-3456-7890', Laporan::query()->sole()->identitasPelapor->no_hp);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function pilihanTidakSah(): array
    {
        return [
            'kategori WBS' => [['kategori_id' => 'wbs'], 'kategori_id'],
            'unit nonaktif' => [['unit_id' => 'nonaktif'], 'unit_id'],
            'jenis pelapor tidak dikenal' => [['jenis_pelapor' => 'pegawai'], 'jenis_pelapor'],
            'waktu kejadian di masa depan' => [['waktu_kejadian' => '2099-01-01 10:00'], 'waktu_kejadian'],
            'ketentuan belum disetujui' => [['setuju_ketentuan' => false], 'setuju_ketentuan'],
        ];
    }

    /**
     * @param  array<string, mixed>  $ubah
     */
    #[DataProvider('pilihanTidakSah')]
    public function test_invalid_choice_is_rejected(array $ubah, string $kolom): void
    {
        $ubah = array_map(fn (mixed $nilai): mixed => match ($nilai) {
            'wbs' => Kategori::factory()->wbs()->create()->id,
            'nonaktif' => Unit::factory()->nonaktif()->create()->id,
            default => $nilai,
        }, $ubah);

        $response = $this->post('/pengaduan', $this->isianSah($ubah));

        $response->assertSessionHasErrors([$kolom]);
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_student_complaint_requires_a_guardian_contact_and_consent(): void
    {
        $response = $this->post('/pengaduan', $this->isianSah(['jenis_pelapor' => JenisPelapor::Siswa->value]));

        $response->assertSessionHasErrors([
            'kontak_orang_tua' => 'Pelapor siswa wajib mengisi kontak orang tua/wali.',
            'persetujuan_orang_tua' => 'Orang tua/wali harus menyetujui pengaduan ini.',
        ]);
        $response->assertSessionDoesntHaveErrors(['email', 'no_hp']);
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_student_complaint_stores_the_guardian_contact_and_not_the_students_own(): void
    {
        $response = $this->post('/pengaduan', $this->isianSah([
            'jenis_pelapor' => JenisPelapor::Siswa->value,
            'kontak_orang_tua' => '0811-2222-3333',
            'persetujuan_orang_tua' => true,
            'email' => 'siswa@example.com',
            'no_hp' => '0899-0000-1111',
        ]));

        $response->assertSessionHasNoErrors();
        $identitas = Laporan::query()->sole()->identitasPelapor;
        $this->assertSame('0811-2222-3333', $identitas->kontak_orang_tua);
        $this->assertTrue($identitas->persetujuan_orang_tua);
        $this->assertNull($identitas->email);
        $this->assertNull($identitas->no_hp);
    }

    public function test_filled_honeypot_is_rejected(): void
    {
        $response = $this->post('/pengaduan', $this->isianSah(['situs_web' => 'https://spam.example']));

        $response->assertSessionHasErrors(['situs_web']);
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_sixth_submission_within_a_minute_is_refused(): void
    {
        config(['sigap.pengaduan.batas_kirim_per_menit' => 5]);

        foreach (range(1, 5) as $kiriman) {
            $this->post('/pengaduan', $this->isianSah())->assertRedirect(route('bukti-lapor'));
        }
        $response = $this->from('/pengaduan')->post('/pengaduan', $this->isianSah());

        $response->assertRedirect('/pengaduan');
        $response->assertSessionHasErrors(['kirim']);
        $this->assertDatabaseCount('laporan', 5);
    }

    public function test_submissions_are_accepted_again_after_a_minute(): void
    {
        foreach (range(1, 5) as $kiriman) {
            $this->post('/pengaduan', $this->isianSah());
        }

        $this->travel(61)->seconds();
        $response = $this->post('/pengaduan', $this->isianSah());

        $response->assertRedirect(route('bukti-lapor'));
        $this->assertDatabaseCount('laporan', 6);
    }

    public function test_receipt_page_shows_the_code_right_after_submission(): void
    {
        $response = $this->withSession(['bukti_lapor' => ['kode_lacak' => 'PGD-2611-7KQ2M9', 'judul' => 'Toilet rusak']])
            ->get('/selesai');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('BuktiLapor')
            ->where('kodeLacak', 'PGD-2611-7KQ2M9'));
    }

    public function test_receipt_page_without_a_submission_returns_to_beranda(): void
    {
        $this->get('/selesai')->assertRedirect(route('beranda'));
    }

    /**
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function isianSah(array $ubah = []): array
    {
        return [
            'unit_id' => Unit::factory()->create()->id,
            'kategori_id' => Kategori::factory()->create()->id,
            'jenis_pelapor' => JenisPelapor::OrangTua->value,
            'nama' => 'Rina Kartika',
            'email' => 'rina@example.com',
            'no_hp' => '',
            'judul' => 'Toilet lantai 2 rusak',
            'kronologi' => 'Keran di toilet lantai 2 bocor sejak hari Senin.',
            'waktu_kejadian' => '2026-11-16 08:00',
            'lokasi' => 'Gedung B',
            'jabatan_pihak_dikeluhkan' => 'Wali kelas',
            'setuju_ketentuan' => true,
            'situs_web' => '',
            'altcha' => $this->solusiAltcha(),
            ...$ubah,
        ];
    }
}
