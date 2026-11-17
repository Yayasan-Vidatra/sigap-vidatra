<?php

namespace App\Http\Controllers;

use App\Enums\JenisLaporan;
use App\Services\Lampiran\BerkasDitolak;
use App\Services\Lampiran\UnggahanBertahap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Endpoint unggah lampiran bertahap untuk formulir publik (US-04, UC-03). Lihat UnggahanBertahap.
 */
class UnggahLampiranController extends Controller
{
    public function __construct(private UnggahanBertahap $unggahan) {}

    public function mulai(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'ukuran' => ['required', 'integer', 'min:1'],
            'kanal' => ['required', Rule::enum(JenisLaporan::class)],
        ], [
            'required' => 'Data unggahan tidak lengkap.',
        ]);

        try {
            return response()->json($this->unggahan->mulai(
                $data['nama'],
                (int) $data['ukuran'],
                JenisLaporan::from($data['kanal']),
            ), 201);
        } catch (BerkasDitolak $penolakan) {
            return response()->json(['message' => $penolakan->getMessage()], 422);
        }
    }

    public function potongan(Request $request, string $id, int $indeks): JsonResponse
    {
        $request->validate(['potongan' => ['required', 'file']]);

        if (! $this->unggahan->simpanPotongan($id, $indeks, $request->file('potongan'))) {
            return response()->json(['message' => 'Potongan berkas tidak diterima. Ulangi unggahan.'], 422);
        }

        return response()->json(['indeks' => $indeks]);
    }

    public function selesai(string $id): JsonResponse
    {
        try {
            $hasil = $this->unggahan->selesaikan($id);
        } catch (BerkasDitolak $penolakan) {
            return response()->json(['message' => $penolakan->getMessage()], 422);
        }

        if ($hasil === null) {
            return response()->json(['message' => 'Unggahan belum lengkap atau sudah kedaluwarsa. Ulangi unggahan.'], 422);
        }

        return response()->json($hasil);
    }

    public function batal(string $id): JsonResponse
    {
        $this->unggahan->batal($id);

        return response()->json(status: 204);
    }
}
