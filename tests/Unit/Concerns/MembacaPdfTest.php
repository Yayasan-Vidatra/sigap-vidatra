<?php

namespace Tests\Unit\Concerns;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Concerns\MembacaPdf;

/**
 * Helper pembaca teks PDF untuk test. Regresi: aliran terkompresi yang berakhir dengan byte 0x0D dulu terpotong satu
 * byte, sehingga teks halaman hilang dan BuktiLaporControllerTest gagal acak.
 */
class MembacaPdfTest extends TestCase
{
    use MembacaPdf;

    public function test_reads_a_compressed_stream_that_ends_with_a_carriage_return_byte(): void
    {
        [$kode, $konten] = $this->kontenYangKompresinyaBerakhirDenganCr();

        $this->assertStringEndsWith("\r", gzcompress($konten));
        $this->assertStringContainsString($kode, $this->teksPdf($this->pdf(gzcompress($konten))));
    }

    public function test_an_unreadable_flate_stream_fails_loudly_instead_of_returning_no_text(): void
    {
        $this->expectException(RuntimeException::class);

        $this->teksPdf($this->pdf('bukan data zlib'));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function kontenYangKompresinyaBerakhirDenganCr(): array
    {
        for ($i = 0; $i < 20000; $i++) {
            $kode = 'PGD-2611-'.strtoupper(substr(md5((string) $i), 0, 6));
            $konten = "BT ({$kode}) Tj ET".str_repeat("\n", $i % 40);

            if (str_ends_with(gzcompress($konten), "\r")) {
                return [$kode, $konten];
            }
        }

        $this->fail('Tidak menemukan contoh kompresi yang berakhir dengan 0x0D.');
    }

    private function pdf(string $aliran): string
    {
        return "%PDF-1.7\n1 0 obj\n<< /Length ".strlen($aliran)." /Filter /FlateDecode >>\nstream\n{$aliran}\nendstream\nendobj\n%%EOF";
    }
}
