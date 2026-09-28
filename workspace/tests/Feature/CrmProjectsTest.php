<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Attachment;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectDecision;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\PurchaseOrder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmProjectsTest extends TestCase
{
    use RefreshDatabase;

    private User $crm;

    private User $client;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crm = User::factory()->role('crm')->create();
        $this->client = User::factory()->role('client')->create(['name' => 'شركة النور']);
        $this->project = Project::factory()->forClient($this->client)->status(ProjectStatus::InProgress)->create([
            'name' => 'منصة النور',
            'description' => 'وصف المشروع المعلن للعميل.',
            'budget' => 987654,
        ]);
    }

    public function test_crm_lists_every_project_including_internal_ones(): void
    {
        Project::factory()->create(['name' => 'مسودة داخلية']);
        Project::factory()->forClient()->status(ProjectStatus::Completed)->create(['name' => 'مشروع منجز']);

        $this->actingAs($this->crm)->get(route('projects.index'))
            ->assertOk()
            ->assertSee('مشاريع العملاء')
            ->assertSee('منصة النور')
            ->assertSee('مسودة داخلية')
            ->assertSee('مشروع منجز')
            ->assertSee('شركة النور')
            ->assertDontSee('مشروع جديد')
            ->assertViewHas('projects', fn ($projects): bool => $projects->total() === 3);

        $this->actingAs($this->crm)->get(route('projects.index', ['status' => 'completed']))
            ->assertSee('مشروع منجز')
            ->assertDontSee('مسودة داخلية');
    }

    public function test_crm_opens_the_client_safe_view_of_any_project(): void
    {
        $internal = Project::factory()->create(['name' => 'مسودة داخلية']);

        $this->actingAs($this->crm)->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('منصة النور')
            ->assertSee('قيد التنفيذ')
            ->assertSee('وصف المشروع المعلن للعميل.')
            ->assertSee('العميل')
            ->assertSee('شركة النور')
            ->assertViewIs('projects.client-show');

        $this->actingAs($this->crm)->get(route('projects.show', $internal))->assertOk()->assertViewIs('projects.client-show');
    }

    public function test_the_crm_view_leaks_nothing_internal(): void
    {
        $member = User::factory()->role('team_member')->create(['name' => 'عضو الفريق السري']);
        ProjectMember::factory()->create(['project_id' => $this->project->id, 'user_id' => $member->id]);
        Task::factory()->create(['project_id' => $this->project->id, 'title' => 'مهمة داخلية سرية', 'assignee_id' => $member->id]);
        ProjectDecision::factory()->create(['project_id' => $this->project->id, 'note' => 'ملاحظة قرار داخلية']);
        Contract::factory()->active()->create(['project_id' => $this->project->id, 'title' => 'عقد سري القيمة']);
        PurchaseOrder::factory()->create(['project_id' => $this->project->id, 'vendor_name' => 'مورد سري']);
        Invoice::factory()->withAmount('1000.00')->issued()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->crm)->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertDontSee('عضو الفريق السري')
            ->assertDontSee('مهمة داخلية سرية')
            ->assertDontSee('ملاحظة قرار داخلية')
            ->assertDontSee('سجل القرارات')
            ->assertDontSee('عقد سري القيمة')
            ->assertDontSee('مورد سري')
            ->assertDontSee('987,654')
            ->assertDontSee('987654')
            ->assertDontSee('أوامر شراء ملتزَم بها');

        $this->actingAs($this->crm)->get(route('projects.index'))
            ->assertDontSee('مهمة داخلية سرية')
            ->assertDontSee('987,654');
    }

    public function test_crm_cannot_open_any_internal_page_of_a_project(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);
        $milestone = ProjectMilestone::factory()->create(['project_id' => $this->project->id]);
        $attachment = Attachment::factory()->create(['attachable_id' => $this->project->id]);

        $this->actingAs($this->crm);

        foreach (['projects.tasks.index', 'projects.tasks.create', 'projects.members.index', 'projects.milestones.index', 'projects.files.index'] as $route) {
            $this->get(route($route, $this->project))->assertForbidden();
        }
        $this->get(route('tasks.show', $task))->assertForbidden();
        $this->get(route('tasks.edit', $task))->assertForbidden();
        $this->get(route('attachments.show', $attachment))->assertForbidden();
        $this->put(route('projects.milestones.update', [$this->project, $milestone]), ['title' => 'عنوان جديد'])->assertForbidden();
    }

    public function test_crm_cannot_change_anything_on_a_project(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);
        $milestone = ProjectMilestone::factory()->create(['project_id' => $this->project->id]);
        $other = User::factory()->role('team_member')->create();

        $this->actingAs($this->crm);

        $this->get(route('projects.create'))->assertForbidden();
        $this->post(route('projects.store'), ['name' => 'مشروع جديد', 'priority' => 'normal'])->assertForbidden();
        $this->get(route('projects.edit', $this->project))->assertForbidden();
        $this->put(route('projects.update', $this->project), ['name' => 'اسم جديد', 'priority' => 'normal'])->assertForbidden();
        $this->delete(route('projects.destroy', $this->project))->assertForbidden();
        $this->post(route('projects.submit', $this->project))->assertForbidden();
        $this->post(route('projects.decide', $this->project), ['type' => 'on_hold', 'note' => 'إيقاف'])->assertForbidden();
        $this->post(route('projects.start', $this->project))->assertForbidden();
        $this->post(route('projects.complete', $this->project))->assertForbidden();
        $this->post(route('projects.members.store', $this->project), ['user_id' => $other->id, 'role' => 'member'])->assertForbidden();
        $this->post(route('projects.milestones.store', $this->project), ['title' => 'مرحلة جديدة'])->assertForbidden();
        $this->post(route('projects.milestones.toggle', [$this->project, $milestone]))->assertForbidden();
        $this->post(route('projects.tasks.store', $this->project), ['title' => 'مهمة', 'priority' => 'normal'])->assertForbidden();
        $this->patch(route('tasks.move', $task), ['status' => 'done'])->assertForbidden();
        $this->post(route('projects.files.store', $this->project))->assertForbidden();

        $project = $this->project->fresh();
        $this->assertSame('منصة النور', $project->name);
        $this->assertSame(ProjectStatus::InProgress, $project->status);
        $this->assertSame(0, $project->members()->count());
        $this->assertSame(1, $project->tasks()->count());
    }

    public function test_crm_sees_no_finance_hiring_hr_or_system_pages(): void
    {
        $contract = Contract::factory()->active()->create(['project_id' => $this->project->id]);
        $invoice = Invoice::factory()->withAmount('1000.00')->issued()->create(['project_id' => $this->project->id]);
        $order = PurchaseOrder::factory()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->crm);

        foreach (['contracts.index', 'invoices.index', 'purchase-orders.index', 'hiring-requests.index', 'hiring-requests.create', 'hr.employees', 'users.index', 'audit.index', 'system.health', 'approvals.projects', 'approvals.financial', 'team.index', 'decisions.index', 'reports.finance', 'reports.executive', 'reports.client', 'content.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->get(route('contracts.show', $contract))->assertForbidden();
        $this->get(route('invoices.show', $invoice))->assertForbidden();
        $this->get(route('purchase-orders.show', $order))->assertForbidden();
    }

    public function test_hr_and_crm_accounts_are_not_offered_as_project_team_members(): void
    {
        $hr = User::factory()->role('hr')->create();
        User::factory()->role('team_member')->create(['name' => 'زميل من الفريق']);
        $pm = $this->project->pm;

        $this->actingAs($pm)->get(route('projects.members.index', $this->project))
            ->assertOk()
            ->assertSee('زميل من الفريق')
            ->assertDontSee($this->crm->name)
            ->assertDontSee($hr->name);

        foreach ([$this->crm, $hr] as $account) {
            $this->actingAs($pm)->post(route('projects.members.store', $this->project), ['user_id' => $account->id, 'role' => 'member'])
                ->assertSessionHasErrors('user_id');
        }

        $this->assertSame(0, $this->project->members()->count());
    }
}
