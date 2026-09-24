<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\ProjectDecisionType;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    private User $executive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pm = User::factory()->role('pm')->create();
        $this->executive = User::factory()->role('executive')->create();
    }

    public function test_pm_creates_a_draft_they_manage(): void
    {
        $client = User::factory()->role('client')->create();

        $response = $this->actingAs($this->pm)->post(route('projects.store'), [
            'name' => 'منصة التدريب',
            'client_id' => $client->id,
            'priority' => 'high',
            'budget' => '150000',
            'start_date' => '2026-10-01',
            'end_date' => '2027-01-31',
        ]);

        $project = Project::sole();
        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame(ProjectStatus::Draft, $project->status);
        $this->assertSame($this->pm->id, $project->pm_id);
        $this->assertSame($this->pm->id, $project->created_by);
        $this->assertSame($client->id, $project->client_id);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::ProjectCreated->value, 'subject_id' => $project->id]);
    }

    public function test_only_the_executive_chooses_the_project_manager(): void
    {
        $otherPm = User::factory()->role('pm')->create();

        $this->actingAs($this->pm)
            ->post(route('projects.store'), ['name' => 'س', 'priority' => 'normal', 'pm_id' => $otherPm->id])
            ->assertSessionHasErrors('pm_id');

        $this->actingAs($this->executive)
            ->post(route('projects.store'), ['name' => 'س', 'priority' => 'normal'])
            ->assertSessionHasErrors('pm_id');

        $this->actingAs($this->executive)
            ->post(route('projects.store'), ['name' => 'س', 'priority' => 'normal', 'pm_id' => $otherPm->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($otherPm->id, Project::sole()->pm_id);
    }

    public function test_client_and_team_member_cannot_create_projects(): void
    {
        foreach (['client', 'team_member', 'finance'] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get(route('projects.create'))->assertForbidden();
        }
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $this->actingAs($this->pm)->post(route('projects.store'), [
            'name' => 'س', 'priority' => 'normal', 'start_date' => '2026-10-10', 'end_date' => '2026-10-01',
        ])->assertSessionHasErrors('end_date');
    }

    public function test_full_approval_lifecycle(): void
    {
        $project = Project::factory()->create(['pm_id' => $this->pm->id]);

        $this->actingAs($this->pm)->post(route('projects.submit', $project))->assertSessionHas('status');
        $this->assertSame(ProjectStatus::PendingApproval, $project->fresh()->status);
        $this->assertNotNull($project->fresh()->submitted_at);

        $this->actingAs($this->executive)->post(route('projects.decide', $project), ['type' => 'approved'])->assertSessionHas('status');
        $this->assertSame(ProjectStatus::Approved, $project->fresh()->status);
        $this->assertDatabaseHas('project_decisions', ['project_id' => $project->id, 'type' => 'approved', 'decided_by' => $this->executive->id]);

        $this->actingAs($this->pm)->post(route('projects.start', $project));
        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);
        $this->assertTrue($project->fresh()->actual_start_date->isToday());

        $this->actingAs($this->pm)->post(route('projects.complete', $project));
        $this->assertSame(ProjectStatus::Completed, $project->fresh()->status);
        $this->assertTrue($project->fresh()->actual_end_date->isToday());
    }

    public function test_only_the_projects_own_manager_submits_it(): void
    {
        $project = Project::factory()->create(['pm_id' => $this->pm->id]);
        $otherPm = User::factory()->role('pm')->create();

        $this->actingAs($otherPm)->post(route('projects.submit', $project))->assertForbidden();
        $this->assertSame(ProjectStatus::Draft, $project->fresh()->status);
    }

    public function test_only_the_executive_decides(): void
    {
        $project = Project::factory()->status(ProjectStatus::PendingApproval)->create(['pm_id' => $this->pm->id]);

        $this->actingAs($this->pm)->post(route('projects.decide', $project), ['type' => 'approved'])->assertForbidden();
        $this->assertSame(ProjectStatus::PendingApproval, $project->fresh()->status);
    }

    public function test_rejection_needs_a_reason_and_the_project_can_be_resubmitted(): void
    {
        $project = Project::factory()->status(ProjectStatus::PendingApproval)->create(['pm_id' => $this->pm->id]);

        $this->actingAs($this->executive)
            ->post(route('projects.decide', $project), ['type' => 'rejected'])
            ->assertSessionHasErrors('note');
        $this->assertSame(ProjectStatus::PendingApproval, $project->fresh()->status);

        $this->actingAs($this->executive)->post(route('projects.decide', $project), ['type' => 'rejected', 'note' => 'الميزانية غير مبررة']);
        $this->assertSame(ProjectStatus::Rejected, $project->fresh()->status);

        $this->actingAs($this->pm)->post(route('projects.submit', $project));
        $this->actingAs($this->executive)->post(route('projects.decide', $project), ['type' => 'approved']);

        // التاريخ كامل: الرفض ثم الاعتماد، لا آخر قرار وحده.
        $this->assertSame(['approved', 'rejected'], $project->decisions()->pluck('type')->map->value->all());
    }

    public function test_a_decision_is_refused_from_the_wrong_status(): void
    {
        $project = Project::factory()->create(['pm_id' => $this->pm->id]);

        $this->actingAs($this->executive)->post(route('projects.decide', $project), ['type' => 'approved'])->assertForbidden();
        $this->assertSame(ProjectStatus::Draft, $project->fresh()->status);
    }

    public function test_hold_stops_task_work_and_resume_returns_to_progress(): void
    {
        $project = Project::factory()->status(ProjectStatus::InProgress)->create(['pm_id' => $this->pm->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $this->pm->id]);

        $this->actingAs($this->executive)->post(route('projects.decide', $project), ['type' => 'on_hold', 'note' => 'انتظار التمويل']);
        $this->assertSame(ProjectStatus::OnHold, $project->fresh()->status);

        $this->actingAs($this->pm)->patch(route('tasks.move', $task), ['status' => 'in_progress'])->assertForbidden();

        $this->actingAs($this->executive)->post(route('projects.decide', $project), ['type' => 'resumed']);
        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);

        $this->actingAs($this->pm)->patch(route('tasks.move', $task), ['status' => 'in_progress'])->assertSessionHas('status');
    }

    public function test_resuming_a_project_that_never_started_returns_it_to_approved(): void
    {
        $project = Project::factory()->status(ProjectStatus::Approved)->create(['pm_id' => $this->pm->id]);

        $project->decide(ProjectDecisionType::OnHold, $this->executive, 'مراجعة');
        $project->decide(ProjectDecisionType::Resumed, $this->executive);

        $this->assertSame(ProjectStatus::Approved, $project->fresh()->status);
    }

    public function test_completing_with_open_tasks_requires_confirmation(): void
    {
        $project = Project::factory()->status(ProjectStatus::InProgress)->create(['pm_id' => $this->pm->id]);
        Task::factory()->create(['project_id' => $project->id]);

        $this->actingAs($this->pm)->post(route('projects.complete', $project))->assertSessionHas('error');
        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);

        $this->actingAs($this->pm)->post(route('projects.complete', $project), ['confirm_open_tasks' => '1']);
        $this->assertSame(ProjectStatus::Completed, $project->fresh()->status);
    }

    public function test_closed_projects_are_read_only(): void
    {
        $project = Project::factory()->status(ProjectStatus::Completed)->create(['pm_id' => $this->pm->id]);

        $this->actingAs($this->pm)->get(route('projects.edit', $project))->assertForbidden();
        $this->actingAs($this->pm)->post(route('projects.milestones.store', $project), ['title' => 'x'])->assertForbidden();
        $this->actingAs($this->pm)->get(route('projects.tasks.create', $project))->assertForbidden();
    }

    public function test_executive_can_cancel_an_open_project_with_a_reason(): void
    {
        $project = Project::factory()->status(ProjectStatus::InProgress)->create(['pm_id' => $this->pm->id]);

        $this->actingAs($this->executive)->post(route('projects.decide', $project), ['type' => 'cancelled', 'note' => 'انسحاب العميل']);

        $this->assertSame(ProjectStatus::Cancelled, $project->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::ProjectDecided->value, 'subject_id' => $project->id]);
    }

    public function test_only_an_unsubmitted_draft_can_be_deleted(): void
    {
        $draft = Project::factory()->create(['pm_id' => $this->pm->id]);
        Task::factory()->create(['project_id' => $draft->id]);

        $this->actingAs($this->pm)->delete(route('projects.destroy', $draft))->assertRedirect(route('projects.index'));
        $this->assertModelMissing($draft);
        $this->assertDatabaseCount('tasks', 0);

        $submitted = Project::factory()->status(ProjectStatus::Rejected)->create(['pm_id' => $this->pm->id]);
        $this->actingAs($this->pm)->delete(route('projects.destroy', $submitted))->assertForbidden();
    }

    public function test_project_page_shows_the_next_step_to_each_role(): void
    {
        $project = Project::factory()->status(ProjectStatus::PendingApproval)->create(['pm_id' => $this->pm->id]);

        $this->actingAs($this->executive)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('القرار التنفيذي');

        $this->actingAs($this->pm)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('بانتظار قرار المدير التنفيذي')
            ->assertDontSee('القرار التنفيذي');
    }

    public function test_task_statuses_are_counted_on_the_overview(): void
    {
        $project = Project::factory()->status(ProjectStatus::InProgress)->create(['pm_id' => $this->pm->id]);
        Task::factory()->count(3)->status(TaskStatus::Done)->create(['project_id' => $project->id]);
        Task::factory()->create(['project_id' => $project->id]);

        $this->assertSame(75, $project->progress());
    }
}
