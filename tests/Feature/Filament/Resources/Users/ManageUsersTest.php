<?php

namespace Tests\Feature\Filament\Resources\Users;

use App\Enums\AksiAudit;
use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Exceptions\PelanggaranIntegritasData;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\LogAudit;
use App\Models\RiwayatPengaturan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengaturan\PengelolaDataJabatan;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManageUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();
    }

    public function test_super_admin_sees_office_holders_but_not_wbs_team_result_recipients_or_super_admins(): void
    {
        $kelolaan = collect(Peran::kelolaanPengaturan())->map(fn (Peran $peran): User => User::factory()->peran($peran)->create());
        $nonaktif = User::factory()->create(['peran' => null]);
        $tertutup = collect([Peran::KetuaTimWbs, Peran::AnggotaTimWbs, Peran::DewanPengawas, Peran::KetuaPembina, Peran::Pembina, Peran::KetuaUmum, Peran::PengurusHarian, Peran::SuperAdmin])
            ->map(fn (Peran $peran): User => User::factory()->peran($peran)->create());

        $this->actingAs($this->superAdmin);

        Livewire::test(ManageUsers::class)
            ->assertCanSeeTableRecords([...$kelolaan, $nonaktif])
            ->assertCanNotSeeTableRecords([...$tertutup, $this->superAdmin]);
    }

    public function test_super_admin_replaces_a_head_of_school_and_the_audit_keeps_old_and_new_values_without_the_password(): void
    {
        $unit = Unit::factory()->create(['nama' => 'SMP YPVDP']);

        $this->actingAs($this->superAdmin);

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: [
                'name' => 'Bu Sari Wulandari',
                'email' => 'sari@contoh.test',
                'peran' => Peran::KepalaSekolah->value,
                'unit_id' => $unit->id,
                'password' => 'KataSandiAwal-2026!',
            ])
            ->assertHasNoFormErrors();

        $kepala = User::query()->where('email', 'sari@contoh.test')->sole();
        $this->assertSame(Peran::KepalaSekolah, $kepala->peran);
        $this->assertSame($unit->id, $kepala->unit_id);
        $this->assertSame(JenisAkun::Lokal, $kepala->jenis_akun);
        $this->assertTrue(Hash::check('KataSandiAwal-2026!', $kepala->password));

        $log = LogAudit::query()->where('aksi', AksiAudit::MengubahPengaturan->value)->where('objek', "users:{$kepala->id}")->sole();
        $riwayat = $log->riwayatPengaturan()->pluck('nilai_baru', 'kolom')->all();
        $this->assertSame('Bu Sari Wulandari', $riwayat['nama']);
        $this->assertSame('sari@contoh.test', $riwayat['surel']);
        $this->assertSame('kepala_sekolah', $riwayat['peran']);
        $this->assertSame('SMP YPVDP', $riwayat['unit']);
        $this->assertSame(RiwayatPengaturan::NILAI_RAHASIA, $riwayat['kata_sandi']);
        $this->assertDatabaseMissing('riwayat_pengaturan', ['nilai_baru' => 'KataSandiAwal-2026!']);
        $this->assertDatabaseMissing('riwayat_pengaturan', ['nilai_baru' => $kepala->password]);
    }

    public function test_super_admin_changes_the_email_of_a_pic_and_the_old_value_is_recorded(): void
    {
        $pic = User::factory()->peran(Peran::PicUnit)->create(['name' => 'Pak Anto', 'email' => 'anto.lama@contoh.test']);

        $this->actingAs($this->superAdmin);

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($pic), data: ['email' => 'anto.baru@contoh.test'])
            ->assertHasNoFormErrors();

        $this->assertSame('anto.baru@contoh.test', $pic->refresh()->email);
        $this->assertDatabaseHas('riwayat_pengaturan', ['kolom' => 'surel', 'nilai_lama' => 'anto.lama@contoh.test', 'nilai_baru' => 'anto.baru@contoh.test']);
        $this->assertDatabaseMissing('riwayat_pengaturan', ['kolom' => 'kata_sandi']);
    }

    public function test_super_admin_deactivates_an_account_by_clearing_its_role(): void
    {
        $komite = User::factory()->peran(Peran::KomiteEtik)->denganMfa()->create();

        $this->actingAs($this->superAdmin);

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($komite), data: ['peran' => null])
            ->assertHasNoFormErrors();

        $this->assertNull($komite->refresh()->peran);
        $this->actingAs($komite)->get('/panel')->assertForbidden();
    }

    public function test_a_wbs_role_cannot_be_granted_through_settings(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create();

        $this->actingAs($this->superAdmin);

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($admin), data: ['peran' => Peran::KetuaTimWbs->value])
            ->assertHasFormErrors(['peran']);

        $this->assertSame(Peran::AdminPengaduan, $admin->refresh()->peran);

        $this->expectException(PelanggaranIntegritasData::class);
        app(PengelolaDataJabatan::class)->simpanPengguna($this->superAdmin, $admin, [
            'name' => $admin->name, 'email' => $admin->email, 'peran' => Peran::KetuaTimWbs,
        ]);
    }

    public function test_a_wbs_team_account_cannot_be_changed_through_settings(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->create(['email' => 'ketua@contoh.test']);

        $this->assertFalse($this->superAdmin->can('update', $ketua));

        $this->expectException(PelanggaranIntegritasData::class);

        try {
            app(PengelolaDataJabatan::class)->simpanPengguna($this->superAdmin, $ketua, [
                'name' => $ketua->name, 'email' => 'ambil.alih@contoh.test', 'peran' => Peran::AdminPengaduan,
            ]);
        } finally {
            $this->assertSame('ketua@contoh.test', $ketua->refresh()->email);
        }
    }

    public function test_an_sso_account_must_use_the_workspace_domain(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: [
                'name' => 'Admin Baru',
                'email' => 'admin@gmail.test',
                'peran' => Peran::AdminPengaduan->value,
                'jenis_akun' => JenisAkun::Sso->value,
            ])
            ->assertHasFormErrors(['email']);
    }

    public function test_super_admin_unlocks_a_locked_account_and_it_is_audited(): void
    {
        $pic = User::factory()->peran(Peran::PicUnit)->terkunci()->create();

        $this->actingAs($this->superAdmin);

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('bukaKunci')->table($pic));

        $this->assertFalse($pic->refresh()->terkunci());
        $this->assertDatabaseHas('log_audit', [
            'user_id' => $this->superAdmin->id,
            'aksi' => AksiAudit::KunciAkunDibuka->value,
            'objek' => "users:{$pic->id}",
        ]);
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

        $this->actingAs($user)->get('/panel/pengaturan/pengguna')->assertForbidden();
        $this->assertFalse($user->can('create', User::class));
    }
}
