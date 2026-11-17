<?php

namespace App\Models;

use App\Casts\TerenkripsiBilaWbs;
use App\Enums\JenisLaporan;
use App\Enums\JenisPelapor;
use App\Enums\KedudukanTerlapor;
use App\Enums\KesimpulanWbs;
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
     * @return HasMany<Pesan, $this>
     */
    public function pesan(): HasMany
    {
        return $this->hasMany(Pesan::class);
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
