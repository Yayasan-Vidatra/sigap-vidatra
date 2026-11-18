<?php

namespace App\Console\Commands;

use App\Enums\AksiAudit;
use App\Enums\JenisLaporan;
use App\Enums\StatusLaporan;
use App\Filament\Resources\Pengaduan\PengaduanResource;
use App\Filament\Resources\PengaduanUnit\PengaduanUnitResource;
use App\Mail\PengingatBatasWaktu;
use App\Models\Disposisi;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\User;
use App\Services\Pengaduan\PenerimaNotifikasi;
use App\Services\WaktuLokal;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Pengingat dan eskalasi batas waktu pengaduan (F-11, UC-09 2a, US-11), dijadwalkan harian pagi WITA. Hanya
 * pengaduan berstatus Diproses (unit belum selesai menindaklanjuti):
 * - pengingat ke PIC bila batas waktu tinggal 2 hari kerja atau kurang (tanggal WITA);
 * - eskalasi ke PIC, Admin Pengaduan (yayasan dan Admin Sekolah unit itu), dan Pimpinan Yayasan bila batas
 *   waktu sudah lewat.
 * Masing-masing dikirim sekali per disposisi; pengirimannya tercatat di log audit dan dipakai untuk mencegah
 * surel ganda bila perintah dijalankan ulang.
 */
#[Signature('sigap:pengingat-batas-waktu')]
#[Description('Mengirim pengingat dan eskalasi batas waktu pengaduan')]
class KirimPengingatBatasWaktu extends Command
{
    public const HARI_KERJA_PENGINGAT = 2;

    public function handle(): int
    {
        $pengingat = 0;
        $eskalasi = 0;
        $batasPengingat = WaktuLokal::tambahHariKerja(WaktuLokal::keLokal(now())->startOfDay(), self::HARI_KERJA_PENGINGAT);

        Laporan::query()
            ->where('jenis', JenisLaporan::Pengaduan->value)
            ->where('status', StatusLaporan::Diproses->value)
            ->whereNotNull('batas_waktu')
            ->with(['unit', 'kategori'])
            ->lazyById()
            ->each(function (Laporan $laporan) use ($batasPengingat, &$pengingat, &$eskalasi): void {
                $disposisi = $laporan->disposisi()->with('pic')->latest('id')->first();

                if ($disposisi === null) {
                    return;
                }

                if ($laporan->batas_waktu->isPast()) {
                    $eskalasi += $this->kirimSekali($laporan, $disposisi, AksiAudit::MengirimEskalasiBatasWaktu, fn () => $this->kirimEskalasi($laporan, $disposisi));

                    return;
                }

                if (WaktuLokal::keLokal($laporan->batas_waktu)->toDateString() <= WaktuLokal::keLokal($batasPengingat)->toDateString()) {
                    $pengingat += $this->kirimSekali($laporan, $disposisi, AksiAudit::MengirimPengingatBatasWaktu, fn () => $this->kirimKePic($laporan, $disposisi, eskalasi: false));
                }
            });

        $this->info("Pengingat dikirim: {$pengingat}. Eskalasi dikirim: {$eskalasi}.");

        return self::SUCCESS;
    }

    /**
     * @param  callable(): void  $kirim
     */
    private function kirimSekali(Laporan $laporan, Disposisi $disposisi, AksiAudit $aksi, callable $kirim): int
    {
        $sudahDikirim = LogAudit::query()
            ->where('aksi', $aksi->value)
            ->where('objek', "laporan:{$laporan->getKey()}")
            ->where('waktu', '>=', $disposisi->dibuat_pada)
            ->exists();

        if ($sudahDikirim) {
            return 0;
        }

        $kirim();
        LogAudit::catat(null, $aksi, $laporan);

        return 1;
    }

    private function kirimEskalasi(Laporan $laporan, Disposisi $disposisi): void
    {
        $this->kirimKePic($laporan, $disposisi, eskalasi: true);

        $tautanAdmin = PengaduanResource::getUrl('view', ['record' => $laporan], panel: 'panel');

        foreach (PenerimaNotifikasi::adminUntuk($laporan) as $admin) {
            Mail::to($admin)->send(new PengingatBatasWaktu($laporan, eskalasi: true, tautan: $tautanAdmin));
        }

        // Pimpinan Yayasan tidak membuka detail pengaduan di panel (dasbor dan rekap saja), jadi tanpa tautan.
        foreach (PenerimaNotifikasi::pimpinanYayasan() as $pimpinan) {
            Mail::to($pimpinan)->send(new PengingatBatasWaktu($laporan, eskalasi: true, tautan: null));
        }
    }

    private function kirimKePic(Laporan $laporan, Disposisi $disposisi, bool $eskalasi): void
    {
        $pic = $disposisi->pic;

        if (! $pic instanceof User || $pic->terkunci()) {
            return;
        }

        Mail::to($pic)->send(new PengingatBatasWaktu(
            $laporan,
            $eskalasi,
            PengaduanUnitResource::getUrl('view', ['record' => $laporan], panel: 'panel'),
        ));
    }
}
