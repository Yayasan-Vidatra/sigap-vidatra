<?php

namespace App\Filament\Concerns;

use App\Enums\TujuanPengalihan;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengalihan\PengalihanLaporan;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Tombol "Alihkan" (UC-19) untuk Kotak Masuk Pengaduan (H-11) dan Ruang Kerja WBS (H-13). Hanya tampil pada status yang
 * belum akhir; hak akses dan status diperiksa ulang di PengalihanLaporan. Halaman pemakai menyediakan jalankan().
 *
 * @property Laporan $record
 */
trait MengalihkanLaporan
{
    /**
     * @param  Closure(Closure(User): mixed, string): void  $jalankan
     */
    protected function aksiAlihkan(Closure $jalankan): Action
    {
        return Action::make('alihkan')
            ->label('Alihkan')
            ->icon(Heroicon::OutlinedArrowUturnRight)
            ->color('warning')
            ->authorize(fn (Laporan $record): bool => Gate::allows($record->isWbs() ? 'telaahLaporanWbs' : 'kelolaPengaduan', $record))
            ->visible(fn (Laporan $record): bool => PengalihanLaporan::bisa($record))
            ->modalHeading('Alihkan laporan')
            ->modalDescription('Kekerasan di sekolah ke Kepala Sekolah unit terkait; kode etik atau kepegawaian ke Komite Etik. Penerima hanya membaca ringkasan ini.')
            ->fillForm(fn (Laporan $record): array => ['unit_tujuan_id' => $record->unit_id, 'kepala_sekolah_terlibat' => false])
            ->schema([
                Select::make('tujuan')
                    ->label('Tujuan')
                    ->options(fn (Laporan $record): array => collect(PengalihanLaporan::tujuanUntuk($record))
                        ->mapWithKeys(fn (TujuanPengalihan $tujuan): array => [$tujuan->value => $tujuan->getLabel()])
                        ->all())
                    ->required()
                    ->live(),
                Select::make('unit_tujuan_id')
                    ->label('Unit Kepala Sekolah')
                    ->options(fn (): array => Unit::query()->where('aktif', true)->orderBy('id')->pluck('nama', 'id')->all())
                    ->visible(fn (Get $get): bool => $get('tujuan') === TujuanPengalihan::KepalaSekolah->value)
                    ->required(fn (Get $get): bool => $get('tujuan') === TujuanPengalihan::KepalaSekolah->value),
                Toggle::make('kepala_sekolah_terlibat')
                    ->label('Kepala Sekolah unit ini termasuk yang dilaporkan')
                    ->helperText('Bila ya, pengalihan ke Kepala Sekolah ditolak; Tim WBS merujuk ke Pokja BSAN.'),
                Textarea::make('ringkasan')
                    ->label('Ringkasan untuk penerima')
                    ->helperText('Tanpa identitas pelapor, kecuali pelapor menyetujui secara tertulis atau diperlukan untuk keselamatan siswa. Disimpan terenkripsi.')
                    ->required()
                    ->maxLength(PengalihanLaporan::MAKS_RINGKASAN)
                    ->rows(5),
            ])
            ->action(fn (array $data, PengalihanLaporan $layanan) => $jalankan(
                fn (User $oleh) => $layanan->alihkan(
                    $this->record,
                    $oleh,
                    TujuanPengalihan::from($data['tujuan']),
                    filled($data['unit_tujuan_id'] ?? null) ? Unit::query()->find($data['unit_tujuan_id']) : null,
                    (string) $data['ringkasan'],
                    (bool) ($data['kepala_sekolah_terlibat'] ?? false),
                ),
                'Laporan dialihkan.',
            ));
    }
}
