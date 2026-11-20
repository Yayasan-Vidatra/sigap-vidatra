<?php

namespace App\Filament\Resources\LaporanDialihkan;

use App\Filament\Resources\LaporanDialihkan\Pages\ListLaporanDialihkan;
use App\Filament\Resources\LaporanDialihkan\Pages\ViewLaporanDialihkan;
use App\Filament\Support\TabelPanel;
use App\Models\Pengalihan;
use App\Models\User;
use App\Services\WaktuLokal;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * H-17 Laporan Dialihkan (US-19, US-20; UC-19; F-18) untuk Kepala Sekolah dan Komite Etik. Hanya ringkasan pengalihan
 * (terenkripsi) yang ditampilkan: tanpa identitas pelapor, kode lacak, isi laporan, atau lampiran. Lingkup data
 * ditegakkan di query dan di PengalihanPolicy; Kepala Sekolah tidak pernah melihat laporan yang menyangkut dirinya.
 */
class LaporanDialihkanResource extends Resource
{
    protected static ?string $model = Pengalihan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static ?string $navigationLabel = 'Laporan Dialihkan';

    protected static ?string $modelLabel = 'laporan dialihkan';

    protected static ?string $pluralModelLabel = 'laporan dialihkan';

    protected static ?string $slug = 'dialihkan';

    protected static bool $isGloballySearchable = false;

    /**
     * @return Builder<Pengalihan>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        $id = Pengalihan::query()
            ->with('laporan.terlapor')
            ->get()
            ->filter(fn (Pengalihan $pengalihan): bool => $user->can('view', $pengalihan))
            ->modelKeys();

        return parent::getEloquentQuery()->with(['laporan.kategori', 'laporan.unit', 'unitTujuan'])->whereKey($id);
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Pengalihan ? "Pengalihan #{$record->getKey()}" : 'Laporan dialihkan';
    }

    public static function table(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('id')->label('No.')->formatStateUsing(fn (int $state): string => "#{$state}")->width('80px'),
                TextColumn::make('laporan.jenis')->label('Kanal')->width('120px'),
                TextColumn::make('laporan.kategori.nama')->label('Kategori')->placeholder('–')->lineClamp(2)->wrap(),
                TextColumn::make('unitTujuan.nama')->label('Unit tujuan')->placeholder('–')->wrap()->width('150px'),
                TextColumn::make('dibuat_pada')->label('Dialihkan')->dateTime('j M Y H:i')->suffix(' '.WaktuLokal::label())->sortable()->wrap()->width('130px'),
                TextColumn::make('diterima_pada')->label('Diterima')->dateTime('j M Y H:i')->placeholder('Belum dikonfirmasi')->wrap()->width('140px'),
                TabelPanel::kolomPanah(),
            ])
            ->defaultSort('dibuat_pada', 'desc')
            ->filters([
                // Sama dengan kartu pengalihan di Dasbor; hanya mempersempit pengalihan yang boleh dilihat (PengalihanPolicy).
                TernaryFilter::make('dikonfirmasi')
                    ->label('Konfirmasi terima')
                    ->placeholder('Semua')
                    ->trueLabel('Sudah dikonfirmasi')
                    ->falseLabel('Belum dikonfirmasi')
                    ->nullable()
                    ->attribute('diterima_pada'),
            ])
            ->recordActions([])
            ->toolbarActions([]);

        return TabelPanel::terapkan($table, 'ringkas', 'laporan dialihkan');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Pengalihan')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('tujuan')->label('Tujuan'),
                        TextEntry::make('unitTujuan.nama')->label('Unit tujuan')->placeholder('-'),
                        TextEntry::make('laporan.jenis')->label('Kanal asal'),
                        TextEntry::make('laporan.kategori.nama')->label('Kategori')->placeholder('-'),
                        TextEntry::make('dibuat_pada')->label('Dialihkan')->dateTime('j M Y H:i')->suffix(' '.WaktuLokal::label()),
                        TextEntry::make('diterima_pada')->label('Diterima')->dateTime('j M Y H:i')->suffix(' '.WaktuLokal::label())->placeholder('Belum dikonfirmasi'),
                        TextEntry::make('ringkasan')->label('Ringkasan')->prose()->columnSpanFull(),
                        TextEntry::make('catatan_tindak_lanjut')->label('Catatan tindak lanjut')->placeholder('Belum ada')->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLaporanDialihkan::route('/'),
            'view' => ViewLaporanDialihkan::route('/{record}'),
        ];
    }
}
