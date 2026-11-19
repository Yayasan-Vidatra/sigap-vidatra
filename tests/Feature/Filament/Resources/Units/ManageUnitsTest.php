<?php

namespace Tests\Feature\Filament\Resources\Units;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Filament\Resources\Units\Pages\ManageUnits;
use App\Models\LogAudit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengaturan\PengelolaDataJabatan;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManageUnitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_changes_the_emergency_contact_and_the_change_is_audited_with_old_and_new_value(): void
    {
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();
        $unit = Unit::factory()->create(['nama' => 'SD YPVDP', 'kontak_darurat' => '0812-1111-1111']);

        $this->actingAs($superAdmin);

        Livewire::test(ManageUnits::class)
            ->callAction(TestAction::make('edit')->table($unit), data: [
                'nama' => 'SD YPVDP',
                'kontak_darurat' => '0812-2222-2222',
                'aktif' => true,
            ])
            ->assertHasNoFormErrors();

        $this->assertSame('0812-2222-2222', $unit->refresh()->kontak_darurat);

        $log = LogAudit::query()->where('aksi', AksiAudit::MengubahPengaturan->value)->sole();
        $this->assertSame("units:{$unit->id}", $log->objek);
        $this->assertSame($superAdmin->id, $log->user_id);
        $this->assertSame(
            [['kolom' => 'kontak_darurat', 'nilai_lama' => '0812-1111-1111', 'nilai_baru' => '0812-2222-2222']],
            $log->riwayatPengaturan()->get(['kolom', 'nilai_lama', 'nilai_baru'])->toArray(),
        );
    }

    public function test_super_admin_adds_a_unit(): void
    {
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();

        $this->actingAs($superAdmin);

        Livewire::test(ManageUnits::class)
            ->callAction('create', data: ['nama' => 'TK YPVDP', 'kontak_darurat' => null, 'aktif' => true])
            ->assertHasNoFormErrors();

        $unit = Unit::query()->where('nama', 'TK YPVDP')->sole();
        $this->assertDatabaseHas('log_audit', ['aksi' => AksiAudit::MengubahPengaturan->value, 'objek' => "units:{$unit->id}"]);
    }

    public function test_saving_without_changes_writes_no_audit_row(): void
    {
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();
        $unit = Unit::factory()->create(['kontak_darurat' => '0812-1111-1111']);

        $this->actingAs($superAdmin);

        Livewire::test(ManageUnits::class)
            ->callAction(TestAction::make('edit')->table($unit), data: [])
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('log_audit', ['aksi' => AksiAudit::MengubahPengaturan->value]);
    }

    public function test_units_cannot_be_deleted(): void
    {
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();
        $unit = Unit::factory()->create();

        $this->actingAs($superAdmin);

        Livewire::test(ManageUnits::class)->assertActionDoesNotExist(TestAction::make('delete')->table($unit));
        $this->assertFalse($superAdmin->can('delete', $unit));
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranSelainSuperAdmin(): array
    {
        return collect(Peran::cases())
            ->reject(fn (Peran $peran): bool => $peran === Peran::SuperAdmin)
            ->mapWithKeys(fn (Peran $peran): array => [$peran->value => [$peran]])
            ->all();
    }

    #[DataProvider('peranSelainSuperAdmin')]
    public function test_other_peran_are_forbidden_even_by_direct_url(Peran $peran): void
    {
        $user = User::factory()->peran($peran)->denganMfa()->create();

        $this->actingAs($user)->get('/panel/pengaturan/unit')->assertForbidden();
    }

    public function test_other_peran_cannot_call_the_service_directly(): void
    {
        $pic = User::factory()->peran(Peran::PicUnit)->denganMfa()->create();
        $unit = Unit::factory()->create(['kontak_darurat' => '0812-1111-1111']);

        $this->expectException(AuthorizationException::class);

        try {
            app(PengelolaDataJabatan::class)->simpanUnit($pic, $unit, ['nama' => $unit->nama, 'kontak_darurat' => '0000']);
        } finally {
            $this->assertSame('0812-1111-1111', $unit->refresh()->kontak_darurat);
        }
    }
}
