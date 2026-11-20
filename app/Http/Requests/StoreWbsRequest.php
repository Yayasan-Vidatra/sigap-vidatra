<?php

namespace App\Http\Requests;

use App\Enums\JenisLaporan;
use App\Enums\KedudukanTerlapor;
use App\Http\Controllers\WbsController;
use App\Rules\AltchaSah;
use App\Rules\UnggahanLampiranSiap;
use App\Services\Lampiran\PenyimpanTautanBukti;
use App\Services\WaktuLokal;
use App\Services\Wbs\KodeAksesWbs;
use App\Services\Wbs\TokenFormulirWbs;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validasi Formulir WBS H-04 (US-05, UC-02, F-03).
 *
 * Sejak revisi 20-11 laporan WBS tidak anonim: nama, surel, dan nomor HP/WA wajib; akun media sosial opsional.
 * Kontak orang tua/wali tidak diminta. Satu atau lebih terlapor (nama wajib, jabatan boleh kosong), kedudukan
 * terlapor wajib (Bagian 3.1); "Lain-lain" wajib disertai kedudukan yang ditulis pelapor, "Tidak tahu" tidak bisa
 * dipilih lagi. Tautan Google Drive hanya diterima bersama persetujuan peringatannya. PIN 6 digit disimpan sebagai
 * hash. Pengganti CSRF di jalur tanpa sesi: token akses (lolos kode akses), token formulir WBS, dan ALTCHA; token
 * formulir dan ALTCHA ditandai terpakai setelah seluruh isian sah. situs_web adalah honeypot.
 */
class StoreWbsRequest extends FormRequest
{
    public const PESAN_TOKEN = 'Halaman formulir sudah terlalu lama terbuka. Isian Anda masih ada; tekan Kirim sekali lagi.';

    public const PESAN_RAHASIA = 'Identitas Anda dirahasiakan dan hanya dibuka oleh penelaah yang berwenang.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token_akses' => ['bail', 'required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! KodeAksesWbs::tokenSah($value)) {
                    $fail(WbsController::PESAN_TOKEN_AKSES);
                }
            }],
            'token' => ['bail', 'required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (TokenFormulirWbs::nonce(is_string($value) ? $value : null) === null) {
                    $fail(self::PESAN_TOKEN);
                }
            }],
            'nama' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'no_hp' => ['required', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
            'media_sosial' => ['nullable', 'string', 'max:150'],
            'kategori_id' => ['required', 'integer', Rule::exists('kategori', 'id')->where('jenis', JenisLaporan::Wbs->value)],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('aktif', true)],
            'terlapor' => ['required', 'array', 'min:1', 'max:'.config('sigap.wbs.maks_terlapor')],
            'terlapor.*' => ['array:nama,jabatan'],
            'terlapor.*.nama' => ['required', 'string', 'max:150'],
            'terlapor.*.jabatan' => ['nullable', 'string', 'max:150'],
            'kedudukan_terlapor' => ['required', Rule::enum(KedudukanTerlapor::class)->only(KedudukanTerlapor::pilihanFormulir())],
            'kedudukan_lain' => ['exclude_unless:kedudukan_terlapor,'.KedudukanTerlapor::LainLain->value, 'required', 'string', 'max:100'],
            'judul' => ['required', 'string', 'max:200'],
            'kronologi' => ['required', 'string', 'min:20', 'max:5000'],
            'waktu_kejadian' => ['nullable', 'date', 'before_or_equal:'.WaktuLokal::sekarangSebagaiMasukan()],
            'lokasi' => ['nullable', 'string', 'max:200'],
            'saksi' => ['nullable', 'string', 'max:1000'],
            'lampiran' => ['nullable', 'array', 'max:'.config('sigap.lampiran.maks_berkas'), new UnggahanLampiranSiap(JenisLaporan::Wbs)],
            'lampiran.*' => ['string', 'distinct'],
            ...PenyimpanTautanBukti::aturan($this),
            'pin' => ['required', 'string', 'regex:/^[0-9]{6}$/', 'confirmed', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && self::pinMudahDitebak($value)) {
                    $fail('PIN terlalu mudah ditebak. Hindari angka yang sama atau berurutan.');
                }
            }],
            'situs_web' => ['prohibited'],
            'altcha' => ['bail', 'required', 'string', new AltchaSah],
        ];
    }

    /**
     * PIN berisi satu angka berulang (111111) atau deret naik/turun (123456, 987654).
     */
    public static function pinMudahDitebak(string $pin): bool
    {
        if (count(array_unique(str_split($pin))) === 1) {
            return true;
        }

        return str_contains('01234567890', $pin) || str_contains('09876543210', $pin);
    }

    /**
     * Token formulir dan solusi CAPTCHA baru ditandai terpakai setelah seluruh isian sah, sehingga pelapor
     * yang salah mengisi kolom lain bisa mengirim ulang. Dari dua kiriman bersamaan hanya satu yang lolos.
     */
    protected function passedValidation(): void
    {
        if (! TokenFormulirWbs::pakai((string) $this->input('token'))) {
            throw ValidationException::withMessages(['token' => self::PESAN_TOKEN]);
        }

        if (! AltchaSah::tandaiDipakai((string) $this->input('altcha'))) {
            throw ValidationException::withMessages([
                'altcha' => 'Verifikasi "Saya bukan robot" sudah dipakai. Silakan ulangi.',
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'token.required' => self::PESAN_TOKEN,
            'token_akses.required' => WbsController::PESAN_TOKEN_AKSES,
            'nama.required' => 'Isi nama Anda. '.self::PESAN_RAHASIA,
            'email.required' => 'Isi surel Anda. '.self::PESAN_RAHASIA,
            'no_hp.required' => 'Isi nomor HP/WhatsApp Anda. '.self::PESAN_RAHASIA,
            'terlapor.required' => 'Isi sekurang-kurangnya satu pihak terlapor.',
            'terlapor.min' => 'Isi sekurang-kurangnya satu pihak terlapor.',
            'terlapor.max' => 'Terlapor paling banyak :max orang.',
            'terlapor.*.nama.required' => 'Isi nama terlapor. Bila tidak tahu namanya, tulis ciri atau sebutannya.',
            'kedudukan_terlapor.required' => 'Pilih kedudukan terlapor. Pilih "Lain-lain" bila tidak ada yang pas.',
            'kedudukan_lain.required' => 'Tuliskan kedudukan terlapor, misalnya "petugas kantin" atau "rekanan pengadaan".',
            'kedudukan_lain.max' => 'Kedudukan terlapor paling banyak :max karakter.',
            'exists' => ':attribute yang dipilih tidak tersedia.',
            'enum' => ':attribute yang dipilih tidak tersedia.',
            'email' => 'Format surel tidak valid.',
            'regex' => 'Format :attribute tidak valid.',
            'pin.regex' => 'PIN harus 6 angka.',
            'pin.confirmed' => 'Ulangi PIN yang sama.',
            'max' => ':attribute paling banyak :max karakter.',
            'min' => ':attribute paling sedikit :min karakter.',
            'date' => ':attribute tidak valid.',
            'before_or_equal' => ':attribute tidak boleh di masa depan.',
            'situs_web.prohibited' => 'Pengiriman tidak dapat diproses.',
            'altcha.required' => 'Selesaikan verifikasi "Saya bukan robot" terlebih dahulu.',
            'lampiran.max' => 'Lampiran paling banyak :max berkas.',
            'lampiran.*.distinct' => 'Lampiran yang sama terkirim dua kali.',
            ...PenyimpanTautanBukti::pesan(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nama' => 'Nama',
            'email' => 'Surel',
            'no_hp' => 'Nomor HP/WhatsApp',
            'media_sosial' => 'Akun media sosial',
            'kedudukan_lain' => 'Kedudukan terlapor',
            'kategori_id' => 'Jenis pelanggaran',
            'unit_id' => 'Unit',
            'terlapor.*.nama' => 'Nama terlapor',
            'terlapor.*.jabatan' => 'Jabatan terlapor',
            'kedudukan_terlapor' => 'Kedudukan terlapor',
            'judul' => 'Judul',
            'kronologi' => 'Kronologi',
            'waktu_kejadian' => 'Waktu kejadian',
            'lokasi' => 'Lokasi',
            'saksi' => 'Saksi',
            'pin' => 'PIN',
        ];
    }
}
