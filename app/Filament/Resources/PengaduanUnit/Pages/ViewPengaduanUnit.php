<?php

namespace App\Filament\Resources\PengaduanUnit\Pages;

use App\Enums\JenisBerkas;
use App\Enums\JenisLaporan;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Filament\Concerns\MencatatPembukaanLaporan;
use App\Filament\Resources\Pengaduan\Schemas\PengaduanInfolist;
use App\Filament\Resources\PengaduanUnit\PengaduanUnitResource;
use App\Models\Disposisi;
use App\Models\Laporan;
use App\Models\User;
use App\Services\Lampiran\BerkasDitolak;
use App\Services\Pengaduan\TindakLanjutPengaduan;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Detail Pengaduan Unit Saya (H-12, UC-10). Setiap pembukaan halaman dicatat di log audit (US-27).
 * PIC mencatat tindak lanjut dan bukti penyelesaian selama pengaduan Diproses, lalu meminta konfirmasi
 * pelapor. Semua aksi dijalankan TindakLanjutPengaduan, yang memeriksa ulang hak akses dan status di server.
 *
 * @property Laporan $record
 */
class ViewPengaduanUnit extends ViewRecord
{
    use MencatatPembukaanLaporan;

    protected static string $resource = PengaduanUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->aksiCatatTindakLanjut(),
            $this->aksiMintaKonfirmasi(),
        ];
    }

    private function aksiCatatTindakLanjut(): Action
    {
        return Action::make('catatTindakLanjut')
            ->label('Catat tindak lanjut')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('primary')
            ->authorize('tindakLanjutiPengaduan')
            ->visible(fn (Laporan $record): bool => TindakLanjutPengaduan::bisaDitindaklanjuti($record))
            ->modalHeading('Tindak lanjut dan bukti penyelesaian')
            ->modalDescription('Tuliskan apa yang sudah dilakukan unit. Bukti berupa dokumen, foto, atau video (paling besar 10 MB per berkas).')
            ->modalSubmitActionLabel('Simpan')
            ->fillForm(fn (Laporan $record): array => [
                'tindak_lanjut' => $this->disposisiSaya()?->tindak_lanjut,
            ])
            ->schema([
                Textarea::make('tindak_lanjut')
                    ->label('Tindak lanjut')
                    ->maxLength(5000)
                    ->rows(5),
                FileUpload::make('bukti')
                    ->label('Bukti penyelesaian')
                    ->multiple()
                    ->maxFiles(5)
                    ->maxSize(10 * 1024)
                    ->acceptedFileTypes(self::mimeBukti())
                    ->storeFiles(false),
            ])
            ->action(fn (array $data, TindakLanjutPengaduan $tindakLanjut) => $this->jalankan(function (User $pic) use ($data, $tindakLanjut): void {
                $tindakLanjut->simpanTindakLanjut($this->record, $pic, (string) ($data['tindak_lanjut'] ?? ''));

                $berkas = array_map(fn (TemporaryUploadedFile $file): array => [
                    'path' => $file->getRealPath(),
                    'nama_asli' => $file->getClientOriginalName(),
                ], array_values($data['bukti'] ?? []));

                if ($berkas !== []) {
                    $tindakLanjut->lampirkanBukti($this->record, $pic, $berkas);
                }
            }, 'Tindak lanjut tersimpan.'));
    }

    private function aksiMintaKonfirmasi(): Action
    {
        return Action::make('mintaKonfirmasi')
            ->label('Minta konfirmasi pelapor')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->authorize('tindakLanjutiPengaduan')
            ->visible(fn (Laporan $record): bool => TindakLanjutPengaduan::bisaDitindaklanjuti($record))
            ->requiresConfirmation()
            ->modalHeading('Minta konfirmasi pelapor')
            ->modalDescription('Status menjadi Menunggu Konfirmasi. Pelapor diberi 7 hari untuk mengonfirmasi atau menolak hasil; tanpa tanggapan, pengaduan selesai otomatis.')
            ->modalSubmitActionLabel('Minta konfirmasi')
            ->action(fn (TindakLanjutPengaduan $tindakLanjut) => $this->jalankan(
                fn (User $pic) => $tindakLanjut->mintaKonfirmasi($this->record, $pic),
                'Konfirmasi pelapor diminta.',
            ));
    }

    /**
     * Seperti H-11: record dimuat ulang di tempat agar seluruh isi halaman langsung diperbarui.
     *
     * @param  Closure(User): mixed  $aksi
     */
    private function jalankan(Closure $aksi, string $pesanBerhasil): void
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        try {
            $aksi($user);
        } catch (AksiPengaduanTidakSah|BerkasDitolak $galat) {
            Notification::make()->danger()->title($galat->getMessage())->send();

            return;
        } finally {
            $this->record->refresh()->load(PengaduanInfolist::RELASI);
        }

        Notification::make()->success()->title($pesanBerhasil)->send();
    }

    private function disposisiSaya(): ?Disposisi
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? TindakLanjutPengaduan::disposisiUnit($this->record, $user) : null;
    }

    /**
     * @return list<string>
     */
    private static function mimeBukti(): array
    {
        return array_values(array_merge(...array_map(
            fn (JenisBerkas $jenis): array => $jenis->daftarMime(),
            array_filter(JenisBerkas::cases(), fn (JenisBerkas $jenis): bool => $jenis->bolehUntuk(JenisLaporan::Pengaduan)),
        )));
    }
}
