<?php

namespace App\Services\Lampiran;

use App\Models\Laporan;
use App\Models\Pesan;
use App\Models\TautanBukti;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tautan Google Drive sebagai bukti tambahan (revisi 20-11, log deviasi Bagian 8 dan 9): maks. 5 tautan per laporan
 * atau per pesan, hanya https://drive.google.com/ dan https://docs.google.com/, dan hanya bila pelapor menyetujui
 * peringatan "Saya mengerti" (berkas tidak dipindai, tidak dienkripsi, jangan dihapus, surel pemilik terlihat
 * petugas). Persetujuan divalidasi di server, tidak cukup di browser. Alamatnya disimpan terenkripsi.
 */
class PenyimpanTautanBukti
{
    public const PESAN_ALAMAT = 'Hanya tautan Google Drive (https://drive.google.com/ atau https://docs.google.com/) yang diterima.';

    public const PESAN_SETUJU = 'Baca dan setujui peringatan tautan Google Drive (tekan "Saya mengerti") sebelum mengirim tautan.';

    /**
     * Aturan validasi untuk isian `tautan` (daftar alamat) dan `setuju_tautan`.
     *
     * @return array<string, array<mixed>>
     */
    public static function aturan(Request $request): array
    {
        $adaTautan = fn (): bool => collect($request->input('tautan', []))->filter(fn (mixed $url): bool => filled($url))->isNotEmpty();

        return [
            'tautan' => ['nullable', 'array', 'max:'.TautanBukti::MAKS],
            'tautan.*' => ['bail', 'required', 'string', 'max:2000', 'distinct', function (string $attribute, mixed $value, Closure $fail): void {
                if (! TautanBukti::alamatSah($value)) {
                    $fail(self::PESAN_ALAMAT);
                }
            }],
            'setuju_tautan' => [Rule::requiredIf($adaTautan), Rule::when($adaTautan, ['accepted'], ['nullable'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function pesan(): array
    {
        return [
            'tautan.array' => self::PESAN_ALAMAT,
            'tautan.max' => 'Tautan Google Drive paling banyak :max.',
            'tautan.*.required' => 'Tempel alamat tautan atau hapus isian yang kosong.',
            'tautan.*.max' => 'Alamat tautan terlalu panjang.',
            'tautan.*.distinct' => 'Tautan yang sama ditempel dua kali.',
            'setuju_tautan.required' => self::PESAN_SETUJU,
            'setuju_tautan.accepted' => self::PESAN_SETUJU,
        ];
    }

    /**
     * @param  list<string>  $alamat  alamat yang sudah lolos validasi
     */
    public function simpan(Laporan $laporan, array $alamat, ?Pesan $pesan = null): void
    {
        foreach (array_values(array_unique($alamat)) as $url) {
            $laporan->tautanBukti()->create([
                'pesan_id' => $pesan?->getKey(),
                'url' => trim($url),
            ]);
        }
    }
}
