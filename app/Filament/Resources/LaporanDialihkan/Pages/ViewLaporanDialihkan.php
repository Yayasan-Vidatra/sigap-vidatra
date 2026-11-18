<?php

namespace App\Filament\Resources\LaporanDialihkan\Pages;

use App\Enums\AksiAudit;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Filament\Resources\LaporanDialihkan\LaporanDialihkanResource;
use App\Models\LogAudit;
use App\Models\Pengalihan;
use App\Models\User;
use App\Services\Pengalihan\PengalihanLaporan;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Detail pengalihan (H-17). Setiap pembukaan dicatat sebagai membuka_pengalihan; konfirmasi terima dan tindak lanjut
 * dijalankan PengalihanLaporan yang memeriksa ulang hak akses di server.
 *
 * @property Pengalihan $record
 */
class ViewLaporanDialihkan extends ViewRecord
{
    protected static string $resource = LaporanDialihkanResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $user = Filament::auth()->user();
        LogAudit::catat($user instanceof User ? $user : null, AksiAudit::MembukaPengalihan, $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('konfirmasiTerima')
                ->label('Konfirmasi terima')
                ->icon(Heroicon::OutlinedCheck)
                ->color('success')
                ->visible(fn (Pengalihan $record): bool => $record->diterima_pada === null)
                ->requiresConfirmation()
                ->action(fn (PengalihanLaporan $layanan) => $this->jalankan(
                    fn (User $user) => $layanan->konfirmasiTerima($this->record, $user),
                    'Penerimaan dikonfirmasi.',
                )),
            Action::make('catatTindakLanjut')
                ->label('Catat tindak lanjut')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('primary')
                ->visible(fn (Pengalihan $record): bool => $record->diterima_pada !== null)
                ->fillForm(fn (Pengalihan $record): array => ['catatan' => $record->catatan_tindak_lanjut])
                ->schema([
                    Textarea::make('catatan')->label('Catatan tindak lanjut')->required()->maxLength(PengalihanLaporan::MAKS_RINGKASAN)->rows(4),
                ])
                ->action(fn (array $data, PengalihanLaporan $layanan) => $this->jalankan(
                    fn (User $user) => $layanan->catatTindakLanjut($this->record, $user, (string) $data['catatan']),
                    'Tindak lanjut tersimpan.',
                )),
        ];
    }

    /**
     * @param  Closure(User): mixed  $aksi
     */
    private function jalankan(Closure $aksi, string $pesanBerhasil): void
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        try {
            $aksi($user);
        } catch (AksiPengaduanTidakSah $galat) {
            Notification::make()->danger()->title($galat->getMessage())->send();

            return;
        } finally {
            $this->record->refresh();
        }

        Notification::make()->success()->title($pesanBerhasil)->send();
    }
}
