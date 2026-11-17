<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

/**
 * Mengunggah berkas uji lewat endpoint unggah bertahap, seperti komponen unggah di browser.
 */
trait MengunggahLampiran
{
    protected function unggahBertahap(string $path, string $namaAsli, string $kanal = 'pengaduan'): TestResponse
    {
        $mulai = $this->postJson('/lampiran/unggah', [
            'nama' => $namaAsli,
            'ukuran' => filesize($path),
            'kanal' => $kanal,
        ]);

        if ($mulai->status() !== 201) {
            return $mulai;
        }

        for ($indeks = 0; $indeks < $mulai->json('jumlah_potongan'); $indeks++) {
            $this->kirimPotongan($mulai->json('id'), $indeks, $path)->assertOk();
        }

        return $this->postJson("/lampiran/unggah/{$mulai->json('id')}/selesai");
    }

    /**
     * Mengunggah berkas dan mengembalikan id unggahan yang siap dilampirkan.
     */
    protected function idUnggahanSiap(string $path, string $namaAsli): string
    {
        return $this->unggahBertahap($path, $namaAsli)->assertOk()->json('id');
    }

    protected function kirimPotongan(string $id, int $indeks, string $path): TestResponse
    {
        $ukuranPotongan = (int) config('sigap.lampiran.ukuran_potongan_mb') * 1024 * 1024;
        $berkas = fopen($path, 'rb');
        fseek($berkas, $indeks * $ukuranPotongan);
        $isi = (string) fread($berkas, $ukuranPotongan);
        fclose($berkas);

        return $this->post("/lampiran/unggah/{$id}/potongan/{$indeks}", [
            'potongan' => UploadedFile::fake()->createWithContent('potongan', $isi),
        ], ['Accept' => 'application/json']);
    }
}
