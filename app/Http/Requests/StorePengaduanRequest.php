<?php

namespace App\Http\Requests;

use App\Enums\JenisLaporan;
use App\Enums\JenisPelapor;
use App\Rules\AltchaSah;
use App\Services\Lampiran\PenyimpanTautanBukti;
use App\Services\Lampiran\UnggahanBertahap;
use App\Services\WaktuLokal;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Validasi Formulir Pengaduan H-02 (US-02, US-03, UC-01, F-02).
 *
 * Pengaduan wajib nama dan kontak (Rancangan r1 Bagian 2). Pelapor siswa mengisi kontak orang tua/wali
 * dengan persetujuannya; surel dan nomor HP siswa sendiri tidak disimpan. situs_web adalah honeypot.
 */
class StorePengaduanRequest extends FormRequest
{
    /** Panjang maksimum kronologi; juga dipakai penghitung karakter di formulir. */
    public const MAKS_KRONOLOGI = 5000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $siswa = JenisPelapor::Siswa->value;

        return [
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('aktif', true)->where('khusus_wbs', false)],
            'kategori_id' => ['required', 'integer', Rule::exists('kategori', 'id')->where('jenis', JenisLaporan::Pengaduan->value)],
            'jenis_pelapor' => ['required', Rule::enum(JenisPelapor::class)],
            'nama' => ['required', 'string', 'max:150'],
            'email' => ["exclude_if:jenis_pelapor,{$siswa}", 'nullable', 'required_without:no_hp', 'email', 'max:150'],
            'no_hp' => ["exclude_if:jenis_pelapor,{$siswa}", 'nullable', 'required_without:email', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
            'kontak_orang_tua' => ["exclude_unless:jenis_pelapor,{$siswa}", 'required', 'string', 'max:150'],
            'persetujuan_orang_tua' => ["exclude_unless:jenis_pelapor,{$siswa}", 'accepted'],
            'judul' => ['required', 'string', 'max:200'],
            'kronologi' => ['required', 'string', 'min:20', 'max:'.self::MAKS_KRONOLOGI],
            // Isian datetime-local tidak membawa zona; pelapor mengisi waktu WITA (App\Services\WaktuLokal).
            'waktu_kejadian' => ['nullable', 'date', 'before_or_equal:'.WaktuLokal::sekarangSebagaiMasukan()],
            'lokasi' => ['nullable', 'string', 'max:200'],
            'jabatan_pihak_dikeluhkan' => ['nullable', 'string', 'max:150'],
            'setuju_ketentuan' => ['accepted'],
            'situs_web' => ['prohibited'],
            'lampiran' => ['nullable', 'array', 'max:'.config('sigap.lampiran.maks_berkas')],
            'lampiran.*' => ['string', 'distinct'],
            ...PenyimpanTautanBukti::aturan($this),
            'altcha' => ['bail', 'required', 'string', new AltchaSah],
        ];
    }

    /**
     * Setiap id lampiran harus unggahan pengaduan yang sudah selesai diperiksa, dan totalnya paling
     * besar 200 MB (Rancangan r1 Bagian 8).
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('lampiran') || ! is_array($this->input('lampiran'))) {
                return;
            }

            $total = 0;

            foreach ($this->input('lampiran') as $id) {
                $siap = app(UnggahanBertahap::class)->ambilSiap((string) $id, JenisLaporan::Pengaduan);

                if ($siap === null) {
                    $validator->errors()->add('lampiran', 'Ada lampiran yang belum selesai diunggah atau sudah kedaluwarsa. Hapus lalu unggah ulang.');

                    return;
                }

                $total += $siap['ukuran'];
            }

            if ($total > (int) config('sigap.lampiran.maks_total_mb') * 1024 * 1024) {
                $validator->errors()->add('lampiran', 'Total ukuran lampiran paling besar '.config('sigap.lampiran.maks_total_mb').' MB.');
            }
        }];
    }

    /**
     * Solusi CAPTCHA baru ditandai terpakai setelah seluruh isian sah, sehingga pelapor yang salah
     * mengisi kolom lain bisa mengirim ulang tanpa memecahkan CAPTCHA lagi.
     */
    protected function passedValidation(): void
    {
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
            'email.required_without' => 'Isi surel atau nomor HP agar kami bisa menghubungi Anda.',
            'no_hp.required_without' => 'Isi nomor HP atau surel agar kami bisa menghubungi Anda.',
            'kontak_orang_tua.required' => 'Pelapor siswa wajib mengisi kontak orang tua/wali.',
            'persetujuan_orang_tua.accepted' => 'Orang tua/wali harus menyetujui pengaduan ini.',
            'setuju_ketentuan.accepted' => 'Anda perlu menyetujui ketentuan penggunaan dan kebijakan privasi.',
            'exists' => ':attribute yang dipilih tidak tersedia.',
            'enum' => ':attribute yang dipilih tidak tersedia.',
            'email' => 'Format surel tidak valid.',
            'regex' => 'Format :attribute tidak valid.',
            'max' => ':attribute paling banyak :max karakter.',
            'min' => ':attribute paling sedikit :min karakter.',
            'date' => ':attribute tidak valid.',
            'before_or_equal' => ':attribute tidak boleh di masa depan.',
            'situs_web.prohibited' => 'Pengiriman tidak dapat diproses.',
            'altcha.required' => 'Selesaikan verifikasi "Saya bukan robot" terlebih dahulu.',
            'lampiran.max' => 'Lampiran paling banyak :max berkas.',
            'lampiran.*.distinct' => 'Lampiran yang sama terkirim dua kali.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'unit_id' => 'Unit',
            'kategori_id' => 'Kategori',
            'jenis_pelapor' => 'Jenis pelapor',
            'nama' => 'Nama',
            'email' => 'Surel',
            'no_hp' => 'Nomor HP',
            'kontak_orang_tua' => 'Kontak orang tua/wali',
            'judul' => 'Judul',
            'kronologi' => 'Kronologi',
            'waktu_kejadian' => 'Waktu kejadian',
            'lokasi' => 'Lokasi',
            'jabatan_pihak_dikeluhkan' => 'Jabatan pihak yang dikeluhkan',
        ];
    }
}
