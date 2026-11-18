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
 *
 * Kanal ditentukan route: /lampiran/unggah untuk pengaduan (dengan sesi dan CSRF), dan
 * /wbs/lampiran/unggah untuk WBS (tanpa sesi, cookie, dan IP; wajib token formulir WBS). Unggahan satu
 * kanal tidak bisa dilanjutkan, diselesaikan, atau dibatalkan lewat jalur kanal lain.
 */
class UnggahLampiranController extends Controller
{
    public function __construct(private UnggahanBertahap $unggahan) {}

    public function mulai(Request $request): JsonResponse
    {
        $kanal = $this->kanalRute($request);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'ukuran' => ['required', 'integer', 'min:1'],
            'kanal' => ['required', Rule::in([$kanal->value])],
        ], [
            'required' => 'Data unggahan tidak lengkap.',
            'kanal.in' => 'Lampiran ini tidak dapat diunggah lewat formulir ini.',
        ]);

        try {
            return response()->json($this->unggahan->mulai($data['nama'], (int) $data['ukuran'], $kanal), 201);
        } catch (BerkasDitolak $penolakan) {
            return response()->json(['message' => $penolakan->getMessage()], 422);
        }
    }

    public function potongan(Request $request, string $id, int $indeks): JsonResponse
    {
        $request->validate(['potongan' => ['required', 'file']]);

        if (! $this->kanalCocok($request, $id) || ! $this->unggahan->simpanPotongan($id, $indeks, $request->file('potongan'))) {
            return response()->json(['message' => 'Potongan berkas tidak diterima. Ulangi unggahan.'], 422);
        }

        return response()->json(['indeks' => $indeks]);
    }

    public function selesai(Request $request, string $id): JsonResponse
    {
        if (! $this->kanalCocok($request, $id)) {
            return response()->json(['message' => 'Unggahan belum lengkap atau sudah kedaluwarsa. Ulangi unggahan.'], 422);
        }

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

    public function batal(Request $request, string $id): JsonResponse
    {
        if ($this->kanalCocok($request, $id)) {
            $this->unggahan->batal($id);
        }

        return response()->json(status: 204);
    }

    private function kanalRute(Request $request): JenisLaporan
    {
        return $request->routeIs('wbs.*') ? JenisLaporan::Wbs : JenisLaporan::Pengaduan;
    }

    private function kanalCocok(Request $request, string $id): bool
    {
        return $this->unggahan->kanal($id) === $this->kanalRute($request);
    }
}
