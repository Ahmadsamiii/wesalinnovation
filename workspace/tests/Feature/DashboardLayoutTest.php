<?php

namespace Tests\Feature;

use App\Models\HiringRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_links_every_tab_of_the_users_role_to_its_page(): void
    {
        $user = User::factory()->role('finance')->create();

        $response = $this->actingAs($user)->get(route('invoices.index'));

        $response->assertOk();
        foreach (config('roles.finance.tabs') as $key => $tab) {
            $response->assertSee('href="'.route($tab['route']).'"', false);
            $response->assertSee('data-tab="'.$key.'"', false);
        }
    }

    public function test_sidebar_never_lists_another_roles_tab_keys(): void
    {
        $client = User::factory()->role('client')->create();

        $response = $this->actingAs($client)->get(route('projects.index'));

        $response->assertOk();
        foreach (['projects_approval', 'financial_approvals', 'roles_permissions', 'invoices'] as $foreignKey) {
            $response->assertDontSee('data-tab="'.$foreignKey.'"', false);
        }
    }

    public function test_the_current_section_is_marked_in_the_sidebar_and_named_in_the_top_bar(): void
    {
        $finance = User::factory()->role('finance')->create();

        $response = $this->actingAs($finance)->get(route('reports.finance'));

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('reports.finance'), '/').'"\s+aria-current="page"/', $response->getContent());
        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'));
        $response->assertSee('<p class="min-w-0 flex-1 truncate text-base font-bold text-brand-ink sm:text-lg">التقارير المالية</p>', false);
    }

    public function test_shell_renders_collapse_toggle_mobile_menu_and_logout(): void
    {
        $user = User::factory()->role('pm')->create();

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertOk();
        $response->assertSee('id="app-sidebar"', false);
        $response->assertSee('@click="toggleCollapsed()"', false);
        $response->assertSee('@click="openMobile()"', false);
        $response->assertSee('action="'.route('logout').'"', false);
    }

    public function test_services_outside_the_roles_tabs_sit_under_their_own_heading(): void
    {
        $pm = User::factory()->role('pm')->create();

        $this->actingAs($pm)->get(route('projects.index'))
            ->assertSeeInOrder(['خدمات', 'طلب إفادة', 'طلبات التوظيف'])
            ->assertSee('data-tab="hiring_requests"', false);

        // عضو الفريق «طلب إفادة» تبويب عنده أصلاً، فلا يتكرر في الخدمات.
        $member = User::factory()->role('team_member')->create();
        $this->assertSame(1, substr_count($this->actingAs($member)->get(route('tasks.mine'))->getContent(), 'href="'.route('reference-letters.index').'"'));
    }

    public function test_sidebar_links_every_tab_of_hr_and_crm_and_marks_one_current_page(): void
    {
        foreach (['hr' => 'hr.employees', 'crm' => 'crm.clients'] as $role => $landing) {
            $response = $this->actingAs(User::factory()->role($role)->create())->get(route($landing));

            $response->assertOk();
            foreach (config("roles.{$role}.tabs") as $key => $tab) {
                $response->assertSee('href="'.route($tab['route']).'"', false);
                $response->assertSee('data-tab="'.$key.'"', false);
            }
            $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route($landing), '/').'"\s+aria-current="page"/', $response->getContent());
            $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'));
        }
    }

    public function test_hr_tab_stays_current_inside_its_section_and_tabs_are_not_repeated_as_services(): void
    {
        $hr = User::factory()->role('hr')->create();
        $employee = User::factory()->role('team_member')->create();
        $hiring = HiringRequest::factory()->create();

        $edit = $this->actingAs($hr)->get(route('hr.employees.edit', $employee));
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('hr.employees'), '/').'"\s+aria-current="true"/', $edit->getContent());
        $edit->assertDontSee('خدمات');

        $show = $this->actingAs($hr)->get(route('hiring-requests.show', $hiring));
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('hiring-requests.index'), '/').'"\s+aria-current="true"/', $show->getContent());
        $this->assertSame(1, substr_count($show->getContent(), 'data-tab="hiring_requests"'));
    }

    public function test_crm_keeps_its_projects_tab_current_on_a_project_page_and_gets_only_the_letter_service(): void
    {
        $crm = User::factory()->role('crm')->create();
        $project = Project::factory()->create();

        $response = $this->actingAs($crm)->get(route('projects.show', $project));

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('projects.index'), '/').'"\s+aria-current="true"/', $response->getContent());
        $response->assertSeeInOrder(['خدمات', 'طلب إفادة'])
            ->assertDontSee('data-tab="hiring_requests"', false);
    }

    public function test_profile_page_keeps_the_role_sidebar_and_marks_profile_active(): void
    {
        $user = User::factory()->role('medical')->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        foreach (config('roles.medical.tabs') as $tab) {
            $response->assertSee($tab['label']);
            $response->assertSee('href="'.route($tab['route']).'"', false);
        }
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('profile.edit'), '/').'"\s+aria-current="page"/', $response->getContent());
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
