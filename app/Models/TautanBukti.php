<?php

namespace App\Models;

use Database\Factories\TautanBuktiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * Tautan Google Drive sebagai bukti tambahan dari pelapor (revisi 20-11, log deviasi Bagian 8): pada laporan atau
 * pada pesan lanjutan di Lacak. Alamatnya terenkripsi untuk semua baris karena bisa memuat nama berkas atau
 * pemiliknya. Berkas di balik tautan tidak dipindai dan tidak dienkripsi sistem; petugas membukanya lewat
 * BukaTautanBuktiController yang memeriksa hak akses dan mencatat log audit.
 */
#[Table('tautan_bukti')]
#[Fillable(['laporan_id', 'pesan_id', 'url'])]
class TautanBukti extends Model
{
    /** @use HasFactory<TautanBuktiFactory> */
    use HasFactory;

    public const CREATED_AT = 'dibuat_pada';

    public const UPDATED_AT = null;

    /** Banyaknya tautan per laporan atau per pesan. */
    public const MAKS = 5;

    /** Awalan alamat yang diterima: hanya Google Drive dan Google Docs lewat HTTPS. */
    public const AWALAN_SAH = ['https://drive.google.com/', 'https://docs.google.com/'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'url' => 'encrypted',
        ];
    }

    /**
     * Alamat Google Drive yang sah: HTTPS, host persis drive.google.com atau docs.google.com, tanpa nama pengguna,
     * kata sandi, atau port lain.
     */
    public static function alamatSah(mixed $url): bool
    {
        if (! is_string($url) || strlen($url) > 2000 || preg_match('/\s/', $url)) {
            return false;
        }

        $bagian = parse_url($url);

        return is_array($bagian)
            && ($bagian['scheme'] ?? null) === 'https'
            && in_array($bagian['host'] ?? null, ['drive.google.com', 'docs.google.com'], true)
            && ! isset($bagian['user']) && ! isset($bagian['pass'])
            && ! isset($bagian['port'])
            && collect(self::AWALAN_SAH)->contains(fn (string $awalan): bool => str_starts_with($url, $awalan));
    }

    /**
     * Tautan sementara (10 menit) untuk petugas; pembukaannya diperiksa dan dicatat di BukaTautanBuktiController.
     */
    public function tautanBukaSementara(): string
    {
        return URL::temporarySignedRoute('filament.panel.tautan-bukti.buka', now()->addMinutes(Lampiran::MASA_TAUTAN_MENIT), ['tautanBukti' => $this->getKey()]);
    }

    /**
     * @return BelongsTo<Laporan, $this>
     */
    public function laporan(): BelongsTo
    {
        return $this->belongsTo(Laporan::class);
    }

    /**
     * @return BelongsTo<Pesan, $this>
     */
    public function pesan(): BelongsTo
    {
        return $this->belongsTo(Pesan::class);
    }
}
