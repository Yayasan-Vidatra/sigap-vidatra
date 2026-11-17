<?php

namespace Tests\Feature;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_beranda_renders_the_inertia_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Beranda'));
    }

    public function test_guest_is_redirected_from_panel_to_login(): void
    {
        $response = $this->get('/panel');

        $response->assertRedirect('/panel/login');
    }

    public function test_panel_login_page_is_accessible(): void
    {
        $response = $this->get('/panel/login');

        $response->assertOk();
    }

    public function test_user_without_peran_is_forbidden_from_panel(): void
    {
        $tanpaPeran = User::factory()->denganMfa()->create();

        $response = $this->actingAs($tanpaPeran)->get('/panel');

        $response->assertForbidden();
    }

    public function test_user_with_peran_can_open_panel(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();

        $response = $this->actingAs($admin)->get('/panel');

        $response->assertOk();
    }
}
