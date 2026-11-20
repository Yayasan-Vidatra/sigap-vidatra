<?php

use App\Models\Pengaturan;
use Database\Seeders\KontenPublikSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Sidik jari SHA-256 FAQ dan Kebijakan Privasi draf awal sebelum revisi 20-11 (masih menyebut WBS dan mode anonim).
     */
    private const DRAF_LAMA = [
        Pengaturan::PUBLIK_FAQ => '9f8fe831f9a2f4ac2bed780fd1adb2b0889c5fc82a936e515de0111056143e9b',
        Pengaturan::PUBLIK_PRIVASI => '77f1117cd813f19b0ee1b5b0a5da94db0d6b9216dacbbd6507e393ff74d97e85',
    ];

    /**
     * Revisi 20-11 (WBS hanya internal): FAQ dan Kebijakan Privasi diganti dengan draf baru tanpa WBS, hanya bila isinya
     * masih sama persis dengan draf awal. Teks yang sudah diubah Super Admin tidak disentuh dan perlu disunting di
     * Pengaturan > Konten Publik. Unit PT Vidatra Jaya Makmur dan petunjuk kode akses ditambahkan lewat seeder
     * (UnitSeeder, KontenPublikSeeder), bukan di sini.
     */
    public function up(): void
    {
        $baru = [
            Pengaturan::PUBLIK_FAQ => KontenPublikSeeder::FAQ,
            Pengaturan::PUBLIK_PRIVASI => KontenPublikSeeder::PRIVASI,
        ];

        foreach (self::DRAF_LAMA as $kunci => $sidikJari) {
            $nilai = Pengaturan::query()->where('kunci', $kunci)->value('nilai');

            if (is_string($nilai) && hash_equals($sidikJari, hash('sha256', $nilai))) {
                Pengaturan::query()->where('kunci', $kunci)->update([
                    'nilai' => json_encode($baru[$kunci], JSON_UNESCAPED_UNICODE),
                ]);
            }
        }
    }

    /**
     * Teks lama tidak dikembalikan; isi Konten Publik dikelola di Pengaturan.
     */
    public function down(): void {}
};
