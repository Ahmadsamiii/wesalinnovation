<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_sidebar_links_every_tab_of_the_users_role_to_the_dashboard(): void
    {
        $user = User::factory()->create()->assignRole('finance');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        foreach (array_keys(config('roles.finance.tabs')) as $key) {
            $response->assertSee('href="'.route('dashboard').'#'.$key.'"', false);
            $response->assertSee('data-tab="'.$key.'"', false);
        }
    }

    public function test_sidebar_never_lists_another_roles_tab_keys(): void
    {
        $client = User::factory()->create()->assignRole('client');

        $response = $this->actingAs($client)->get('/dashboard');

        $response->assertOk();
        foreach (['projects_approval', 'financial_approvals', 'roles_permissions', 'invoices'] as $foreignKey) {
            $response->assertDontSee('data-tab="'.$foreignKey.'"', false);
        }
    }

    public function test_shell_renders_collapse_toggle_mobile_menu_and_logout(): void
    {
        $user = User::factory()->create()->assignRole('pm');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('id="app-sidebar"', false);
        $response->assertSee('@click="toggleCollapsed()"', false);
        $response->assertSee('@click="openMobile()"', false);
        $response->assertSee('action="'.route('logout').'"', false);
    }

    public function test_profile_page_keeps_the_role_sidebar_and_marks_profile_active(): void
    {
        $user = User::factory()->create()->assignRole('medical');

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        foreach (config('roles.medical.tabs') as $key => $label) {
            $response->assertSee($label);
            $response->assertSee('href="'.route('dashboard').'#'.$key.'"', false);
        }
        $response->assertSee('aria-current="page"', false);
    }

    public function test_user_without_role_gets_only_the_dashboard_link_in_the_sidebar(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('data-tab=', false);
        $response->assertSee('لا يوجد دور مُسنَد لحسابك بعد');
    }
}
