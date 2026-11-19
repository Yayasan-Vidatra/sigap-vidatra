<?php

namespace Tests\Concerns;

use RuntimeException;

/**
 * Mengambil teks dari PDF buatan dompdf untuk diperiksa di test: aliran konten di-inflate, lalu string
 * operator Tj dan TJ digabung (dompdf memecah teks TJ karena kerning).
 */
trait MembacaPdf
{
    protected function teksPdf(string $pdf): string
    {
        $konten = implode("\n", $this->aliranPdf($pdf));

        preg_match_all('/\[((?:[^\[\]\\\\]|\\\\.)*)\]\s*TJ|\(((?:[^()\\\\]|\\\\.)*)\)\s*Tj/s', $konten, $operator, PREG_SET_ORDER);

        $baris = array_map(function (array $cocok): string {
            if (($cocok[1] ?? '') !== '') {
                preg_match_all('/\(((?:[^()\\\\]|\\\\.)*)\)/s', $cocok[1], $potongan);

                return implode('', array_map(fn (string $teks): string => $this->dekodeTeksPdf($teks), $potongan[1]));
            }

            return $this->dekodeTeksPdf($cocok[2] ?? '');
        }, $operator);

        return implode("\n", $baris);
    }

    /**
     * Isi setiap aliran (stream) PDF, sudah di-inflate bila FlateDecode. Panjangnya diambil dari /Length di kamus
     * aliran, bukan dari pencarian "endstream": data terkompresi boleh berakhir dengan byte 0x0D, yang bila dicari
     * dengan pola "\r?\nendstream" ikut terpotong sehingga inflate gagal dan teks halaman hilang. Aliran yang tidak
     * dapat dibaca membuat test gagal, bukan dilewati.
     *
     * @return list<string>
     */
    private function aliranPdf(string $pdf): array
    {
        preg_match_all('/<<((?:[^<>]|<<[^<>]*>>|<(?!<)[^<>]*>)*)>>\s*stream(?:\r\n|\n)/s', $pdf, $cocok, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        if ($cocok === []) {
            throw new RuntimeException('Tidak ada aliran yang terbaca dari PDF.');
        }

        return array_map(function (array $aliran) use ($pdf): string {
            $kamus = $aliran[1][0];

            if (preg_match('/\/Length\s+(\d+)(?!\s+\d+\s+R)/', $kamus, $panjang) !== 1) {
                throw new RuntimeException('Aliran PDF tanpa /Length langsung: '.$kamus);
            }

            $mulai = $aliran[0][1] + strlen($aliran[0][0]);
            $isi = substr($pdf, $mulai, (int) $panjang[1]);

            if (preg_match('/\G\s*endstream/', $pdf, $akhir, 0, $mulai + (int) $panjang[1]) !== 1) {
                throw new RuntimeException('/Length aliran PDF tidak cocok dengan letak endstream.');
            }

            if (! str_contains($kamus, '/FlateDecode')) {
                return $isi;
            }

            $hasil = @gzuncompress($isi);

            if ($hasil === false) {
                throw new RuntimeException('Aliran FlateDecode di PDF gagal di-inflate.');
            }

            return $hasil;
        }, $cocok);
    }

    /**
     * Font Unicode (DejaVu) membuat dompdf menulis teks sebagai UTF-16BE; font inti sebagai byte biasa.
     */
    private function dekodeTeksPdf(string $teks): string
    {
        $teks = stripcslashes($teks);

        if ($teks !== '' && strlen($teks) % 2 === 0 && preg_match('/^(?:\x00[\x00-\xFF])+$/s', $teks) === 1) {
            return mb_convert_encoding($teks, 'UTF-8', 'UTF-16BE');
        }

        return $teks;
    }
}
