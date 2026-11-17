<?php

namespace Tests\Feature\Services\Lampiran;

use App\Services\Lampiran\PenyimpananLampiran;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\MembuatBerkasUji;
use Tests\TestCase;

class PenyimpananLampiranTest extends TestCase
{
    use MembuatBerkasUji;

    public function test_stores_under_a_random_name_without_the_original_name_or_extension(): void
    {
        Storage::fake('lampiran');
        $penyimpanan = new PenyimpananLampiran;

        $pathA = $penyimpanan->simpan($this->pdfUji('surat_budi_santoso.pdf'), enkripsi: false);
        $pathB = $penyimpanan->simpan($this->pdfUji('surat_budi_santoso.pdf'), enkripsi: false);

        $this->assertMatchesRegularExpression('#^berkas/[A-Za-z0-9]{40}$#', $pathA);
        $this->assertNotSame($pathA, $pathB);
        $this->assertStringNotContainsString('budi', $pathA);
        Storage::disk('lampiran')->assertExists($pathA);
    }

    public function test_pengaduan_attachment_is_stored_as_is(): void
    {
        Storage::fake('lampiran');
        $sumber = $this->pdfUji();

        $path = (new PenyimpananLampiran)->simpan($sumber, enkripsi: false);

        $this->assertSame(file_get_contents($sumber), Storage::disk('lampiran')->get($path));
    }

    public function test_wbs_attachment_content_is_encrypted_and_decrypts_back(): void
    {
        Storage::fake('lampiran');
        $sumber = $this->mp4Uji(ukuran: 200_000);
        $penyimpanan = new PenyimpananLampiran;

        $path = $penyimpanan->simpan($sumber, enkripsi: true);

        $tersimpan = Storage::disk('lampiran')->get($path);
        $this->assertStringStartsWith('SIGAPENC1', $tersimpan);
        $this->assertStringNotContainsString('ftypisom', $tersimpan);

        $keluaran = fopen('php://memory', 'w+b');
        $penyimpanan->salinIsiKe($path, terenkripsi: true, keluaran: $keluaran);
        rewind($keluaran);
        $this->assertSame(file_get_contents($sumber), stream_get_contents($keluaran));
    }

    public function test_tampered_encrypted_attachment_fails_to_decrypt(): void
    {
        Storage::fake('lampiran');
        $penyimpanan = new PenyimpananLampiran;
        $path = $penyimpanan->simpan($this->txtUji(), enkripsi: true);
        $isi = Storage::disk('lampiran')->get($path);
        Storage::disk('lampiran')->put($path, substr($isi, 0, -1).chr(ord(substr($isi, -1)) ^ 1));

        $this->expectException(RuntimeException::class);

        $penyimpanan->salinIsiKe($path, terenkripsi: true, keluaran: fopen('php://memory', 'w+b'));
    }

    public function test_encrypting_without_a_key_is_refused(): void
    {
        Storage::fake('lampiran');
        config(['sigap.lampiran.kunci_enkripsi' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LAMPIRAN_KUNCI_ENKRIPSI');

        (new PenyimpananLampiran)->simpan($this->txtUji(), enkripsi: true);
    }

    public function test_attachment_disk_is_not_served_publicly(): void
    {
        $this->assertFalse(config('filesystems.disks.lampiran.serve'));
        $this->assertStringNotContainsString('public', config('filesystems.disks.lampiran.root'));
    }
}
