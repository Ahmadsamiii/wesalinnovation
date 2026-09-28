<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\HiringRequest;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrCrmAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $allowed
     */
    private function assertOnlyRolesReach(string $route, array $allowed): void
    {
        foreach (array_keys(config('roles')) as $role) {
            $response = $this->actingAs(User::factory()->role($role)->create())->get(route($route));

            $this->assertSame(in_array($role, $allowed, true) ? 200 : 403, $response->getStatusCode(), "{$role} on {$route}");
        }
    }

    public function test_employees_page_matrix_over_every_role(): void
    {
        $this->assertOnlyRolesReach('hr.employees', ['hr', 'sysadmin']);
    }

    public function test_clients_page_matrix_over_every_role(): void
    {
        $this->assertOnlyRolesReach('crm.clients', ['crm', 'sysadmin']);
    }

    public function test_every_tab_of_the_new_roles_opens_for_its_own_role(): void
    {
        foreach (['hr', 'crm'] as $role) {
            $user = User::factory()->role($role)->create();

            foreach (config("roles.{$role}.tabs") as $key => $tab) {
                $this->assertSame(200, $this->actingAs($user)->get(route($tab['route']))->getStatusCode(), "{$role} tab {$key}");
            }
        }
    }

    public function test_hr_and_crm_reach_only_their_pages_over_the_wider_route_list(): void
    {
        $expected = [
            'hr' => [
                'hr.employees' => 200, 'hiring-requests.index' => 200, 'reference-letters.index' => 200, 'card.show' => 200,
                'crm.clients' => 403, 'projects.index' => 403, 'contracts.index' => 403, 'invoices.index' => 403,
                'purchase-orders.index' => 403, 'tasks.index' => 403, 'approvals.projects' => 403, 'approvals.financial' => 403,
                'decisions.index' => 403, 'team.index' => 403, 'reports.executive' => 403, 'reports.finance' => 403,
                'reports.pm' => 403, 'reports.client' => 403, 'users.index' => 403, 'audit.index' => 403, 'system.health' => 403,
                'content.index' => 403, 'medical.review' => 403,
            ],
            'crm' => [
                'crm.clients' => 200, 'projects.index' => 200, 'reference-letters.index' => 200, 'card.show' => 200,
                'hr.employees' => 403, 'hiring-requests.index' => 403, 'hiring-requests.create' => 403, 'contracts.index' => 403,
                'invoices.index' => 403, 'purchase-orders.index' => 403, 'tasks.index' => 403, 'approvals.projects' => 403,
                'approvals.financial' => 403, 'decisions.index' => 403, 'team.index' => 403, 'reports.executive' => 403,
                'reports.finance' => 403, 'reports.pm' => 403, 'reports.client' => 403, 'users.index' => 403, 'audit.index' => 403,
                'system.health' => 403, 'content.index' => 403, 'medical.review' => 403,
            ],
        ];

        foreach ($expected as $role => $routes) {
            $user = User::factory()->role($role)->create();

            foreach ($routes as $route => $status) {
                $this->assertSame($status, $this->actingAs($user)->get(route($route))->getStatusCode(), "{$role} on {$route}");
            }
        }
    }

    public function test_hr_sees_no_contract_invoice_purchase_order_or_project(): void
    {
        $hr = User::factory()->role('hr')->create();
        $project = Project::factory()->forClient()->create();
        $contract = Contract::factory()->active()->create(['project_id' => $project->id]);
        $invoice = Invoice::factory()->withAmount('1000.00')->issued()->create(['project_id' => $project->id]);
        $order = PurchaseOrder::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->actingAs($hr);

        $this->get(route('projects.show', $project))->assertForbidden();
        $this->get(route('projects.tasks.index', $project))->assertForbidden();
        $this->get(route('projects.files.index', $project))->assertForbidden();
        $this->get(route('tasks.show', $task))->assertForbidden();
        $this->get(route('contracts.show', $contract))->assertForbidden();
        $this->get(route('invoices.show', $invoice))->assertForbidden();
        $this->get(route('purchase-orders.show', $order))->assertForbidden();
        $this->post(route('projects.decide', $project), ['type' => 'on_hold'])->assertForbidden();
        $this->post(route('purchase-orders.decide', $order))->assertForbidden();
    }

    public function test_hr_and_crm_never_see_another_roles_navigation(): void
    {
        $hr = User::factory()->role('hr')->create();
        $crm = User::factory()->role('crm')->create();

        $this->actingAs($hr)->get(route('hr.employees'))
            ->assertDontSee('data-tab="clients"', false)
            ->assertDontSee('data-tab="client_projects"', false)
            ->assertDontSee('data-tab="invoices"', false)
            ->assertDontSee('data-tab="team"', false);

        $this->actingAs($crm)->get(route('crm.clients'))
            ->assertDontSee('data-tab="employees"', false)
            ->assertDontSee('data-tab="hiring_requests"', false)
            ->assertDontSee('data-tab="reference_letters"', false)
            ->assertDontSee('data-tab="invoices"', false)
            ->assertDontSee(route('hiring-requests.index'), false)
            ->assertDontSee('طلبات التوظيف');
    }

    public function test_the_sysadmin_role_list_offers_both_new_roles(): void
    {
        $sysadmin = User::factory()->role('sysadmin')->create();
        User::factory()->role('hr')->create();
        User::factory()->role('crm')->count(2)->create();

        $this->actingAs($sysadmin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('مدير الموارد البشرية')
            ->assertSee('مدير علاقات العملاء')
            ->assertSee('الموظفون، طلبات التوظيف، الإفادات الوظيفية')
            ->assertSee('العملاء، مشاريع العملاء');

        $this->actingAs($sysadmin)->get(route('users.index', ['role' => 'crm']))->assertOk()->assertSessionHasNoErrors();
    }

    public function test_the_executive_team_page_lists_the_new_roles_too(): void
    {
        $executive = User::factory()->role('executive')->create();
        User::factory()->role('hr')->create(['name' => 'منى الموارد']);
        User::factory()->role('crm')->create(['name' => 'ليلى العلاقات']);

        $this->actingAs($executive)->get(route('team.index'))
            ->assertOk()
            ->assertSee('منى الموارد')
            ->assertSee('ليلى العلاقات');
        $this->actingAs($executive)->get(route('team.index', ['role' => 'hr']))
            ->assertSee('منى الموارد')
            ->assertDontSee('ليلى العلاقات');
    }

    public function test_hiring_requests_stay_closed_to_crm_even_when_a_request_exists(): void
    {
        $crm = User::factory()->role('crm')->create();
        $hiring = HiringRequest::factory()->approved()->create();

        $this->actingAs($crm)->get(route('hiring-requests.show', $hiring))->assertForbidden();
        $this->actingAs($crm)->post(route('hiring-requests.fill', $hiring), ['note' => 'x'])->assertForbidden();
        $this->actingAs($crm)->post(route('hiring-requests.cancel', $hiring), ['reason' => 'x'])->assertForbidden();
    }
}
