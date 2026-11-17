<?php

namespace Tests\Feature\Models;

use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_pic_unit_belongs_to_its_unit(): void
    {
        $unit = Unit::factory()->create();

        $pic = User::factory()->peran(Peran::PicUnit)->for($unit)->create();

        $this->assertTrue($pic->fresh()->unit->is($unit));
        $this->assertSame([$pic->id], $unit->users->modelKeys());
        $this->assertSame(Peran::PicUnit, $pic->fresh()->peran);
    }

    public function test_new_user_has_no_peran_and_a_local_account(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->fresh()->peran);
        $this->assertSame(JenisAkun::Lokal, $user->fresh()->jenis_akun);
    }
}
