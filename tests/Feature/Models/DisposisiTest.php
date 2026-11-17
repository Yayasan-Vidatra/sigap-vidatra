<?php

namespace Tests\Feature\Models;

use App\Enums\Peran;
use App\Exceptions\PelanggaranIntegritasData;
use App\Models\Disposisi;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisposisiTest extends TestCase
{
    use RefreshDatabase;

    public function test_disposisi_of_a_wbs_laporan_is_rejected(): void
    {
        $wbs = Laporan::factory()->wbs()->create();

        $this->expectException(PelanggaranIntegritasData::class);

        try {
            Disposisi::factory()->for($wbs)->create();
        } finally {
            $this->assertDatabaseCount('disposisi', 0);
        }
    }

    public function test_disposisi_belongs_to_laporan_unit_and_pic(): void
    {
        $unit = Unit::factory()->create();
        $pic = User::factory()->peran(Peran::PicUnit)->for($unit)->create();
        $laporan = Laporan::factory()->for($unit)->create();

        $disposisi = Disposisi::factory()->for($laporan)->for($unit)->create(['pic_user_id' => $pic->id]);

        $this->assertTrue($disposisi->laporan->is($laporan));
        $this->assertTrue($disposisi->unit->is($unit));
        $this->assertTrue($disposisi->pic->is($pic));
        $this->assertSame([$disposisi->id], $pic->disposisi->modelKeys());
    }
}
