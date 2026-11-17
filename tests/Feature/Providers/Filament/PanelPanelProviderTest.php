<?php

namespace Tests\Feature\Providers\Filament;

use App\Enums\Peran;
use App\Models\User;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as PenggunaGoogle;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Syarat persetujuan "login pertama tanpa 2FA": selama 2FA belum terpasang, semua halaman panel selain
 * halaman pemasangan 2FA dialihkan ke sana, termasuk setelah login SSO.
 */
class PanelPanelProviderTest extends TestCase
{
    use RefreshDatabase;

    private const HALAMAN_PASANG_2FA = '/panel/multi-factor-authentication/set-up';

    /**
     * Route yang memerlukan login tetapi harus tetap terbuka tanpa 2FA.
     */
    private const ROUTE_TANPA_2FA = [
        'filament.panel.auth.multi-factor-authentication.set-up-required',
        'filament.panel.auth.logout',
    ];

    public function test_every_signed_in_panel_route_except_2fa_setup_and_logout_requires_2fa(): void
    {
        $routeTerlindung = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'filament.panel.'))
            ->filter(fn ($route): bool => in_array(Authenticate::class, $route->gatherMiddleware(), true))
            ->reject(fn ($route): bool => in_array($route->getName(), self::ROUTE_TANPA_2FA, true));

        $routeTanpa2fa = $routeTerlindung
            ->reject(fn ($route): bool => in_array(EnsureMultiFactorAuthenticationIsEnabled::class, $route->gatherMiddleware(), true))
            ->map(fn ($route): string => $route->getName())
            ->values()
            ->all();

        $this->assertContains('filament.panel.pages.dashboard', $routeTerlindung->map->getName());
        $this->assertContains('filament.panel.resources.log-audit.index', $routeTerlindung->map->getName());
        $this->assertSame([], $routeTanpa2fa);
    }

    public function test_2fa_check_also_runs_on_livewire_requests_of_open_pages(): void
    {
        $this->assertContains(EnsureMultiFactorAuthenticationIsEnabled::class, Livewire::getPersistentMiddleware());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function halamanPanel(): array
    {
        return [
            'dashboard' => ['/panel'],
            'log audit' => ['/panel/log-audit'],
        ];
    }

    #[DataProvider('halamanPanel')]
    public function test_local_officer_without_2fa_is_redirected_to_2fa_setup(string $halaman): void
    {
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->create();

        $response = $this->actingAs($superAdmin)->get($halaman);

        $response->assertRedirect(self::HALAMAN_PASANG_2FA);
    }

    #[DataProvider('halamanPanel')]
    public function test_sso_officer_without_2fa_is_redirected_to_2fa_setup_after_google_login(string $halaman): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->sso()->create();
        Socialite::fake('google', PenggunaGoogle::fake([
            'email' => $admin->email,
            'hd' => 'vidatra.sch.id',
            'email_verified' => true,
        ]));
        $this->get(route('filament.panel.sso.google.callback'));
        $this->assertAuthenticatedAs($admin);

        $response = $this->get($halaman);

        $response->assertRedirect(self::HALAMAN_PASANG_2FA);
    }

    public function test_2fa_setup_page_opens_without_2fa(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->create();

        $response = $this->actingAs($user)->get(self::HALAMAN_PASANG_2FA);

        $response->assertOk();
    }

    public function test_officer_without_2fa_can_still_log_out(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->create();

        $response = $this->actingAs($user)->post('/panel/logout');

        $response->assertRedirect();
        $this->assertGuest();
    }
}
