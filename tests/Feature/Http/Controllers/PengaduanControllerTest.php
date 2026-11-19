<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\JenisLaporan;
use App\Enums\JenisPelapor;
use App\Enums\StatusLaporan;
use App\Enums\StatusPindai;
use App\Enums\SumberLaporan;
use App\Http\Requests\StorePengaduanRequest;
use App\Jobs\ProsesLampiran;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MembuatBerkasUji;
use Tests\Concerns\MemecahkanAltcha;
use Tests\Concerns\MengunggahLampiran;
use Tests\TestCase;

class PengaduanControllerTest extends TestCase
{
    use MembuatBerkasUji;
    use MemecahkanAltcha;
    use MengunggahLampiran;
    use RefreshDatabase;

    public function test_form_offers_only_active_units_and_pengaduan_categories(): void
    {
        $unit = Unit::factory()->create(['nama' => 'SMP YPVDP', 'kontak_darurat' => '0811-2222-3333']);
        Unit::factory()->nonaktif()->create();
        $kategori = Kategori::factory()->create(['nama' => 'Kebersihan', 'sla_tanggapan_hari' => 2, 'sla_hari' => 10]);
        Kategori::factory()->wbs()->create();

        $response = $this->get('/pengaduan');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Pengaduan/Formulir')
            ->where('unit', [['id' => $unit->id, 'nama' => 'SMP YPVDP', 'kontak_darurat' => '0811-2222-3333']])
            ->where('kategori', [['id' => $kategori->id, 'nama' => 'Kebersihan', 'sla_tanggapan_hari' => 2, 'sla_hari' => 10]])
            ->where('maksKronologi', StorePengaduanRequest::MAKS_KRONOLOGI)
            ->where('kontakDaruratCadangan', 'Segera hubungi wali kelas atau kantor sekolah.')
            ->has('jenisPelapor', 4)
            ->has('kataKunciWbs'));
    }

    public function test_changing_a_category_sla_or_emergency_contact_changes_the_form_without_code(): void
    {
        $unit = Unit::factory()->create(['kontak_darurat' => '0811-1111-1111']);
        $kategori = Kategori::factory()->create(['sla_tanggapan_hari' => 2, 'sla_hari' => 14]);

        $unit->update(['kontak_darurat' => '0822-9999-0000']);
        $kategori->update(['sla_tanggapan_hari' => 1, 'sla_hari' => 5]);

        $this->get('/pengaduan')->assertInertia(fn (Assert $page) => $page
            ->where('unit.0.kontak_darurat', '0822-9999-0000')
            ->where('kategori.0.sla_tanggapan_hari', 1)
            ->where('kategori.0.sla_hari', 5));
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

    public function test_incident_time_is_read_as_wita_and_stored_in_utc(): void
    {
        // Rabu 18 Nov 2026 07.46 WITA. Kejadian 07.00 WITA belum lewat 8 jam, sehingga tidak boleh dianggap
        // waktu di masa depan walau secara angka lebih besar dari jam UTC saat ini.
        $this->travelTo(CarbonImmutable::parse('2026-11-17 23:46:00', 'UTC'));

        $response = $this->post('/pengaduan', $this->isianSah(['waktu_kejadian' => '2026-11-18T07:00']));

        $response->assertSessionHasNoErrors();
        $this->assertSame(
            '2026-11-17 23:00:00',
            DB::table('laporan')->value('waktu_kejadian'),
        );
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

    public function test_attachments_are_stored_under_random_names_and_queued_for_scanning(): void
    {
        Storage::fake('lampiran');
        Queue::fake([ProsesLampiran::class]);
        $idPdf = $this->idUnggahanSiap($this->pdfUji(), 'surat_kepala_sekolah.pdf');
        $idFoto = $this->idUnggahanSiap($this->jpgUji(), 'toilet.jpg');

        $response = $this->post('/pengaduan', $this->isianSah(['lampiran' => [$idPdf, $idFoto]]));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('bukti_lapor.lampiran', ['surat_kepala_sekolah.pdf', 'toilet.jpg']);
        $lampiran = Laporan::query()->sole()->lampiran()->orderBy('id')->get();
        $this->assertSame(['application/pdf', 'image/jpeg'], $lampiran->pluck('mime')->all());
        $this->assertSame([StatusPindai::Menunggu, StatusPindai::Menunggu], $lampiran->pluck('status_pindai')->all());
        foreach ($lampiran as $item) {
            $this->assertMatchesRegularExpression('#^berkas/[A-Za-z0-9]{40}$#', $item->path_acak);
            Storage::disk('lampiran')->assertExists($item->path_acak);
            Queue::assertPushed(ProsesLampiran::class, fn (ProsesLampiran $job): bool => $job->lampiran->is($item));
        }
        $namaMentah = DB::table('lampiran')->orderBy('id')->value('nama_asli');
        $this->assertSame('surat_kepala_sekolah.pdf', Crypt::decryptString($namaMentah));
        $this->assertSame([], Storage::disk('lampiran')->directories('unggahan'));
    }

    public function test_more_than_five_attachments_are_rejected(): void
    {
        Storage::fake('lampiran');
        $id = collect(range(1, 6))->map(fn (int $i): string => $this->idUnggahanSiap($this->txtUji("catatan-{$i}.txt"), "catatan-{$i}.txt"));

        $response = $this->post('/pengaduan', $this->isianSah(['lampiran' => $id->all()]));

        $response->assertSessionHasErrors(['lampiran' => 'Lampiran paling banyak 5 berkas.']);
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_attachments_over_the_total_size_limit_are_rejected(): void
    {
        Storage::fake('lampiran');
        config(['sigap.lampiran.maks_total_mb' => 1]);
        $idA = $this->idUnggahanSiap($this->pdfUji('a.pdf', ukuran: 600_000), 'a.pdf');
        $idB = $this->idUnggahanSiap($this->pdfUji('b.pdf', ukuran: 600_000), 'b.pdf');

        $response = $this->post('/pengaduan', $this->isianSah(['lampiran' => [$idA, $idB]]));

        $response->assertSessionHasErrors(['lampiran' => 'Total ukuran lampiran paling besar 1 MB.']);
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_unknown_or_wbs_upload_ids_are_rejected(): void
    {
        Storage::fake('lampiran');
        $idWbs = $this->unggahBertahap($this->pdfUji(), 'bukti.pdf', kanal: 'wbs')->json('id');

        foreach ([str_repeat('x', 40), $idWbs] as $id) {
            $response = $this->post('/pengaduan', $this->isianSah(['lampiran' => [$id]]));

            $response->assertSessionHasErrors(['lampiran']);
        }
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_an_upload_cannot_be_attached_to_a_second_complaint(): void
    {
        Storage::fake('lampiran');
        Queue::fake([ProsesLampiran::class]);
        $id = $this->idUnggahanSiap($this->pdfUji(), 'bukti.pdf');
        $this->post('/pengaduan', $this->isianSah(['lampiran' => [$id]]))->assertSessionHasNoErrors();

        $response = $this->post('/pengaduan', $this->isianSah(['lampiran' => [$id]]));

        $response->assertSessionHasErrors(['lampiran']);
        $this->assertDatabaseCount('lampiran', 1);
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
