<?php

namespace App\Filament\Resources\HasilWbs\Pages;

use App\Enums\AksiAudit;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Filament\Resources\HasilWbs\HasilWbsResource;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\User;
use App\Services\Wbs\PenangananWbs;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Detail hasil WBS untuk penerima hasil (H-16). Setiap pembukaan dicatat sebagai membuka_hasil_wbs; setiap keputusan
 * (perpanjangan, tindak lanjut) dijalankan PenangananWbs yang memeriksa ulang hak akses dan status di server.
 *
 * @property Laporan $record
 */
class ViewHasilWbs extends ViewRecord
{
    protected static string $resource = HasilWbsResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $user = Filament::auth()->user();

        LogAudit::catat($user instanceof User ? $user : null, AksiAudit::MembukaHasilWbs, $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setujuiPerpanjangan')
                ->label('Setujui perpanjangan')
                ->icon(Heroicon::OutlinedCheck)
                ->color('success')
                ->authorize('putuskanHasilWbs')
                ->visible(fn (Laporan $record): bool => PenangananWbs::bisa('putuskanPerpanjangan', $record))
                ->requiresConfirmation()
                ->modalDescription('Investigasi diperpanjang 30 hari kerja dari batas semula. Persetujuan Anda tercatat.')
                ->action(fn (PenangananWbs $penanganan) => $this->jalankan(
                    fn (User $user) => $penanganan->setujuiPerpanjangan($this->record, $user),
                    'Perpanjangan disetujui.',
                )),
            Action::make('tolakPerpanjangan')
                ->label('Tolak perpanjangan')
                ->icon(Heroicon::OutlinedXMark)
                ->color('danger')
                ->authorize('putuskanHasilWbs')
                ->visible(fn (Laporan $record): bool => PenangananWbs::bisa('putuskanPerpanjangan', $record))
                ->schema([
                    Textarea::make('alasan')->label('Alasan')->required()->maxLength(PenangananWbs::MAKS_CATATAN)->rows(3),
                ])
                ->action(fn (array $data, PenangananWbs $penanganan) => $this->jalankan(
                    fn (User $user) => $penanganan->tolakPerpanjangan($this->record, $user, (string) $data['alasan']),
                    'Perpanjangan ditolak.',
                )),
            Action::make('tandaiDitindaklanjuti')
                ->label('Tandai sudah ditindaklanjuti')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('primary')
                ->authorize('putuskanHasilWbs')
                ->visible(fn (Laporan $record): bool => PenangananWbs::bisa('tandaiDitindaklanjuti', $record))
                ->schema([
                    Textarea::make('catatan')->label('Catatan tindak lanjut')->required()->maxLength(PenangananWbs::MAKS_CATATAN)->rows(3),
                ])
                ->action(fn (array $data, PenangananWbs $penanganan) => $this->jalankan(
                    fn (User $user) => $penanganan->tandaiDitindaklanjuti($this->record, $user, (string) $data['catatan']),
                    'Hasil ditandai sudah ditindaklanjuti.',
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
            $this->record->refresh()->load(['unit', 'kategori', 'terlapor']);
        }

        Notification::make()->success()->title($pesanBerhasil)->send();
    }
}
