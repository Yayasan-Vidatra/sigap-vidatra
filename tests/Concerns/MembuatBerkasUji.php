<?php

namespace Tests\Concerns;

use PHPUnit\Framework\Attributes\After;
use ZipArchive;

/**
 * Membuat berkas uji yang asli menurut isinya (bukan hanya ekstensinya) untuk menguji deteksi MIME.
 * Berkas besar dibuat dengan ftruncate agar test tetap cepat.
 */
trait MembuatBerkasUji
{
    private ?string $folderBerkasUji = null;

    #[After]
    protected function hapusBerkasUji(): void
    {
        if ($this->folderBerkasUji !== null && is_dir($this->folderBerkasUji)) {
            array_map('unlink', glob($this->folderBerkasUji.DIRECTORY_SEPARATOR.'*') ?: []);
            rmdir($this->folderBerkasUji);
        }

        $this->folderBerkasUji = null;
    }

    protected function mp3Uji(string $nama = 'rekaman.mp3'): string
    {
        return $this->berkasUji($nama, 'ID3'.chr(3).chr(0).chr(0).str_repeat(chr(0), 4)."\xFF\xFB\x90\x64".str_repeat(chr(0), 400));
    }

    protected function berkasUji(string $nama, string $isi = ''): string
    {
        $this->folderBerkasUji ??= sys_get_temp_dir().DIRECTORY_SEPARATOR.'sigap-uji-'.bin2hex(random_bytes(6));

        if (! is_dir($this->folderBerkasUji)) {
            mkdir($this->folderBerkasUji, 0777, true);
        }

        $path = $this->folderBerkasUji.DIRECTORY_SEPARATOR.$nama;
        file_put_contents($path, $isi);

        return $path;
    }

    protected function pdfUji(string $nama = 'bukti.pdf', int $ukuran = 0): string
    {
        return $this->perbesar($this->berkasUji($nama, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n"), $ukuran);
    }

    protected function txtUji(string $nama = 'catatan.txt'): string
    {
        return $this->berkasUji($nama, "Keran di toilet lantai 2 bocor sejak hari Senin.\n");
    }

    protected function pngUji(string $nama = 'foto.png'): string
    {
        $path = $this->berkasUji($nama);
        imagepng(imagecreatetruecolor(4, 4), $path);

        return $path;
    }

    protected function jpgUji(string $nama = 'foto.jpg', int $ukuran = 0): string
    {
        $path = $this->berkasUji($nama);
        imagejpeg(imagecreatetruecolor(4, 4), $path);

        return $this->perbesar($path, $ukuran);
    }

    protected function heicUji(string $nama = 'foto.heic'): string
    {
        return $this->berkasUji($nama, pack('N', 24).'ftypheic'.pack('N', 0).'mif1heic'.pack('N', 8).'free');
    }

    protected function mp4Uji(string $nama = 'video.mp4', int $ukuran = 0): string
    {
        return $this->perbesar($this->berkasUji($nama, pack('N', 24).'ftypisom'.pack('N', 512).'isomiso2'.pack('N', 8).'free'), $ukuran);
    }

    protected function movUji(string $nama = 'video.mov'): string
    {
        return $this->berkasUji($nama, pack('N', 20).'ftypqt  '.pack('N', 512).'qt  '.pack('N', 8).'free');
    }

    protected function docxUji(string $nama = 'surat.docx', bool $makro = false): string
    {
        return $this->ooxmlUji($nama, 'word/document.xml', $makro
            ? 'application/vnd.ms-word.document.macroEnabled.main+xml'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $makro);
    }

    protected function xlsxUji(string $nama = 'rekap.xlsx', bool $makro = false): string
    {
        return $this->ooxmlUji($nama, 'xl/workbook.xml', $makro
            ? 'application/vnd.ms-excel.sheet.macroEnabled.main+xml'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml', $makro);
    }

    /**
     * Arsip ZIP biasa berisi catatan dan foto (revisi 20-11: jenis berkas ZIP).
     */
    protected function zipUji(string $nama = 'bukti.zip'): string
    {
        $path = $this->berkasUji($nama);
        unlink($path);

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('catatan.txt', 'Catatan rapat tanggal 3 November.');
        $zip->addFile($this->jpgUji('di-dalam-zip.jpg'), 'foto.jpg');
        $zip->close();

        return $path;
    }

    /**
     * Skrip PHP yang dinamai seperti PDF.
     */
    protected function skripMenyamarUji(string $nama = 'tagihan.pdf'): string
    {
        return $this->berkasUji($nama, "<?php system(\$_GET['c']);");
    }

    /**
     * Program Windows yang dinamai seperti foto.
     */
    protected function programMenyamarUji(string $nama = 'foto.jpg'): string
    {
        return $this->berkasUji($nama, 'MZ'.str_repeat("\0", 64).'This program cannot be run in DOS mode');
    }

    private function ooxmlUji(string $nama, string $bagian, string $jenisKonten, bool $makro): string
    {
        $path = $this->berkasUji($nama);
        unlink($path);

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Override PartName="/'.$bagian.'" ContentType="'.$jenisKonten.'"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships/>');
        $zip->addFromString($bagian, '<?xml version="1.0"?><root/>');

        if ($makro) {
            $zip->addFromString(dirname($bagian).'/vbaProject.bin', 'VBA');
        }

        $zip->close();

        return $path;
    }

    private function perbesar(string $path, int $ukuran): string
    {
        if ($ukuran > filesize($path)) {
            $berkas = fopen($path, 'r+');
            ftruncate($berkas, $ukuran);
            fclose($berkas);
        }

        return $path;
    }
}
