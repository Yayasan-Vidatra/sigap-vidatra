<?php

namespace App\Filament\Support;

use App\Enums\Peran;
use App\Models\User;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatar akun berupa inisial di lingkaran (prototipe panel): biru #1E3A9E, atau biru tua #0B1B4D untuk Tim WBS.
 * Dibuat sebagai SVG di server, sehingga nama petugas tidak dikirim ke layanan avatar luar seperti bawaan Filament.
 */
class AvatarInisial implements AvatarProvider
{
    public function get(Model $record): string
    {
        $nama = $record instanceof User ? $record->name : Filament::getNameForDefaultAvatar($record);
        $warna = $record instanceof User && in_array($record->peran, [Peran::KetuaTimWbs, Peran::AnggotaTimWbs], true)
            ? '#0B1B4D'
            : '#1E3A9E';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80">'
            .'<rect width="80" height="80" rx="40" fill="'.$warna.'"/>'
            .'<text x="40" y="40" dy="0.35em" text-anchor="middle" fill="#ffffff" '
            .'font-family="Plus Jakarta Sans, system-ui, sans-serif" font-size="28" font-weight="700">'
            .e(self::inisial($nama)).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public static function inisial(string $nama): string
    {
        $kata = preg_split('/\s+/u', trim($nama), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];

        return mb_strtoupper(collect($kata)->take(2)->map(fn (string $bagian): string => mb_substr($bagian, 0, 1))->implode(''));
    }
}
