<?php

namespace App\Filament\Resources\LaporanWbs\Pages;

use App\Enums\AksiAudit;
use App\Enums\KedudukanTerlapor;
use App\Enums\KesimpulanWbs;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Filament\Concerns\MencatatPembukaanLaporan;
use App\Filament\Concerns\MengalihkanLaporan;
use App\Filament\Resources\LaporanWbs\LaporanWbsResource;
use App\Filament\Resources\LaporanWbs\Schemas\LaporanWbsInfolist;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\User;
use App\Services\Pengaduan\PesanLaporan;
use App\Services\Wbs\AksesWbs;
use App\Services\Wbs\PemindahPengaduanKeWbs;
use App\Services\Wbs\PenangananWbs;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Detail laporan di Ruang Kerja WBS (H-13). Setiap pembukaan halaman dicatat di log audit (US-27).
 *
 * Aksi mengikuti Rancangan r1 Bagian 6 dan dijalankan lewat PenangananWbs atau PesanLaporan, yang memeriksa ulang
 * hak akses dan status di server. Bila koreksi kedudukan membuat laporan tertutup bagi pengguna ini, halaman
 * dialihkan ke daftar.
 *
 * @property Laporan $record
 */
class ViewLaporanWbs extends ViewRecord
{
    use MencatatPembukaanLaporan;
    use MengalihkanLaporan;

    protected static string $resource = LaporanWbsResource::class;

    /**
     * Kode lacak dan PIN sementara untuk pelapor pengaduan yang dipindah ke WBS, hanya di state halaman ini (tidak di
     * sesi), seperti hasil Catat Laporan Masuk (H-14).
     *
     * @var array{kode_lacak: string, pin: string}|null
     */
    public ?array $aksesPelapor = null;

    protected function getHeaderActions(): array
    {
        return [
            $this->aksiMulaiTelaah(),
            $this->aksiMulaiInvestigasi(),
            $this->aksiTidakDitindaklanjuti(),
            $this->aksiSimpulkan(),
            Action::make('ajukanPerpanjangan')
                ->label('Ajukan perpanjangan')
                ->icon(Heroicon::OutlinedClock)
                ->color('warning')
                ->authorize('simpulkanLaporanWbs')
                ->visible(fn (Laporan $record): bool => PenangananWbs::bisa('ajukanPerpanjangan', $record))
                ->modalDescription('Perpanjangan 30 hari kerja berlaku setelah disetujui penerima hasil menurut matriks. Penerima diberi tahu lewat surel tanpa isi laporan.')
                ->schema([
                    Textarea::make('alasan')->label('Alasan perpanjangan')->required()->rule($this->tidakKosong('Alasan perpanjangan'))->maxLength(PenangananWbs::MAKS_CATATAN)->rows(3),
                ])
                ->action(fn (array $data, PenangananWbs $penanganan) => $this->jalankan(
                    fn (User $oleh) => $penanganan->ajukanPerpanjangan($this->record, $oleh, (string) $data['alasan']),
                    'Perpanjangan diajukan kepada penerima hasil.',
                )),
            $this->aksiKoreksiKedudukan(),
            $this->aksiAlihkan(fn (Closure $aksi, string $pesan) => $this->jalankan($aksi, $pesan)),
            $this->aksiKirimPesan(),
            $this->aksiLihatIdentitas(),
            $this->aksiBuatAksesPelapor(),
        ];
    }

    /**
     * Laporan pindahan dari Pengaduan (UC-09 1a): pelapor belum punya PIN. Penelaah utama membuat PIN sementara,
     * melihatnya sekali, lalu menyampaikannya kepada pelapor melalui saluran asal (seperti UC-18 langkah 3).
     */
    private function aksiBuatAksesPelapor(): Action
    {
        return Action::make('buatAksesPelapor')
            ->label('Kode akses pelapor')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->authorize('telaahLaporanWbs')
            ->visible(fn (Laporan $record): bool => PemindahPengaduanKeWbs::bisaBuatAksesPelapor($record))
            ->requiresConfirmation()
            ->modalHeading('Buat PIN sementara untuk pelapor')
            ->modalDescription('Laporan ini dipindah dari kanal Pengaduan, sehingga pelapor belum memegang PIN. PIN baru '
                .'ditampilkan sekali; PIN sebelumnya tidak berlaku lagi. Sampaikan kode lacak dan PIN kepada pelapor melalui '
                .'kontak di identitas pelapor. Pembuatan PIN tercatat di log audit.')
            ->modalSubmitActionLabel('Buat PIN')
            ->action(function (PemindahPengaduanKeWbs $pemindah): void {
                $user = Filament::auth()->user();
                abort_unless($user instanceof User, 403);

                $this->aksesPelapor = [
                    'kode_lacak' => $this->record->kode_lacak,
                    'pin' => $pemindah->buatAksesPelapor($this->record, $user),
                ];

                $this->replaceMountedAction('tampilkanAksesPelapor');
            });
    }

    public function tampilkanAksesPelaporAction(): Action
    {
        return Action::make('tampilkanAksesPelapor')
            ->visible(fn (): bool => $this->aksesPelapor !== null)
            ->modalHeading('Kode akses pelapor')
            ->modalDescription('Catat dan sampaikan sekarang. PIN tidak dapat ditampilkan lagi setelah jendela ini ditutup.')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->schema([
                TextEntry::make('kode_lacak')->label('Kode lacak')->state(fn (): ?string => $this->aksesPelapor['kode_lacak'] ?? null)->copyable(),
                TextEntry::make('pin')->label('PIN sementara')->state(fn (): ?string => $this->aksesPelapor['pin'] ?? null)->copyable(),
            ])
            ->after(fn () => $this->aksesPelapor = null);
    }

    private function aksiMulaiTelaah(): Action
    {
        return Action::make('mulaiTelaah')
            ->label('Mulai telaah')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->color('wbs')
            ->authorize('telaahLaporanWbs')
            ->visible(fn (Laporan $record): bool => PenangananWbs::bisa('mulaiTelaah', $record))
            ->requiresConfirmation()
            ->modalDescription('Status yang terlihat pelapor menjadi Ditelaah.')
            ->action(fn (PenangananWbs $penanganan) => $this->jalankan(
                fn (User $oleh) => $penanganan->mulaiTelaah($this->record, $oleh),
                'Telaah dimulai.',
            ));
    }

    private function aksiKoreksiKedudukan(): Action
    {
        return Action::make('koreksiKedudukan')
            ->label('Koreksi kedudukan terlapor')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->authorize('telaahLaporanWbs')
            ->visible(fn (Laporan $record): bool => PenangananWbs::bisa('koreksiKedudukan', $record))
            ->modalDescription('Bila kedudukan yang benar adalah SPI/anggota Tim WBS, Pengawas, atau Pembina, laporan berpindah ke penelaah pengganti menurut matriks dan Anda tidak dapat membukanya lagi.')
            ->fillForm(fn (Laporan $record): array => ['kedudukan' => ($record->kedudukan_terlapor ?? KedudukanTerlapor::TidakTahu)->value])
            ->schema([
                Select::make('kedudukan')->label('Kedudukan terlapor')->options(KedudukanTerlapor::class)->required(),
                Textarea::make('alasan')->label('Alasan koreksi')->required()->rule($this->tidakKosong('Alasan koreksi'))->maxLength(PenangananWbs::MAKS_CATATAN)->rows(3),
            ])
            ->action(fn (array $data, PenangananWbs $penanganan) => $this->jalankan(
                fn (User $oleh) => $penanganan->koreksiKedudukan(
                    $this->record,
                    $oleh,
                    $data['kedudukan'] instanceof KedudukanTerlapor ? $data['kedudukan'] : KedudukanTerlapor::from($data['kedudukan']),
                    (string) $data['alasan'],
                ),
                'Kedudukan terlapor dikoreksi.',
            ));
    }

    private function aksiTidakDitindaklanjuti(): Action
    {
        return Action::make('tidakDitindaklanjuti')
            ->label('Tidak layak investigasi')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->authorize('telaahLaporanWbs')
            ->visible(fn (Laporan $record): bool => PenangananWbs::bisa('tidakDitindaklanjuti', $record))
            ->modalHeading('Laporan tidak ditindaklanjuti')
            ->modalDescription('Alasan umum ini terlihat pelapor di halaman Lacak. Jangan memuat nama, identitas, atau rincian investigasi.')
            ->schema([
                Textarea::make('alasan')->label('Alasan umum')->required()->rule($this->tidakKosong('Alasan umum'))->maxLength(PenangananWbs::MAKS_CATATAN)->rows(3),
            ])
            ->action(fn (array $data, PenangananWbs $penanganan) => $this->jalankan(
                fn (User $oleh) => $penanganan->tidakDitindaklanjuti($this->record, $oleh, (string) $data['alasan']),
                'Laporan dinyatakan tidak ditindaklanjuti.',
            ));
    }

    private function aksiMulaiInvestigasi(): Action
    {
        return Action::make('mulaiInvestigasi')
            ->label('Layak, mulai investigasi')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('wbs')
            ->authorize('telaahLaporanWbs')
            ->visible(fn (Laporan $record): bool => PenangananWbs::bisa('mulaiInvestigasi', $record))
            ->modalDescription('Batas investigasi 30 hari kerja dihitung sejak hari ini. Investigator diberi tahu lewat surel tanpa isi laporan.')
            ->schema([
                Select::make('investigator_user_id')
                    ->label('Investigator')
                    ->options(fn (): array => AksesWbs::calonInvestigator($this->record)->pluck('name', 'id')->all())
                    ->helperText('Hanya anggota tim yang tidak disebut sebagai terlapor.')
                    ->required(),
                Textarea::make('rencana')
                    ->label('Hasil telaah dan rencana investigasi')
                    ->required()
                    ->rule($this->tidakKosong('Hasil telaah dan rencana investigasi'))
                    ->maxLength(PenangananWbs::MAKS_CATATAN)
                    ->rows(4),
            ])
            ->action(fn (array $data, PenangananWbs $penanganan) => $this->jalankan(
                fn (User $oleh) => $penanganan->mulaiInvestigasi($this->record, $oleh, User::query()->findOrFail($data['investigator_user_id']), (string) $data['rencana']),
                'Investigasi dimulai.',
            ));
    }

    private function aksiSimpulkan(): Action
    {
        return Action::make('simpulkan')
            ->label('Tetapkan kesimpulan')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->authorize('simpulkanLaporanWbs')
            ->visible(fn (Laporan $record): bool => PenangananWbs::bisa('simpulkan', $record))
            ->modalDescription('Tim WBS tidak menjatuhkan sanksi. Laporan berstatus Selesai; rekomendasi untuk penerima hasil disimpan terenkripsi.')
            ->schema([
                Select::make('kesimpulan')->label('Kesimpulan')->options(KesimpulanWbs::class)->required(),
                Textarea::make('rekomendasi')->label('Rekomendasi')->required()->rule($this->tidakKosong('Rekomendasi'))->maxLength(PenangananWbs::MAKS_CATATAN)->rows(4),
                Textarea::make('catatan_pelapor')
                    ->label('Catatan untuk pelapor (boleh dikosongkan)')
                    ->helperText('Terlihat pelapor di halaman Lacak. Jangan memuat identitas atau rincian investigasi.')
                    ->maxLength(PenangananWbs::MAKS_CATATAN)
                    ->rows(2),
            ])
            ->action(fn (array $data, PenangananWbs $penanganan) => $this->jalankan(
                fn (User $oleh) => $penanganan->simpulkan(
                    $this->record,
                    $oleh,
                    $data['kesimpulan'] instanceof KesimpulanWbs ? $data['kesimpulan'] : KesimpulanWbs::from($data['kesimpulan']),
                    (string) $data['rekomendasi'],
                    $data['catatan_pelapor'] ?? null,
                ),
                'Kesimpulan ditetapkan. Laporan selesai.',
            ));
    }

    /**
     * Pesan ke pelapor (UC-05) atau catatan internal (UC-13 langkah 2). Pelapor melihat pengirim sebagai "Tim WBS".
     */
    private function aksiKirimPesan(): Action
    {
        return Action::make('kirimPesan')
            ->label('Pesan / catatan internal')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('gray')
            ->authorize('tanganiLaporanWbs')
            ->modalHeading('Pesan ke pelapor atau catatan internal')
            ->modalDescription('Pelapor membaca pesan di halaman Lacak dengan pengirim "Tim WBS". Catatan internal tidak pernah terlihat pelapor.')
            ->modalSubmitActionLabel('Simpan')
            ->schema([
                Textarea::make('isi')->label('Isi')->required()->rule($this->tidakKosong('Isi pesan'))->maxLength(PesanLaporan::MAKS_ISI)->rows(4),
                Toggle::make('internal')->label('Catatan internal (tidak terlihat pelapor)')->default(true),
            ])
            ->action(fn (array $data, PesanLaporan $pesan) => $this->jalankan(
                fn (User $oleh) => $pesan->kirimDariPetugas($this->record, $oleh, (string) $data['isi'], (bool) ($data['internal'] ?? false)),
                ($data['internal'] ?? false) ? 'Catatan internal tersimpan.' : 'Pesan terkirim ke pelapor.',
            ));
    }

    /**
     * Identitas pelapor WBS hanya untuk Ketua Tim WBS atau penelaah pengganti (US-16); setiap pembukaan tercatat.
     */
    private function aksiLihatIdentitas(): Action
    {
        return Action::make('lihatIdentitas')
            ->label('Buka identitas pelapor')
            ->icon(Heroicon::OutlinedIdentification)
            ->color('gray')
            ->authorize('lihatIdentitasPelapor')
            ->visible(fn (Laporan $record): bool => $record->identitasPelapor !== null)
            ->modalHeading('Identitas pelapor')
            ->modalDescription('Buka hanya bila perlu. Pembukaan ini tercatat di log audit.')
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
            ]);
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
            $this->record->refresh()->load(LaporanWbsInfolist::RELASI);
        }

        if (! Gate::forUser($user)->allows('bukaLaporanWbs', $this->record)) {
            Notification::make()->success()->title($pesanBerhasil)->body('Laporan ini kini ditangani penelaah lain menurut matriks.')->send();
            $this->redirect(LaporanWbsResource::getUrl('index'));

            return;
        }

        Notification::make()->success()->title($pesanBerhasil)->send();
    }

    private function tidakKosong(string $isian): Closure
    {
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($isian): void {
            if (trim((string) $value) === '') {
                $fail("{$isian} wajib diisi.");
            }
        };
    }
}
