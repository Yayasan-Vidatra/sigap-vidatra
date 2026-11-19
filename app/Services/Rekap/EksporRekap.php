<?php

namespace App\Services\Rekap;

use App\Enums\AksiAudit;
use App\Models\Kategori;
use App\Models\LogAudit;
use App\Models\Unit;
use App\Models\User;
use App\Services\WaktuLokal;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Ekspor Rekap ke Excel dan PDF (H-18, F-14, US-23). Isinya hanya angka dari RekapLaporan: tidak pernah memuat isi
 * laporan, kode lacak, identitas pelapor, terlapor, atau catatan internal. Setiap ekspor tercatat di log audit.
 */
class EksporRekap
{
    public function __construct(private RekapLaporan $rekap) {}

    /**
     * @param  array{dari: string, sampai: string, unit_id?: int|string|null, kategori_id?: int|string|null, tahun_wbs?: int|string|null}  $filter
     * @return string path berkas .xlsx sementara
     */
    public function excel(User $user, array $filter): string
    {
        $data = $this->data($user, $filter);
        $path = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Pengaduan');
        $writer->addRow(Row::fromValues(['Rekap Pengaduan SIGAP Vidatra']));
        $writer->addRow(Row::fromValues(['Periode', $data['periode']]));
        $writer->addRow(Row::fromValues(['Unit', $data['unit'], 'Kategori', $data['kategori']]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Unit', 'Kategori', 'Jumlah', 'Selesai', 'Rata-rata hari penyelesaian', 'Lewat batas waktu']));

        foreach ($data['pengaduan'] as $baris) {
            $writer->addRow(Row::fromValues([
                $baris['unit'], $baris['kategori'], $baris['jumlah'], $baris['selesai'], $baris['rata_rata_hari'] ?? '-', $baris['lewat_sla'],
            ]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([RekapLaporan::CATATAN_PINDAH_WBS]));

        if ($data['wbs'] !== null) {
            $writer->addNewSheetAndMakeItCurrent()->setName('WBS');
            $writer->addRow(Row::fromValues(["Laporan WBS per kategori per triwulan {$data['tahun_wbs']}"]));
            $writer->addRow(Row::fromValues(['Hanya jumlah. Angka di bawah '.RekapLaporan::MINIMAL_WBS.' tidak ditampilkan.']));
            $writer->addRow(Row::fromValues(['Kategori', 'Triwulan 1', 'Triwulan 2', 'Triwulan 3', 'Triwulan 4']));

            foreach ($data['wbs'] as $baris) {
                $writer->addRow(Row::fromValues([
                    $baris['kategori'],
                    ...array_map(fn (?int $nilai): int|string => $nilai ?? '< '.RekapLaporan::MINIMAL_WBS, array_values($baris['triwulan'])),
                ]));
            }
        }

        $writer->close();
        $this->catat($user, 'xlsx', $data);

        return $path;
    }

    /**
     * @param  array{dari: string, sampai: string, unit_id?: int|string|null, kategori_id?: int|string|null, tahun_wbs?: int|string|null}  $filter
     */
    public function pdf(User $user, array $filter): string
    {
        $data = $this->data($user, $filter);
        $isi = Pdf::loadView('pdf.rekap', [...$data, 'minimal' => RekapLaporan::MINIMAL_WBS])->setPaper('a4', 'landscape')->output();
        $this->catat($user, 'pdf', $data);

        return $isi;
    }

    public static function namaBerkas(string $ekstensi): string
    {
        return 'rekap-sigap-'.CarbonImmutable::now(WaktuLokal::zona())->format('Ymd-His').".{$ekstensi}";
    }

    /**
     * @param  array{dari: string, sampai: string, unit_id?: int|string|null, kategori_id?: int|string|null, tahun_wbs?: int|string|null}  $filter
     * @return array{filter: array{dari: string, sampai: string, unit_id: int|null, kategori_id: int|null}, periode: string, unit: string, kategori: string, pengaduan: list<array<string, mixed>>, wbs: list<array{kategori: string, triwulan: array<int, int|null>}>|null, tahun_wbs: int, dibuat: string}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function data(User $user, array $filter): array
    {
        if (! RekapLaporan::bolehBukaRekap($user)) {
            throw new AuthorizationException('Anda tidak berhak membuka rekap.');
        }

        $filter = self::periksaFilter($filter);
        $tahunWbs = (int) ($filter['tahun_wbs'] ?? CarbonImmutable::parse($filter['sampai'])->year);

        return [
            'filter' => $filter,
            'periode' => WaktuLokal::tampilkan(CarbonImmutable::parse($filter['dari']), 'd M Y').' – '.WaktuLokal::tampilkan(CarbonImmutable::parse($filter['sampai']), 'd M Y'),
            'unit' => $filter['unit_id'] !== null ? (Unit::query()->find($filter['unit_id'])->nama ?? '-') : 'Semua unit yang boleh Anda lihat',
            'kategori' => $filter['kategori_id'] !== null ? (Kategori::query()->find($filter['kategori_id'])->nama ?? '-') : 'Semua kategori',
            'pengaduan' => $this->rekap->rekapPengaduan($user, $filter),
            'wbs' => RekapLaporan::bolehRekapWbs($user) ? $this->rekap->wbsPerKategoriPerTriwulan($user, $tahunWbs) : null,
            'tahun_wbs' => $tahunWbs,
            'dibuat' => WaktuLokal::tampilkan(now()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array{dari: string, sampai: string, unit_id: int|null, kategori_id: int|null, tahun_wbs: int|null}
     *
     * @throws ValidationException
     */
    public static function periksaFilter(array $filter): array
    {
        $tanggal = fn (mixed $nilai): ?string => is_string($nilai) && preg_match('/^\d{4}-\d{2}-\d{2}/', $nilai) === 1
            && ($waktu = CarbonImmutable::createFromFormat('!Y-m-d', substr($nilai, 0, 10))) !== false
                ? $waktu->format('Y-m-d')
                : null;

        $dari = $tanggal($filter['dari'] ?? null);
        $sampai = $tanggal($filter['sampai'] ?? null);

        if ($dari === null || $sampai === null || $dari > $sampai) {
            throw ValidationException::withMessages(['data.sampai' => 'Periode tidak sah: isi tanggal awal dan akhir, dan tanggal akhir tidak boleh sebelum tanggal awal.']);
        }

        $angka = fn (mixed $nilai): ?int => filled($nilai) && ctype_digit((string) $nilai) ? (int) $nilai : null;

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'unit_id' => $angka($filter['unit_id'] ?? null),
            'kategori_id' => $angka($filter['kategori_id'] ?? null),
            'tahun_wbs' => $angka($filter['tahun_wbs'] ?? null),
        ];
    }

    /**
     * @param  array{filter: array{dari: string, sampai: string, unit_id: int|null, kategori_id: int|null}, tahun_wbs: int, wbs: mixed}  $data
     */
    private function catat(User $user, string $format, array $data): void
    {
        $filter = $data['filter'];

        LogAudit::catatObjek($user, AksiAudit::MengeksporRekap, mb_substr(sprintf(
            'rekap:%s:%s..%s:unit=%s:kategori=%s%s',
            $format,
            $filter['dari'],
            $filter['sampai'],
            $filter['unit_id'] ?? 'semua',
            $filter['kategori_id'] ?? 'semua',
            $data['wbs'] !== null ? ":wbs={$data['tahun_wbs']}" : '',
        ), 0, 150));
    }
}
