<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\JenisLaporan;
use App\Services\Lampiran\UnggahanBertahap;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MembuatBerkasUji;
use Tests\Concerns\MengunggahLampiran;
use Tests\TestCase;

class UnggahLampiranControllerTest extends TestCase
{
    use MembuatBerkasUji;
    use MengunggahLampiran;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sigap.lampiran.ukuran_potongan_mb' => 1]);
    }

    public function test_file_uploaded_in_several_chunks_is_reassembled_and_checked(): void
    {
        Storage::fake('lampiran');
        $sumber = $this->mp4Uji(ukuran: 2_500_000);

        $hasil = $this->unggahBertahap($sumber, 'rekaman cctv.mp4');

        $hasil->assertOk()->assertJson(['nama_asli' => 'rekaman cctv.mp4', 'ukuran' => 2_500_000, 'jenis' => 'video']);
        $siap = app(UnggahanBertahap::class)->ambilSiap($hasil->json('id'), JenisLaporan::Pengaduan);
        $this->assertSame(hash_file('sha256', $sumber), $siap['hash_sha256']);
        $this->assertSame('video/mp4', $siap['mime']);
    }

    public function test_resending_a_chunk_after_a_dropped_connection_is_accepted(): void
    {
        Storage::fake('lampiran');
        $sumber = $this->pdfUji(ukuran: 1_500_000);
        $mulai = $this->postJson('/lampiran/unggah', ['nama' => 'bukti.pdf', 'ukuran' => 1_500_000, 'kanal' => 'pengaduan'])->json();

        $this->kirimPotongan($mulai['id'], 0, $sumber)->assertOk();
        $this->kirimPotongan($mulai['id'], 0, $sumber)->assertOk();
        $this->kirimPotongan($mulai['id'], 1, $sumber)->assertOk();

        $this->postJson("/lampiran/unggah/{$mulai['id']}/selesai")->assertOk()->assertJson(['ukuran' => 1_500_000]);
    }

    public function test_finishing_with_a_missing_chunk_is_refused(): void
    {
        Storage::fake('lampiran');
        $sumber = $this->pdfUji(ukuran: 1_500_000);
        $mulai = $this->postJson('/lampiran/unggah', ['nama' => 'bukti.pdf', 'ukuran' => 1_500_000, 'kanal' => 'pengaduan'])->json();
        $this->kirimPotongan($mulai['id'], 0, $sumber);

        $response = $this->postJson("/lampiran/unggah/{$mulai['id']}/selesai");

        $response->assertUnprocessable();
        $response->assertJson(['message' => 'Unggahan belum lengkap atau sudah kedaluwarsa. Ulangi unggahan.']);
    }

    public function test_chunk_with_the_wrong_size_is_refused(): void
    {
        Storage::fake('lampiran');
        $mulai = $this->postJson('/lampiran/unggah', ['nama' => 'bukti.pdf', 'ukuran' => 1_500_000, 'kanal' => 'pengaduan'])->json();

        $response = $this->post("/lampiran/unggah/{$mulai['id']}/potongan/0", [
            'potongan' => UploadedFile::fake()->createWithContent('potongan', str_repeat('a', 10)),
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable();
    }

    public function test_disguised_file_is_rejected_after_upload_and_removed(): void
    {
        Storage::fake('lampiran');

        $response = $this->unggahBertahap($this->skripMenyamarUji(), 'tagihan.pdf');

        $response->assertUnprocessable();
        $response->assertJsonPath('message', fn (string $pesan): bool => str_contains($pesan, 'format berkas tidak diterima'));
        $this->assertSame([], Storage::disk('lampiran')->directories('unggahan'));
    }

    public function test_document_with_macros_is_rejected(): void
    {
        Storage::fake('lampiran');

        $response = $this->unggahBertahap($this->docxUji(makro: true), 'surat.docx');

        $response->assertUnprocessable();
        $response->assertJsonPath('message', fn (string $pesan): bool => str_contains($pesan, 'berisi makro'));
    }

    public function test_declared_size_over_the_largest_type_is_refused_before_upload(): void
    {
        Storage::fake('lampiran');

        $response = $this->postJson('/lampiran/unggah', [
            'nama' => 'film.mp4',
            'ukuran' => 100 * 1024 * 1024 + 1,
            'kanal' => 'pengaduan',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', fn (string $pesan): bool => str_contains($pesan, '100 MB'));
    }

    public function test_cancelling_removes_the_upload(): void
    {
        Storage::fake('lampiran');
        $hasil = $this->unggahBertahap($this->pdfUji(), 'bukti.pdf');

        $this->deleteJson("/lampiran/unggah/{$hasil->json('id')}")->assertNoContent();

        $this->assertNull(app(UnggahanBertahap::class)->ambilSiap($hasil->json('id'), JenisLaporan::Pengaduan));
        $this->assertSame([], Storage::disk('lampiran')->directories('unggahan'));
    }

    public function test_expired_uploads_are_cleaned_up_by_the_scheduled_command(): void
    {
        Storage::fake('lampiran');
        $hasil = $this->unggahBertahap($this->pdfUji(), 'bukti.pdf');
        touch(Storage::disk('lampiran')->path('unggahan/'.$hasil->json('id')), now()->subHours(25)->getTimestamp());

        $this->artisan('sigap:bersihkan-unggahan')->expectsOutput('Unggahan kedaluwarsa dihapus: 1.')->assertSuccessful();

        $this->assertSame([], Storage::disk('lampiran')->directories('unggahan'));
    }
}
