<?php

namespace Tests\Feature\Jobs;

use App\Enums\AksiAudit;
use App\Enums\JenisBerkas;
use App\Enums\PengirimPesan;
use App\Enums\StatusPindai;
use App\Jobs\ProsesLampiran;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Services\Lampiran\PembersihMetadata;
use App\Services\Lampiran\PemindaiVirus;
use App\Services\Lampiran\PenyimpananLampiran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\MembuatBerkasUji;
use Tests\TestCase;

class ProsesLampiranTest extends TestCase
{
    use MembuatBerkasUji;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('lampiran');
    }

    public function test_local_driver_marks_the_attachment_as_not_scanned(): void
    {
        $lampiran = $this->lampiranTersimpan(Laporan::factory()->create(), $this->pdfUji());
        $isiAwal = Storage::disk('lampiran')->get($lampiran->path_acak);

        $this->jalankan($lampiran);

        $this->assertSame(StatusPindai::TidakDipindai, $lampiran->fresh()->status_pindai);
        $this->assertSame($isiAwal, Storage::disk('lampiran')->get($lampiran->path_acak));
    }

    public function test_infected_attachment_is_quarantined_and_the_reporter_is_told(): void
    {
        $laporan = Laporan::factory()->create();
        $lampiran = $this->lampiranTersimpan($laporan, $this->pdfUji(), 'tagihan_sekolah.pdf');
        $pathAwal = $lampiran->path_acak;
        $this->pakaiPemindai(StatusPindai::Karantina, 'Win.Test.EICAR_HDB-1');

        $this->jalankan($lampiran);

        $lampiran->refresh();
        $this->assertSame(StatusPindai::Karantina, $lampiran->status_pindai);
        $this->assertStringStartsWith('karantina/', $lampiran->path_acak);
        Storage::disk('lampiran')->assertMissing($pathAwal);
        Storage::disk('lampiran')->assertExists($lampiran->path_acak);
        $this->assertModelExists($laporan);

        $pesan = $laporan->pesan()->sole();
        $this->assertSame(PengirimPesan::Sistem, $pesan->pengirim);
        $this->assertFalse($pesan->internal);
        $this->assertStringContainsString('tagihan_sekolah.pdf', $pesan->isi);
        $this->assertStringContainsString('Laporan Anda tetap tersimpan', $pesan->isi);
        $this->assertStringNotContainsString('EICAR', $pesan->isi);
        $this->assertDatabaseHas('log_audit', ['aksi' => AksiAudit::LampiranDikarantina->value, 'objek' => "lampiran:{$lampiran->id}"]);
    }

    public function test_clean_attachment_has_its_metadata_stripped_and_hash_updated(): void
    {
        $lampiran = $this->lampiranTersimpan(Laporan::factory()->create(), $this->jpgUji(), 'foto.jpg');
        $this->pakaiPemindai(StatusPindai::Bersih);
        $this->pakaiPembersih('foto-tanpa-exif');

        $this->jalankan($lampiran);

        $lampiran->refresh();
        $this->assertSame(StatusPindai::Bersih, $lampiran->status_pindai);
        $this->assertSame('foto-tanpa-exif', Storage::disk('lampiran')->get($lampiran->path_acak));
        $this->assertSame(hash('sha256', 'foto-tanpa-exif'), $lampiran->hash_sha256);
        $this->assertSame(strlen('foto-tanpa-exif'), $lampiran->ukuran);
    }

    public function test_wbs_attachment_stays_encrypted_after_cleaning(): void
    {
        $lampiran = $this->lampiranTersimpan(Laporan::factory()->wbs()->create(), $this->jpgUji(), 'foto.jpg');
        $this->pakaiPemindai(StatusPindai::Bersih);
        $this->pakaiPembersih('foto-wbs-tanpa-exif');

        $this->jalankan($lampiran);

        $tersimpan = Storage::disk('lampiran')->get($lampiran->fresh()->path_acak);
        $this->assertStringStartsWith('SIGAPENC1', $tersimpan);
        $keluaran = fopen('php://memory', 'w+b');
        app(PenyimpananLampiran::class)->salinIsiKe($lampiran->path_acak, terenkripsi: true, keluaran: $keluaran);
        rewind($keluaran);
        $this->assertSame('foto-wbs-tanpa-exif', stream_get_contents($keluaran));
    }

    public function test_attachment_is_marked_failed_after_the_last_retry(): void
    {
        $lampiran = $this->lampiranTersimpan(Laporan::factory()->create(), $this->pdfUji());

        (new ProsesLampiran($lampiran))->failed(new RuntimeException('clamscan tidak ditemukan'));

        $this->assertSame(StatusPindai::Gagal, $lampiran->fresh()->status_pindai);
    }

    public function test_already_processed_attachment_is_not_processed_again(): void
    {
        $lampiran = $this->lampiranTersimpan(Laporan::factory()->create(), $this->pdfUji());
        $lampiran->update(['status_pindai' => StatusPindai::Bersih]);
        $this->pakaiPemindai(StatusPindai::Karantina, 'tidak boleh terpanggil');

        $this->jalankan($lampiran);

        $this->assertSame(StatusPindai::Bersih, $lampiran->fresh()->status_pindai);
    }

    private function lampiranTersimpan(Laporan $laporan, string $sumber, string $namaAsli = 'bukti.pdf'): Lampiran
    {
        $path = app(PenyimpananLampiran::class)->simpan($sumber, enkripsi: $laporan->isWbs());

        return Lampiran::factory()->for($laporan)->create([
            'nama_asli' => $namaAsli,
            'path_acak' => $path,
            'mime' => mime_content_type($sumber),
            'ukuran' => filesize($sumber),
            'hash_sha256' => hash_file('sha256', $sumber),
        ]);
    }

    private function jalankan(Lampiran $lampiran): void
    {
        app()->call([new ProsesLampiran($lampiran), 'handle']);
    }

    private function pakaiPemindai(StatusPindai $status, ?string $keterangan = null): void
    {
        $this->app->instance(PemindaiVirus::class, new class($status, $keterangan) implements PemindaiVirus
        {
            public function __construct(private StatusPindai $status, private ?string $keterangan) {}

            public function pindai(string $path): array
            {
                return ['status' => $this->status, 'keterangan' => $this->keterangan];
            }
        });
    }

    private function pakaiPembersih(string $isiBaru): void
    {
        $this->app->instance(PembersihMetadata::class, new class($isiBaru) implements PembersihMetadata
        {
            public function __construct(private string $isiBaru) {}

            public function bersihkan(string $path, JenisBerkas $jenis, string $mime): bool
            {
                file_put_contents($path, $this->isiBaru);

                return true;
            }
        });
    }
}
