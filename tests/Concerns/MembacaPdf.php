<?php

namespace Tests\Concerns;

/**
 * Mengambil teks dari PDF buatan dompdf untuk diperiksa di test: aliran konten di-inflate, lalu string
 * operator Tj dan TJ digabung (dompdf memecah teks TJ karena kerning).
 */
trait MembacaPdf
{
    protected function teksPdf(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $aliran);

        $konten = implode("\n", array_map(
            fn (string $isi): string => @gzuncompress($isi) ?: (@gzinflate($isi) ?: $isi),
            $aliran[1],
        ));

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
