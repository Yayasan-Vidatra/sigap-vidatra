<?php

namespace App\Filament\Resources\LaporanWbs\Schemas;

use App\Enums\KedudukanTerlapor;
use App\Enums\StatusLaporan;
use App\Filament\Resources\LaporanWbs\Tables\LaporanWbsTable;
use App\Filament\Resources\Pengaduan\Schemas\PengaduanInfolist;
use App\Models\Laporan;
use App\Services\WaktuLokal;
use App\Services\Wbs\AksesWbs;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Isi halaman detail laporan WBS (H-13). Isi laporan, terlapor, rekomendasi, catatan riwayat, dan catatan internal
 * didekripsi hanya di halaman ini, setelah akses diperiksa dan pembukaannya dicatat di log audit. Identitas pelapor
 * tidak ditampilkan di sini, melainkan lewat tombol yang hanya ada bagi penelaah utama dan tercatat.
 */
class LaporanWbsInfolist
{
    /**
     * @var list<string>
     */
    public const RELASI = [
        'unit', 'kategori', 'investigator', 'terlapor', 'lampiranPelapor', 'riwayatStatus.oleh', 'pesan.pengirimPetugas', 'pesan.lampiran',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                self::ringkasan(),
                self::isi(),
                self::terlapor(),
                PengaduanInfolist::lampiran('lampiranPelapor', 'Bukti dari pelapor', 'Tidak ada lampiran.'),
                self::kesimpulan(),
                PengaduanInfolist::pesan(),
                PengaduanInfolist::riwayat(denganCatatanPetugas: true),
            ]);
    }

    private static function ringkasan(): Section
    {
        return Section::make('Ringkasan')
            ->columns(3)
            ->schema([
                TextEntry::make('kode_lacak')->label('Kode lacak')->copyable(),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (StatusLaporan $state): string => LaporanWbsTable::warnaStatus($state)),
                TextEntry::make('dibuat_pada')->label('Masuk')->dateTime('d M Y H:i')->suffix(' '.WaktuLokal::label()),
                TextEntry::make('kategori.nama')->label('Jenis pelanggaran'),
                TextEntry::make('unit.nama')->label('Unit')->placeholder('-'),
                TextEntry::make('sumber')->label('Sumber'),
                TextEntry::make('kedudukan_terlapor')->label('Kedudukan terlapor (pilihan/koreksi)')->badge()->placeholder('Tidak tahu'),
                TextEntry::make('kedudukan_efektif')
                    ->label('Perutean menurut matriks')
                    ->state(fn (Laporan $record): string => AksesWbs::kedudukanEfektif($record)->getLabel())
                    ->helperText(fn (Laporan $record): ?string => AksesWbs::kedudukanEfektif($record) !== ($record->kedudukan_terlapor ?? KedudukanTerlapor::TidakTahu)
                        ? 'Dinaikkan karena nama pejabat disebut sebagai terlapor.'
                        : null),
                TextEntry::make('penerima_hasil')
                    ->label('Penerima hasil')
                    ->state(fn (Laporan $record): string => AksesWbs::kedudukanEfektif($record)->penerimaHasil()),
                TextEntry::make('investigator.name')->label('Investigator')->placeholder('Belum ditunjuk'),
                TextEntry::make('perpanjangan')
                    ->label('Perpanjangan')
                    ->state(fn (Laporan $record): ?string => match (true) {
                        $record->perpanjangan_diajukan_pada !== null => 'Menunggu persetujuan penerima hasil',
                        $record->perpanjangan_disetujui_oleh !== null => 'Disetujui penerima hasil',
                        default => null,
                    })
                    ->placeholder('-'),
                TextEntry::make('batas_waktu')
                    ->label('Batas waktu tahap ini')
                    ->date('d M Y')
                    ->placeholder('-')
                    ->color(fn (Laporan $record): ?string => LaporanWbsTable::lewatBatas($record) ? 'danger' : null),
            ]);
    }

    private static function isi(): Section
    {
        return Section::make('Isi laporan')
            ->columns(2)
            ->schema([
                TextEntry::make('judul')->label('Judul')->columnSpanFull(),
                TextEntry::make('kronologi')->label('Kronologi')->columnSpanFull()->prose(),
                TextEntry::make('waktu_kejadian')->label('Waktu kejadian')->dateTime('d M Y H:i')->suffix(' '.WaktuLokal::label())->placeholder('-'),
                TextEntry::make('lokasi')->label('Lokasi')->placeholder('-'),
                TextEntry::make('saksi')->label('Pihak lain yang mengetahui')->placeholder('-')->columnSpanFull(),
            ]);
    }

    private static function terlapor(): Section
    {
        return Section::make('Pihak terlapor')
            ->schema([
                RepeatableEntry::make('terlapor')
                    ->hiddenLabel()
                    ->placeholder('Tidak ada.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('nama')->label('Nama'),
                        TextEntry::make('jabatan')->label('Jabatan')->placeholder('-'),
                    ]),
            ]);
    }

    private static function kesimpulan(): Section
    {
        return Section::make('Kesimpulan dan rekomendasi')
            ->visible(fn (Laporan $record): bool => $record->kesimpulan !== null)
            ->columns(1)
            ->schema([
                TextEntry::make('kesimpulan')->label('Kesimpulan')->badge(),
                TextEntry::make('rekomendasi')->label('Rekomendasi')->prose(),
            ]);
    }
}
