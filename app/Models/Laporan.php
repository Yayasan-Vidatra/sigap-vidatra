<?php

namespace App\Models;

use App\Casts\TerenkripsiBilaWbs;
use App\Enums\JenisLaporan;
use App\Enums\JenisPelapor;
use App\Enums\KedudukanTerlapor;
use App\Enums\KesimpulanWbs;
use App\Enums\Peran;
use App\Enums\Prioritas;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Enums\TujuanPengalihan;
use App\Exceptions\PelanggaranIntegritasData;
use Database\Factories\LaporanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Random\Randomizer;

/**
 * Laporan bersama kanal Pengaduan dan WBS (Rancangan r1 Bagian 10).
 */
#[Table('laporan')]
#[Fillable([
    'kode_lacak', 'jenis', 'sumber', 'jenis_pelapor', 'unit_id', 'kategori_id', 'judul', 'kronologi',
    'waktu_kejadian', 'lokasi', 'jabatan_pihak_dikeluhkan', 'saksi', 'kedudukan_terlapor', 'status',
    'prioritas', 'batas_waktu', 'penilaian', 'kesimpulan', 'dialihkan_ke', 'perpanjangan_disetujui_oleh',
    'pin_hash',
])]
#[Hidden(['pin_hash'])]
class Laporan extends Model
{
    /** @use HasFactory<LaporanFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = 'diperbarui_pada';

    /**
     * Kolom yang hanya berlaku untuk pengaduan. Di WBS, pihak terlapor dicatat terenkripsi di
     * terlapor_wbs, sehingga jabatan_pihak_dikeluhkan tidak boleh terisi teks biasa.
     *
     * @var list<string>
     */
    public const KOLOM_KHUSUS_PENGADUAN = ['jenis_pelapor', 'jabatan_pihak_dikeluhkan', 'penilaian'];

    /**
     * @var list<string>
     */
    public const KOLOM_KHUSUS_WBS = ['kesimpulan'];

    protected static function booted(): void
    {
        static::saving(function (Laporan $laporan): void {
            if ($laporan->exists && $laporan->isDirty('jenis')) {
                throw PelanggaranIntegritasData::jenisLaporanTidakBolehBerubah();
            }

            if ($laporan->status !== null && ! $laporan->status->berlakuUntuk($laporan->jenis)) {
                throw PelanggaranIntegritasData::statusTidakBerlaku($laporan->status, $laporan->jenis);
            }

            $laporan->pastikanKolomSesuaiJenis();

            if ($laporan->penilaian !== null && ($laporan->penilaian < 1 || $laporan->penilaian > 5)) {
                throw PelanggaranIntegritasData::penilaianDiLuarRentang($laporan->penilaian);
            }
        });
    }

    private function pastikanKolomSesuaiJenis(): void
    {
        [$kolomTerlarang, $jenisBerlaku] = $this->isWbs()
            ? [self::KOLOM_KHUSUS_PENGADUAN, JenisLaporan::Pengaduan]
            : [self::KOLOM_KHUSUS_WBS, JenisLaporan::Wbs];

        foreach ($kolomTerlarang as $kolom) {
            if (($this->getAttributes()[$kolom] ?? null) !== null) {
                throw PelanggaranIntegritasData::hanyaUntukJenis("Kolom {$kolom}", $jenisBerlaku);
            }
        }
    }

    /**
     * Mengisi jenis lebih dulu, karena cast TerenkripsiBilaWbs membaca jenis untuk memutuskan enkripsi.
     *
     * @param  array<string, mixed>  $attributes
     * @return $this
     */
    public function fill(array $attributes)
    {
        if (array_key_exists('jenis', $attributes)) {
            $attributes = ['jenis' => $attributes['jenis']] + $attributes;
        }

        return parent::fill($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisLaporan::class,
            'sumber' => SumberLaporan::class,
            'jenis_pelapor' => JenisPelapor::class,
            'judul' => TerenkripsiBilaWbs::class,
            'kronologi' => TerenkripsiBilaWbs::class,
            'lokasi' => TerenkripsiBilaWbs::class,
            'saksi' => TerenkripsiBilaWbs::class,
            'waktu_kejadian' => 'datetime',
            'kedudukan_terlapor' => KedudukanTerlapor::class,
            'status' => StatusLaporan::class,
            'prioritas' => Prioritas::class,
            'batas_waktu' => 'datetime',
            'penilaian' => 'integer',
            'kesimpulan' => KesimpulanWbs::class,
            'dialihkan_ke' => TujuanPengalihan::class,
            'pin_hash' => 'hashed',
        ];
    }

    public function isWbs(): bool
    {
        return $this->jenis === JenisLaporan::Wbs;
    }

    /**
     * Huruf dan angka kode lacak, tanpa karakter yang mudah tertukar (0/O, 1/I/L).
     */
    public const ALFABET_KODE_LACAK = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private static ?Randomizer $pengacakKodeLacak = null;

    /**
     * Kode lacak acak yang belum dipakai (Rancangan r1 Bagian 9, US-07). Pengaduan: PGD-{yymm}-{6 acak},
     * mengikuti contoh UC-01 dengan bagian akhir acak. WBS: WBS-{4 acak}-{4 acak}, mengikuti contoh UC-02.
     */
    public static function buatKodeLacak(JenisLaporan $jenis): string
    {
        do {
            $kode = match ($jenis) {
                JenisLaporan::Pengaduan => 'PGD-'.now()->format('ym').'-'.self::acakKodeLacak(6),
                JenisLaporan::Wbs => 'WBS-'.self::acakKodeLacak(4).'-'.self::acakKodeLacak(4),
            };
        } while (self::query()->where('kode_lacak', $kode)->exists());

        return $kode;
    }

    /**
     * Mengganti sumber acak kode lacak; hanya untuk test. null kembali ke sumber acak kriptografis.
     */
    public static function pakaiPengacakKodeLacak(?Randomizer $pengacak): void
    {
        self::$pengacakKodeLacak = $pengacak;
    }

    private static function acakKodeLacak(int $panjang): string
    {
        return (self::$pengacakKodeLacak ?? new Randomizer)->getBytesFromString(self::ALFABET_KODE_LACAK, $panjang);
    }

    /**
     * Laporan WBS yang boleh dibuka Ketua Tim WBS menurut matriks Bagian 3.1 (aturan proyek no. 2).
     * Kedudukan kosong diperlakukan seperti "Tidak tahu", yang ditelaah Tim WBS.
     *
     * @param  Builder<Laporan>  $query
     */
    #[Scope]
    protected function terbukaBagiKetuaTimWbs(Builder $query): void
    {
        $query->where('jenis', JenisLaporan::Wbs->value)
            ->where(fn (Builder $kedudukan) => $kedudukan
                ->whereNull('kedudukan_terlapor')
                ->orWhereNotIn('kedudukan_terlapor', KedudukanTerlapor::nilaiTertutupBagiKetuaTimWbs()));
    }

    /**
     * Laporan WBS yang boleh dibuka pengguna menurut matriks penerima Bagian 3.1 (F-19, aturan proyek no. 2 dan 3).
     * Kedudukan kosong diperlakukan seperti "Tidak tahu". Super Admin dan peran tanpa baris di matriks tidak
     * mendapat baris apa pun.
     *
     * @param  Builder<Laporan>  $query
     */
    #[Scope]
    protected function wbsTerbukaBagi(Builder $query, User $user): void
    {
        $nilai = $user->peran === null ? [] : KedudukanTerlapor::nilaiTerbukaBagi($user->peran);

        if ($nilai === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('jenis', JenisLaporan::Wbs->value)
            ->where(fn (Builder $kedudukan) => $kedudukan
                ->whereIn('kedudukan_terlapor', $nilai)
                ->when(
                    in_array(KedudukanTerlapor::TidakTahu->value, $nilai, true),
                    fn (Builder $kosong) => $kosong->orWhereNull('kedudukan_terlapor'),
                ));
    }

    /**
     * Pengaduan di Kotak Masuk (H-11) yang boleh ditangani pengguna: Admin Pengaduan yayasan melihat
     * semua unit, Admin Sekolah (Admin Pengaduan dengan unit_id) hanya unitnya. Laporan WBS tidak pernah
     * termasuk, dan peran lain tidak mendapat baris apa pun.
     *
     * @param  Builder<Laporan>  $query
     */
    #[Scope]
    protected function kotakMasukPengaduan(Builder $query, User $user): void
    {
        if ($user->peran !== Peran::AdminPengaduan) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('jenis', JenisLaporan::Pengaduan->value)
            ->when($user->isAdminSekolah(), fn (Builder $unit) => $unit->where('unit_id', $user->unit_id));
    }

    /**
     * Pengaduan di halaman Pengaduan Unit Saya (H-12) untuk PIC Unit: hanya pengaduan yang sudah
     * didisposisikan ke unitnya. Pengaduan unit itu yang belum didisposisikan, pengaduan unit lain, dan
     * laporan WBS tidak termasuk; peran lain tidak mendapat baris apa pun.
     *
     * @param  Builder<Laporan>  $query
     */
    #[Scope]
    protected function pengaduanUnitPic(Builder $query, User $user): void
    {
        if ($user->peran !== Peran::PicUnit || $user->unit_id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('jenis', JenisLaporan::Pengaduan->value)
            ->where('unit_id', $user->unit_id)
            ->whereHas('disposisi', fn (Builder $disposisi) => $disposisi->where('unit_id', $user->unit_id));
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<Kategori, $this>
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function perpanjanganDisetujuiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'perpanjangan_disetujui_oleh');
    }

    /**
     * @return HasOne<IdentitasPelapor, $this>
     */
    public function identitasPelapor(): HasOne
    {
        return $this->hasOne(IdentitasPelapor::class);
    }

    /**
     * @return HasMany<TerlaporWbs, $this>
     */
    public function terlapor(): HasMany
    {
        return $this->hasMany(TerlaporWbs::class);
    }

    /**
     * @return HasMany<Lampiran, $this>
     */
    public function lampiran(): HasMany
    {
        return $this->hasMany(Lampiran::class);
    }

    /**
     * Lampiran dari pelapor, tanpa bukti penyelesaian PIC Unit.
     *
     * @return HasMany<Lampiran, $this>
     */
    public function lampiranPelapor(): HasMany
    {
        return $this->lampiran()->whereNull('disposisi_id');
    }

    /**
     * Bukti penyelesaian yang diunggah PIC Unit lewat disposisi (UC-10).
     *
     * @return HasMany<Lampiran, $this>
     */
    public function buktiPenyelesaian(): HasMany
    {
        return $this->lampiran()->whereNotNull('disposisi_id');
    }

    /**
     * @return HasMany<Pesan, $this>
     */
    public function pesan(): HasMany
    {
        return $this->hasMany(Pesan::class);
    }

    /**
     * Pesan yang boleh dibaca pelapor: tanpa catatan internal petugas (US-09).
     *
     * @return HasMany<Pesan, $this>
     */
    public function pesanUntukPelapor(): HasMany
    {
        return $this->pesan()->where('internal', false);
    }

    /**
     * @return HasMany<RiwayatStatus, $this>
     */
    public function riwayatStatus(): HasMany
    {
        return $this->hasMany(RiwayatStatus::class);
    }

    /**
     * @return HasMany<Disposisi, $this>
     */
    public function disposisi(): HasMany
    {
        return $this->hasMany(Disposisi::class);
    }

    /**
     * @return HasMany<Pengalihan, $this>
     */
    public function pengalihan(): HasMany
    {
        return $this->hasMany(Pengalihan::class);
    }
}
