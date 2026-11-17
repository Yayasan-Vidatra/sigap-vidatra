<?php

namespace Tests\Unit\Enums;

use App\Enums\Peran;
use PHPUnit\Framework\TestCase;

class PeranTest extends TestCase
{
    public function test_only_admin_pengaduan_and_pic_unit_may_use_sso(): void
    {
        $bolehSso = array_values(array_filter(Peran::cases(), fn (Peran $peran): bool => $peran->bolehSso()));

        $this->assertSame([Peran::AdminPengaduan, Peran::PicUnit], $bolehSso);
    }

    public function test_only_pic_unit_and_kepala_sekolah_are_bound_to_a_unit(): void
    {
        $terikat = array_values(array_filter(Peran::cases(), fn (Peran $peran): bool => $peran->terikatUnit()));

        $this->assertSame([Peran::PicUnit, Peran::KepalaSekolah], $terikat);
    }

    public function test_maps_each_peran_to_its_actor_code(): void
    {
        $this->assertSame([
            'super_admin' => null,
            'admin_pengaduan' => 'A3',
            'pic_unit' => 'A4',
            'ketua_tim_wbs' => 'A5',
            'anggota_tim_wbs' => 'A5',
            'dewan_pengawas' => 'A6',
            'ketua_pembina' => 'A6',
            'pembina' => 'A6',
            'ketua_umum' => 'A7',
            'pengurus_harian' => 'A7',
            'kepala_sekolah' => 'A8',
            'komite_etik' => 'A8',
        ], array_combine(
            array_column(Peran::cases(), 'value'),
            array_map(fn (Peran $peran): ?string => $peran->aktor(), Peran::cases()),
        ));
    }
}
