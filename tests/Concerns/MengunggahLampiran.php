<?php

namespace Tests\Concerns;

use App\Services\Wbs\TokenFormulirWbs;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

/**
 * Mengunggah berkas uji lewat endpoint unggah bertahap, seperti komponen unggah di browser. Kanal WBS memakai
 * jalur /wbs/lampiran/unggah dengan token formulir WBS di header X-Token-Formulir.
 */
trait MengunggahLampiran
{
    protected ?string $tokenUnggahWbs = null;

    protected function unggahBertahap(string $path, string $namaAsli, string $kanal = 'pengaduan'): TestResponse
    {
        $mulai = $this->postJson($this->alamatUnggah($kanal), [
            'nama' => $namaAsli,
            'ukuran' => filesize($path),
            'kanal' => $kanal,
        ], $this->headerUnggah($kanal));

        if ($mulai->status() !== 201) {
            return $mulai;
        }

        for ($indeks = 0; $indeks < $mulai->json('jumlah_potongan'); $indeks++) {
            $this->kirimPotongan($mulai->json('id'), $indeks, $path, $kanal)->assertOk();
        }

        return $this->postJson($this->alamatUnggah($kanal)."/{$mulai->json('id')}/selesai", [], $this->headerUnggah($kanal));
    }

    /**
     * Mengunggah berkas dan mengembalikan id unggahan yang siap dilampirkan.
     */
    protected function idUnggahanSiap(string $path, string $namaAsli, string $kanal = 'pengaduan'): string
    {
        return $this->unggahBertahap($path, $namaAsli, $kanal)->assertOk()->json('id');
    }

    protected function kirimPotongan(string $id, int $indeks, string $path, string $kanal = 'pengaduan'): TestResponse
    {
        $ukuranPotongan = (int) config('sigap.lampiran.ukuran_potongan_mb') * 1024 * 1024;
        $berkas = fopen($path, 'rb');
        fseek($berkas, $indeks * $ukuranPotongan);
        $isi = (string) fread($berkas, $ukuranPotongan);
        fclose($berkas);

        return $this->post($this->alamatUnggah($kanal)."/{$id}/potongan/{$indeks}", [
            'potongan' => UploadedFile::fake()->createWithContent('potongan', $isi),
        ], ['Accept' => 'application/json', ...$this->headerUnggah($kanal)]);
    }

    protected function alamatUnggah(string $kanal): string
    {
        return $kanal === 'wbs' ? '/wbs/lampiran/unggah' : '/lampiran/unggah';
    }

    /**
     * @return array<string, string>
     */
    protected function headerUnggah(string $kanal): array
    {
        if ($kanal !== 'wbs') {
            return [];
        }

        return ['X-Token-Formulir' => $this->tokenUnggahWbs ??= TokenFormulirWbs::terbitkan()];
    }
}
