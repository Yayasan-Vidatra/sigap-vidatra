<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Jenis lampiran menurut Rancangan r1 Bagian 8. Batas ukuran, MIME, dan kanal ada di
 * config sigap.lampiran.jenis.
 */
enum JenisBerkas: string implements HasLabel
{
    case Dokumen = 'dokumen';
    case Foto = 'foto';
    case Video = 'video';
    case Audio = 'audio';
    case Arsip = 'arsip';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dokumen => 'Dokumen',
            self::Foto => 'Foto',
            self::Video => 'Video',
            self::Audio => 'Audio',
            self::Arsip => 'ZIP',
        };
    }

    public function maksByte(): int
    {
        return (int) config("sigap.lampiran.jenis.{$this->value}.maks_mb") * 1024 * 1024;
    }

    /**
     * @return list<string>
     */
    public function daftarMime(): array
    {
        return config("sigap.lampiran.jenis.{$this->value}.mime");
    }

    public function bolehUntuk(JenisLaporan $kanal): bool
    {
        return in_array($kanal->value, config("sigap.lampiran.jenis.{$this->value}.kanal"), true);
    }

    public static function dariMime(string $mime): ?self
    {
        foreach (self::cases() as $jenis) {
            if (in_array($mime, $jenis->daftarMime(), true)) {
                return $jenis;
            }
        }

        return null;
    }
}
