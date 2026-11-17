<?php

namespace App\Services\Lampiran;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menyimpan isi lampiran di disk privat "lampiran" dengan nama acak tanpa ekstensi (Rancangan r1 Bagian 8).
 *
 * Lampiran WBS dienkripsi isinya dengan libsodium secretstream (XChaCha20-Poly1305) per potongan 64 KB,
 * sehingga video besar tidak dimuat utuh ke memori. Kunci dari LAMPIRAN_KUNCI_ENKRIPSI; pengelolaan kunci
 * dengan kendali ganda menyusul di Tahap 2 (US-30).
 */
class PenyimpananLampiran
{
    public const FOLDER_BERKAS = 'berkas';

    public const FOLDER_KARANTINA = 'karantina';

    private const PENANDA = 'SIGAPENC1';

    private const POTONGAN = 65536;

    /**
     * Memindahkan berkas sumber ke disk lampiran dan mengembalikan path acaknya.
     */
    public function simpan(string $pathSumber, bool $enkripsi): string
    {
        $pathAcak = self::FOLDER_BERKAS.'/'.Str::random(40);
        $tujuan = $this->disk()->path($pathAcak);

        if (! is_dir(dirname($tujuan))) {
            mkdir(dirname($tujuan), 0700, true);
        }

        $enkripsi ? $this->enkripsiKe($pathSumber, $tujuan) : copy($pathSumber, $tujuan);

        return $pathAcak;
    }

    /**
     * Menulis isi asli lampiran (didekripsi bila perlu) ke aliran keluaran, potongan demi potongan.
     *
     * @param  resource  $keluaran
     */
    public function salinIsiKe(string $pathAcak, bool $terenkripsi, $keluaran): void
    {
        $masukan = fopen($this->disk()->path($pathAcak), 'rb');

        try {
            if (! $terenkripsi) {
                stream_copy_to_stream($masukan, $keluaran);

                return;
            }

            $this->dekripsiKe($masukan, $keluaran);
        } finally {
            fclose($masukan);
        }
    }

    /**
     * Menyalin isi asli (didekripsi bila perlu) ke berkas sementara untuk pemindaian dan penghapusan
     * metadata. Pemanggil wajib menghapus berkas sementara itu.
     */
    public function keBerkasSementara(string $pathAcak, bool $terenkripsi): string
    {
        $sementara = (string) tempnam(sys_get_temp_dir(), 'sigap-lampiran-');
        $keluaran = fopen($sementara, 'wb');

        try {
            $this->salinIsiKe($pathAcak, $terenkripsi, $keluaran);
        } finally {
            fclose($keluaran);
        }

        return $sementara;
    }

    /**
     * Mengganti isi berkas tersimpan dengan isi baru (mis. setelah metadata dibersihkan), dengan path yang sama.
     */
    public function gantiIsi(string $pathAcak, string $pathSumber, bool $enkripsi): void
    {
        $tujuan = $this->disk()->path($pathAcak);
        $baru = $tujuan.'.baru';

        $enkripsi ? $this->enkripsiKe($pathSumber, $baru) : copy($pathSumber, $baru);

        self::timpa($baru, $tujuan);
    }

    /**
     * Mengganti berkas tujuan dengan berkas baru. rename() atomik di Linux; di Windows rename ke berkas
     * yang sedang dikunci (mis. dipindai antivirus) bisa gagal sesaat, sehingga jatuh ke salin lalu hapus.
     */
    public static function timpa(string $baru, string $tujuan): void
    {
        if (@rename($baru, $tujuan)) {
            return;
        }

        if (! copy($baru, $tujuan)) {
            throw new RuntimeException('Isi lampiran tidak dapat diganti.');
        }

        @unlink($baru);
    }

    /**
     * Memindahkan berkas berbahaya ke folder karantina dan mengembalikan path barunya. Isinya tidak diubah,
     * sehingga lampiran WBS tetap terenkripsi.
     */
    public function pindahKeKarantina(string $pathAcak): string
    {
        $pathKarantina = self::FOLDER_KARANTINA.'/'.basename($pathAcak);
        $this->disk()->move($pathAcak, $pathKarantina);

        return $pathKarantina;
    }

    public function hapus(string $pathAcak): void
    {
        $this->disk()->delete($pathAcak);
    }

    public function disk(): Filesystem
    {
        return Storage::disk('lampiran');
    }

    private function enkripsiKe(string $pathSumber, string $tujuan): void
    {
        $masukan = fopen($pathSumber, 'rb');
        $keluaran = fopen($tujuan, 'wb');

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->kunci());
            fwrite($keluaran, self::PENANDA.$header);

            $potongan = fread($masukan, self::POTONGAN);

            do {
                $berikutnya = fread($masukan, self::POTONGAN);
                $akhir = $berikutnya === '' || $berikutnya === false;
                $tag = $akhir
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;

                fwrite($keluaran, sodium_crypto_secretstream_xchacha20poly1305_push($state, (string) $potongan, '', $tag));
                $potongan = $berikutnya;
            } while (! $akhir);
        } finally {
            fclose($masukan);
            fclose($keluaran);
        }
    }

    /**
     * @param  resource  $masukan
     * @param  resource  $keluaran
     */
    private function dekripsiKe($masukan, $keluaran): void
    {
        $penanda = fread($masukan, strlen(self::PENANDA));
        $header = fread($masukan, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);

        if ($penanda !== self::PENANDA || strlen((string) $header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
            throw new RuntimeException('Berkas lampiran terenkripsi rusak atau bukan buatan SIGAP.');
        }

        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->kunci());
        $panjangPotongan = self::POTONGAN + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

        while (! feof($masukan)) {
            $sandi = fread($masukan, $panjangPotongan);

            if ($sandi === '' || $sandi === false) {
                break;
            }

            $hasil = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $sandi);

            if ($hasil === false) {
                throw new RuntimeException('Berkas lampiran terenkripsi gagal diverifikasi.');
            }

            fwrite($keluaran, $hasil[0]);

            if ($hasil[1] === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                return;
            }
        }

        throw new RuntimeException('Berkas lampiran terenkripsi terpotong.');
    }

    private function kunci(): string
    {
        $kunci = base64_decode((string) config('sigap.lampiran.kunci_enkripsi'), true);

        if ($kunci === false || strlen($kunci) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('LAMPIRAN_KUNCI_ENKRIPSI belum diisi atau bukan 32 byte base64; lampiran WBS tidak dapat disimpan.');
        }

        return $kunci;
    }
}
