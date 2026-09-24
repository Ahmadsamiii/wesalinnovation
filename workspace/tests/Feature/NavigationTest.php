<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_tab_route_in_the_roles_config_is_parameterless(): void
    {
        // التبويب يُبنى بـ route($name) بلا معاملات؛ مسار يحتاج معاملاً يكسر الشريط كله.
        foreach (config('roles') as $role) {
            foreach ($role['tabs'] as $key => $tab) {
                $this->assertMatchesRegularExpression('/^[a-z][a-z.-]*$/', $tab['route'], "tab {$key}");
            }
        }
    }

    public function test_unbuilt_tab_opens_a_pending_page_for_its_own_role(): void
    {
        $medical = User::factory()->role('medical')->create();

        $response = $this->actingAs($medical)->get(route('sections.show', 'content_review'));

        $response->assertOk();
        $response->assertSee('قائمة مراجعة المحتوى الصحي');
        $response->assertSee('قيد البناء');
        $response->assertSee('aria-current="page"', false);
    }

    public function test_a_tab_of_another_role_is_not_found(): void
    {
        $client = User::factory()->role('client')->create();

        $this->actingAs($client)->get(route('sections.show', 'projects_approval'))->assertNotFound();
    }

    public function test_pending_section_redirects_once_its_route_exists(): void
    {
        $sysadmin = User::factory()->role('sysadmin')->create();

        $this->actingAs($sysadmin)
            ->get(route('sections.show', 'roles_permissions'))
            ->assertRedirect(route('users.index'));
    }

    public function test_active_tab_is_marked_for_screen_readers(): void
    {
        $sysadmin = User::factory()->role('sysadmin')->create();

        $response = $this->actingAs($sysadmin)->get(route('users.index'));

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('users.index'), '/').'"\s+aria-current="page"/',
            $response->getContent(),
        );
    }
}
