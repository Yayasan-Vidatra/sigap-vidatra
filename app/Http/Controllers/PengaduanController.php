<?php

namespace App\Http\Controllers;

use App\Enums\JenisLaporan;
use App\Enums\JenisPelapor;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Http\Requests\StorePengaduanRequest;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * H-02 Formulir Pengaduan (US-02, US-03, UC-01). Unggah lampiran (Hari 5) dan surel konfirmasi
 * (Hari 7) belum termasuk.
 */
class PengaduanController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Pengaduan/Formulir', [
            'unit' => Unit::query()->where('aktif', true)->orderBy('id')->get(['id', 'nama']),
            'kategori' => Kategori::query()
                ->where('jenis', JenisLaporan::Pengaduan)
                ->orderBy('nama')
                ->get(['id', 'nama']),
            'jenisPelapor' => collect(JenisPelapor::cases())
                ->map(fn (JenisPelapor $jenis): array => ['value' => $jenis->value, 'label' => $jenis->getLabel()])
                ->all(),
            'kataKunciWbs' => config('sigap.pengaduan.kata_kunci_wbs'),
        ]);
    }

    public function store(StorePengaduanRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $siswa = $request->enum('jenis_pelapor', JenisPelapor::class) === JenisPelapor::Siswa;

        $laporan = DB::transaction(function () use ($data, $siswa): Laporan {
            $laporan = Laporan::create([
                'kode_lacak' => Laporan::buatKodeLacak(JenisLaporan::Pengaduan),
                'jenis' => JenisLaporan::Pengaduan,
                'sumber' => SumberLaporan::Laman,
                'jenis_pelapor' => $data['jenis_pelapor'],
                'unit_id' => $data['unit_id'],
                'kategori_id' => $data['kategori_id'],
                'judul' => $data['judul'],
                'kronologi' => $data['kronologi'],
                'waktu_kejadian' => $data['waktu_kejadian'] ?? null,
                'lokasi' => $data['lokasi'] ?? null,
                'jabatan_pihak_dikeluhkan' => $data['jabatan_pihak_dikeluhkan'] ?? null,
                'status' => StatusLaporan::Diterima,
            ]);

            $laporan->identitasPelapor()->create([
                'nama' => $data['nama'],
                'email' => $siswa ? null : ($data['email'] ?? null),
                'no_hp' => $siswa ? null : ($data['no_hp'] ?? null),
                'kontak_orang_tua' => $siswa ? $data['kontak_orang_tua'] : null,
                'persetujuan_orang_tua' => $siswa,
            ]);

            $laporan->riwayatStatus()->create([
                'status_lama' => null,
                'status_baru' => StatusLaporan::Diterima,
            ]);

            return $laporan;
        });

        return redirect()->route('bukti-lapor')->with('bukti_lapor', [
            'kode_lacak' => $laporan->kode_lacak,
            'judul' => $laporan->judul,
        ]);
    }
}
