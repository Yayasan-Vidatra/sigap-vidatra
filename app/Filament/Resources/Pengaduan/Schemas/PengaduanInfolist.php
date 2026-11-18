<?php

namespace App\Filament\Resources\Pengaduan\Schemas;

use App\Enums\StatusLaporan;
use App\Enums\StatusPindai;
use App\Filament\Resources\Pengaduan\Tables\PengaduanTable;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\RiwayatStatus;
use App\Services\WaktuLokal;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

/**
 * Isi halaman detail pengaduan untuk Admin (H-11). Bagiannya dipakai juga oleh Pengaduan Unit Saya (H-12).
 * Identitas pelapor tidak ditampilkan di sini, melainkan lewat tombol "Lihat identitas pelapor" yang tercatat
 * di log audit. Lampiran diunduh lewat tautan sementara 10 menit; lampiran yang dikarantina tidak diberi
 * tautan (dan unduhannya ditolak di server).
 */
class PengaduanInfolist
{
    /**
     * Relasi yang dimuat ulang setelah aksi agar seluruh isi halaman langsung diperbarui.
     *
     * @var list<string>
     */
    public const RELASI = [
        'unit', 'kategori', 'lampiranPelapor', 'buktiPenyelesaian', 'disposisi.unit', 'disposisi.pic', 'riwayatStatus.oleh',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                self::ringkasan(),
                self::isi(),
                self::lampiran('lampiranPelapor', 'Lampiran pelapor', 'Tidak ada lampiran.'),
                self::disposisi(),
                self::lampiran('buktiPenyelesaian', 'Bukti penyelesaian dari unit', 'Belum ada bukti penyelesaian.'),
                self::riwayat(denganCatatanPetugas: true),
            ]);
    }

    public static function ringkasan(): Section
    {
        return Section::make('Ringkasan')
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
            ]);
    }

    public static function isi(): Section
    {
        return Section::make('Isi pengaduan')
            ->columns(3)
            ->schema([
                TextEntry::make('judul')->label('Judul')->columnSpanFull(),
                TextEntry::make('kronologi')->label('Kronologi')->columnSpanFull()->prose(),
                TextEntry::make('waktu_kejadian')->label('Waktu kejadian')->dateTime('d M Y H:i')->suffix(' '.WaktuLokal::label())->placeholder('-'),
                TextEntry::make('lokasi')->label('Lokasi')->placeholder('-'),
                TextEntry::make('jabatan_pihak_dikeluhkan')->label('Jabatan pihak yang dikeluhkan')->placeholder('-'),
            ]);
    }

    public static function lampiran(string $relasi, string $judul, string $kosong): Section
    {
        return Section::make($judul)
            ->description('Tautan unduh berlaku 10 menit sejak halaman dibuka. Setiap unduhan tercatat di log audit.')
            ->schema([
                RepeatableEntry::make($relasi)
                    ->hiddenLabel()
                    ->placeholder($kosong)
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
            ]);
    }

    public static function disposisi(): Section
    {
        return Section::make('Disposisi dan tindak lanjut')
            ->schema([
                RepeatableEntry::make('disposisi')
                    ->hiddenLabel()
                    ->placeholder('Belum didisposisikan.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('unit.nama')->label('Unit'),
                        TextEntry::make('pic.name')->label('PIC'),
                        TextEntry::make('dibuat_pada')->label('Didisposisikan')->dateTime('d M Y H:i')->suffix(' '.WaktuLokal::label()),
                        TextEntry::make('instruksi')->label('Instruksi')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('tindak_lanjut')->label('Tindak lanjut unit')->placeholder('Belum diisi.')->columnSpanFull(),
                        TextEntry::make('selesai_pada')
                            ->label('Konfirmasi diminta')
                            ->dateTime('d M Y H:i')
                            ->suffix(' '.WaktuLokal::label())
                            ->placeholder('-'),
                    ]),
            ]);
    }

    /**
     * @param  bool  $denganCatatanPetugas  false untuk PIC Unit: hanya catatan yang juga boleh dibaca pelapor
     */
    public static function riwayat(bool $denganCatatanPetugas): Section
    {
        return Section::make('Riwayat status')
            ->schema([
                RepeatableEntry::make('riwayatStatus')
                    ->hiddenLabel()
                    ->columns($denganCatatanPetugas ? 5 : 4)
                    ->schema(array_values(array_filter([
                        TextEntry::make('waktu')->label('Waktu')->dateTime('d M Y H:i')->suffix(' '.WaktuLokal::label()),
                        TextEntry::make('status_baru')->label('Status')->badge(),
                        TextEntry::make('oleh.name')->label('Oleh')->placeholder('Sistem / pelapor'),
                        TextEntry::make('catatan')
                            ->label('Catatan')
                            ->state(fn (RiwayatStatus $record): ?string => $denganCatatanPetugas || $record->tampil_ke_pelapor ? $record->catatan : null)
                            ->placeholder('-'),
                        $denganCatatanPetugas
                            ? TextEntry::make('tampil_ke_pelapor')
                                ->label('Catatan terlihat pelapor')
                                ->formatStateUsing(fn (bool $state): string => $state ? 'Ya' : 'Tidak, hanya petugas')
                            : null,
                    ]))),
            ]);
    }

    private static function bisaDiunduh(Lampiran $lampiran): bool
    {
        return $lampiran->status_pindai !== StatusPindai::Karantina;
    }
}
