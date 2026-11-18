<?php

namespace App\Enums;

use App\Services\WaktuLokal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

/**
 * Tahap penanganan WBS yang memiliki tenggat hari kerja (config sigap.wbs).
 */
enum TahapWbs: string implements HasLabel
{
    case Telaah = 'telaah';
    case Investigasi = 'investigasi';
    case Perpanjangan = 'perpanjangan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Telaah => 'Telaah',
            self::Investigasi => 'Investigasi',
            self::Perpanjangan => 'Perpanjangan investigasi',
        };
    }

    public function hariKerja(): int
    {
        return (int) config("sigap.wbs.{$this->value}_hari_kerja");
    }

    /**
     * Batas waktu tahap ini dihitung dari awal tahap (Senin–Jumat menurut tanggal WITA; libur nasional belum dihitung).
     */
    public function batasDari(CarbonInterface $mulai): CarbonImmutable
    {
        return WaktuLokal::tambahHariKerja($mulai, $this->hariKerja());
    }
}
