<?php

namespace App\Filament\Resources\Pengaduan\Schemas;

use App\Enums\StatusLaporan;
use App\Enums\StatusPindai;
use App\Filament\Resources\Pengaduan\Tables\PengaduanTable;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Services\WaktuLokal;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

/**
 * Isi halaman detail pengaduan. Identitas pelapor tidak ditampilkan di sini, melainkan lewat tombol
 * "Lihat identitas pelapor" yang tercatat di log audit. Lampiran diunduh lewat tautan sementara 10 menit;
 * lampiran yang dikarantina tidak diberi tautan (dan unduhannya ditolak di server).
 */
class PengaduanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Ringkasan')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('kode_lacak')->label('Kode lacak')->copyable(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (StatusLaporan $state): string => PengaduanTable::warnaStatus($state)),
                        TextEntry::make('dibuat_pada')->label('Masuk')->dateTime('d M Y H:i')->suffix(' '.WaktuLokal::label()),
                        TextEntry::make('unit.nama')->label('Unit'),
                        TextEntry::make('kategori.nama')->label('Kategori')->placeholder('Belum ada'),
                        TextEntry::make('jenis_pelapor')->label('Jenis pelapor'),
                        TextEntry::make('sumber')->label('Sumber'),
                        TextEntry::make('prioritas')->label('Prioritas')->badge()->placeholder('-'),
                        TextEntry::make('batas_waktu')
                            ->label('Batas waktu selesai')
                            ->date('d M Y')
                            ->placeholder('Dihitung saat disposisi')
                            ->color(fn (Laporan $record): ?string => PengaduanTable::lewatBatas($record) ? 'danger' : null),
                    ]),
                Section::make('Isi pengaduan')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('judul')->label('Judul')->columnSpanFull(),
                        TextEntry::make('kronologi')->label('Kronologi')->columnSpanFull()->prose(),
                        TextEntry::make('waktu_kejadian')->label('Waktu kejadian')->dateTime('d M Y H:i')->suffix(' '.WaktuLokal::label())->placeholder('-'),
                        TextEntry::make('lokasi')->label('Lokasi')->placeholder('-'),
                        TextEntry::make('jabatan_pihak_dikeluhkan')->label('Jabatan pihak yang dikeluhkan')->placeholder('-'),
                    ]),
                Section::make('Lampiran')
                    ->description('Tautan unduh berlaku 10 menit sejak halaman dibuka. Setiap unduhan tercatat di log audit.')
                    ->schema([
                        RepeatableEntry::make('lampiran')
                            ->hiddenLabel()
                            ->placeholder('Tidak ada lampiran.')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('nama_asli')->label('Berkas'),
                                TextEntry::make('ukuran')
                                    ->label('Ukuran')
                                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state, precision: 1)),
                                TextEntry::make('status_pindai')
                                    ->label('Pemindaian')
                                    ->badge()
                                    ->color(fn (StatusPindai $state): string => match ($state) {
                                        StatusPindai::Bersih => 'success',
                                        StatusPindai::Karantina, StatusPindai::Gagal => 'danger',
                                        default => 'warning',
                                    }),
                                TextEntry::make('unduh')
                                    ->label('Unduh')
                                    ->state(fn (Lampiran $record): string => self::bisaDiunduh($record)
                                        ? 'Unduh'
                                        : 'Dikarantina, tidak bisa diunduh')
                                    ->url(fn (Lampiran $record): ?string => self::bisaDiunduh($record) ? $record->tautanUnduhSementara() : null)
                                    ->color(fn (Lampiran $record): string => self::bisaDiunduh($record) ? 'primary' : 'danger'),
                            ]),
                    ]),
                Section::make('Disposisi')
                    ->schema([
                        RepeatableEntry::make('disposisi')
                            ->hiddenLabel()
                            ->placeholder('Belum didisposisikan.')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('unit.nama')->label('Unit'),
                                TextEntry::make('pic.name')->label('PIC'),
                                TextEntry::make('dibuat_pada')->label('Waktu')->dateTime('d M Y H:i')->suffix(' '.WaktuLokal::label()),
                                TextEntry::make('instruksi')->label('Instruksi')->placeholder('-'),
                            ]),
                    ]),
                Section::make('Riwayat status')
                    ->schema([
                        RepeatableEntry::make('riwayatStatus')
                            ->hiddenLabel()
                            ->columns(5)
                            ->schema([
                                TextEntry::make('waktu')->label('Waktu')->dateTime('d M Y H:i')->suffix(' '.WaktuLokal::label()),
                                TextEntry::make('status_baru')->label('Status')->badge(),
                                TextEntry::make('oleh.name')->label('Oleh')->placeholder('Sistem / pelapor'),
                                TextEntry::make('catatan')->label('Catatan')->placeholder('-'),
                                TextEntry::make('tampil_ke_pelapor')
                                    ->label('Catatan terlihat pelapor')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ya' : 'Tidak, hanya petugas'),
                            ]),
                    ]),
            ]);
    }

    private static function bisaDiunduh(Lampiran $lampiran): bool
    {
        return $lampiran->status_pindai !== StatusPindai::Karantina;
    }
}
