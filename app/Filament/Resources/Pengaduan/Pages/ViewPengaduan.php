<?php

namespace App\Filament\Resources\Pengaduan\Pages;

use App\Enums\AksiAudit;
use App\Enums\JenisLaporan;
use App\Enums\Peran;
use App\Enums\Prioritas;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Filament\Concerns\MencatatPembukaanLaporan;
use App\Filament\Concerns\MengalihkanLaporan;
use App\Filament\Resources\Pengaduan\PengaduanResource;
use App\Filament\Resources\Pengaduan\Schemas\PengaduanInfolist;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengaduan\PenangananPengaduan;
use App\Services\Pengaduan\PesanLaporan;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

/**
 * Detail pengaduan di Kotak Masuk (H-11). Setiap pembukaan halaman dicatat di log audit (US-27).
 *
 * Aksi mengikuti alur Rancangan r1 Bagian 6 dan dijalankan lewat PenangananPengaduan, yang memeriksa
 * ulang hak akses dan status di server. Pengalihan ke Kepala Sekolah/Komite Etik (UC-19) tampil hanya pada status
 * yang belum akhir. Pindah ke WBS (UC-09 1a) belum dikerjakan; tombolnya disembunyikan.
 *
 * @property Laporan $record
 */
class ViewPengaduan extends ViewRecord
{
    use MencatatPembukaanLaporan;
    use MengalihkanLaporan;

    protected static string $resource = PengaduanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->aksiVerifikasi(),
            $this->aksiDisposisi(),
            $this->aksiTolak(),
            $this->aksiTutupLewatTelepon(),
            $this->aksiKirimPesan(),
            $this->aksiLihatIdentitas(),
            $this->aksiAlihkan(fn (Closure $aksi, string $pesan) => $this->jalankan($aksi, $pesan)),
            // Pindah ke WBS (UC-09 1a) belum dijadwalkan; tombolnya disembunyikan sampai fiturnya ada.
            Action::make('pindahKeWbs')
                ->label('Pindah ke WBS')
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->hidden(),
        ];
    }

    private function aksiVerifikasi(): Action
    {
        return Action::make('verifikasi')
            ->label('Verifikasi')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->authorize('kelolaPengaduan')
            ->visible(fn (Laporan $record): bool => PenangananPengaduan::bisa('verifikasi', $record))
            ->modalHeading('Verifikasi dan kategorikan pengaduan')
            ->modalDescription('Pengaduan yang valid berstatus Terverifikasi dan siap didisposisikan ke unit.')
            ->modalSubmitActionLabel('Verifikasi')
            ->fillForm(fn (Laporan $record): array => ['kategori_id' => $record->kategori_id])
            ->schema([
                Select::make('kategori_id')
                    ->label('Kategori')
                    ->options(fn (): array => Kategori::query()
                        ->where('jenis', JenisLaporan::Pengaduan->value)
                        ->orderBy('nama')
                        ->pluck('nama', 'id')
                        ->all())
                    ->required(),
            ])
            ->action(fn (array $data, PenangananPengaduan $penanganan) => $this->jalankan(
                fn (User $oleh) => $penanganan->verifikasi($this->record, $oleh, Kategori::query()->findOrFail($data['kategori_id'])),
                'Pengaduan terverifikasi.',
            ));
    }

    /**
     * Pesan ke pelapor (UC-05) atau catatan internal yang tidak terlihat pelapor.
     */
    private function aksiKirimPesan(): Action
    {
        return Action::make('kirimPesan')
            ->label('Kirim pesan')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('gray')
            ->authorize('kelolaPengaduan')
            ->modalHeading('Kirim pesan ke pelapor')
            ->modalDescription('Pelapor membaca pesan di halaman Lacak Laporan. Jangan memuat identitas pihak lain.')
            ->modalSubmitActionLabel('Kirim')
            ->schema([
                Textarea::make('isi')
                    ->label('Pesan')
                    ->required()
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (trim((string) $value) === '') {
                            $fail('Isi pesan wajib diisi.');
                        }
                    })
                    ->maxLength(PesanLaporan::MAKS_ISI)
                    ->rows(4),
                Toggle::make('internal')
                    ->label('Catatan internal (tidak terlihat pelapor)')
                    ->default(false),
            ])
            ->action(fn (array $data, PesanLaporan $pesan) => $this->jalankan(
                fn (User $oleh) => $pesan->kirimDariPetugas($this->record, $oleh, (string) $data['isi'], (bool) ($data['internal'] ?? false)),
                ($data['internal'] ?? false) ? 'Catatan internal tersimpan.' : 'Pesan terkirim ke pelapor.',
            ));
    }

    /**
     * Pengecualian penutupan oleh Admin (log deviasi): pelapor mengonfirmasi hasil lewat telepon. Catatannya
     * wajib dan hanya untuk petugas.
     */
    private function aksiTutupLewatTelepon(): Action
    {
        return Action::make('tutupLewatTelepon')
            ->label('Tutup (konfirmasi lewat telepon)')
            ->icon(Heroicon::OutlinedPhone)
            ->color('success')
            ->authorize('kelolaPengaduan')
            ->visible(fn (Laporan $record): bool => PenangananPengaduan::bisa('tutup', $record))
            ->modalHeading('Tutup pengaduan')
            ->modalDescription('Gunakan hanya bila pelapor sudah mengonfirmasi hasil lewat telepon. Catatan ini hanya terlihat petugas.')
            ->modalSubmitActionLabel('Tutup pengaduan')
            ->schema([
                Textarea::make('catatan')
                    ->label('Catatan penutupan')
                    ->helperText('Mis. kapan dan dengan siapa konfirmasi lewat telepon dilakukan.')
                    ->required()
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (trim((string) $value) === '') {
                            $fail('Catatan penutupan wajib diisi.');
                        }
                    })
                    ->maxLength(2000)
                    ->rows(3),
            ])
            ->action(fn (array $data, PenangananPengaduan $penanganan) => $this->jalankan(
                fn (User $oleh) => $penanganan->tutupLewatTelepon($this->record, $oleh, $data['catatan']),
                'Pengaduan ditutup.',
            ));
    }

    private function aksiTolak(): Action
    {
        return Action::make('tolak')
            ->label('Tolak')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('kelolaPengaduan')
            ->visible(fn (Laporan $record): bool => PenangananPengaduan::bisa('tolak', $record))
            ->modalHeading('Tolak pengaduan')
            ->modalDescription('Alasan penolakan akan terlihat oleh pelapor saat melacak laporannya.')
            ->modalSubmitActionLabel('Tolak pengaduan')
            ->schema([
                Textarea::make('alasan')
                    ->label('Alasan penolakan')
                    ->required()
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (trim((string) $value) === '') {
                            $fail('Alasan penolakan wajib diisi.');
                        }
                    })
                    ->maxLength(2000)
                    ->rows(4),
            ])
            ->action(fn (array $data, PenangananPengaduan $penanganan) => $this->jalankan(
                fn (User $oleh) => $penanganan->tolak($this->record, $oleh, $data['alasan']),
                'Pengaduan ditolak.',
            ));
    }

    private function aksiDisposisi(): Action
    {
        return Action::make('disposisi')
            ->label('Disposisi')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->authorize('kelolaPengaduan')
            ->visible(fn (Laporan $record): bool => PenangananPengaduan::bisa('disposisi', $record))
            ->modalHeading('Disposisi ke unit')
            ->modalDescription('Batas waktu dihitung otomatis dari SLA kategori dalam hari kerja (Senin–Jumat).')
            ->modalSubmitActionLabel('Disposisikan')
            ->fillForm(fn (Laporan $record): array => [
                'unit_id' => $record->unit_id,
                'prioritas' => Prioritas::Sedang->value,
            ])
            ->schema([
                Select::make('unit_id')
                    ->label('Unit tujuan')
                    ->options(fn (): array => $this->pilihanUnit())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('pic_user_id', null)),
                Select::make('pic_user_id')
                    ->label('PIC Unit')
                    ->options(fn (Get $get): array => $this->pilihanPic($get('unit_id')))
                    ->required()
                    ->helperText('Hanya PIC Unit pada unit tujuan yang tidak terkunci.'),
                Select::make('prioritas')
                    ->label('Prioritas')
                    ->options(Prioritas::class)
                    ->required(),
                Textarea::make('instruksi')
                    ->label('Catatan instruksi untuk PIC')
                    ->maxLength(2000)
                    ->rows(4),
            ])
            ->action(fn (array $data, PenangananPengaduan $penanganan) => $this->jalankan(
                fn (User $oleh) => $penanganan->disposisi(
                    $this->record,
                    $oleh,
                    Unit::query()->findOrFail($data['unit_id']),
                    User::query()->findOrFail($data['pic_user_id']),
                    Prioritas::from($data['prioritas'] instanceof Prioritas ? $data['prioritas']->value : $data['prioritas']),
                    $data['instruksi'] ?? null,
                ),
                'Pengaduan didisposisikan ke unit.',
            ));
    }

    /**
     * Identitas pelapor dibuka lewat tombol agar setiap pembukaan tercatat di log audit dan identitas
     * tidak tampil ke orang di sekitar layar setiap kali halaman dibuka.
     */
    private function aksiLihatIdentitas(): Action
    {
        return Action::make('lihatIdentitas')
            ->label('Lihat identitas pelapor')
            ->icon(Heroicon::OutlinedIdentification)
            ->color('gray')
            ->authorize('lihatIdentitasPelapor')
            ->visible(fn (Laporan $record): bool => $record->identitasPelapor !== null)
            ->modalHeading('Identitas pelapor')
            ->modalDescription('Gunakan hanya untuk menghubungi pelapor. Pembukaan ini tercatat di log audit.')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->mountUsing(function (Laporan $record): void {
                $user = Filament::auth()->user();

                LogAudit::catat($user instanceof User ? $user : null, AksiAudit::MembukaIdentitasPelapor, $record->identitasPelapor);
            })
            ->schema([
                TextEntry::make('identitasPelapor.nama')->label('Nama'),
                TextEntry::make('identitasPelapor.email')->label('Surel')->placeholder('-'),
                TextEntry::make('identitasPelapor.no_hp')->label('Nomor HP/WhatsApp')->placeholder('-'),
                TextEntry::make('identitasPelapor.kontak_orang_tua')->label('Kontak orang tua/wali')->placeholder('-'),
                IconEntry::make('identitasPelapor.persetujuan_orang_tua')->label('Persetujuan orang tua/wali')->boolean(),
            ]);
    }

    /**
     * Menjalankan aksi lalu memuat ulang record yang sama beserta relasinya. Infolist yang sudah dibangun
     * memegang objek record ini, sehingga record harus diperbarui di tempat (bukan diganti objek baru) agar
     * seluruh isi halaman, termasuk Status di Ringkasan, langsung berubah tanpa muat ulang.
     *
     * @param  Closure(User): Laporan  $aksi
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
            $this->record->refresh()->load(PengaduanInfolist::RELASI);
        }

        Notification::make()->success()->title($pesanBerhasil)->send();
    }

    /**
     * @return array<int, string>
     */
    private function pilihanUnit(): array
    {
        $user = Filament::auth()->user();

        return Unit::query()
            ->where('aktif', true)
            ->when($user instanceof User && $user->isAdminSekolah(), fn ($query) => $query->whereKey($user->unit_id))
            ->orderBy('id')
            ->pluck('nama', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function pilihanPic(mixed $unitId): array
    {
        if (blank($unitId)) {
            return [];
        }

        return User::query()
            ->where('peran', Peran::PicUnit->value)
            ->where('unit_id', $unitId)
            ->whereNull('terkunci_pada')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
