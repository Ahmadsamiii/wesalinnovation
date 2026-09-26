<?php

namespace Tests\Feature;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    private User $member;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pm = User::factory()->role('pm')->create();
        $this->member = User::factory()->role('team_member')->create();
        $this->project = Project::factory()->status(ProjectStatus::InProgress)->create(['pm_id' => $this->pm->id]);
        ProjectMember::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->member->id]);
    }

    public function test_pm_creates_a_task_for_a_team_member(): void
    {
        $milestone = ProjectMilestone::factory()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->pm)->post(route('projects.tasks.store', $this->project), [
            'title' => 'تصميم الواجهة',
            'assignee_id' => $this->member->id,
            'milestone_id' => $milestone->id,
            'priority' => 'high',
            'due_date' => now()->addWeek()->toDateString(),
        ])->assertRedirect(route('projects.tasks.index', $this->project));

        $task = Task::sole();
        $this->assertSame($this->member->id, $task->assignee_id);
        $this->assertSame($this->pm->id, $task->created_by);
        $this->assertSame(TaskStatus::Todo, $task->status);
    }

    public function test_tasks_can_only_go_to_the_team_and_to_this_projects_milestones(): void
    {
        $outsider = User::factory()->role('team_member')->create();
        $foreignMilestone = ProjectMilestone::factory()->create();

        $this->actingAs($this->pm)->post(route('projects.tasks.store', $this->project), [
            'title' => 'مهمة',
            'priority' => 'normal',
            'assignee_id' => $outsider->id,
            'milestone_id' => $foreignMilestone->id,
        ])->assertSessionHasErrors(['assignee_id', 'milestone_id']);
    }

    public function test_deactivated_members_are_not_offered_new_tasks(): void
    {
        $this->member->forceFill(['deactivated_at' => now()])->save();

        $this->actingAs($this->pm)->post(route('projects.tasks.store', $this->project), [
            'title' => 'مهمة', 'priority' => 'normal', 'assignee_id' => $this->member->id,
        ])->assertSessionHasErrors('assignee_id');
    }

    public function test_board_checks_permissions_without_a_query_per_card(): void
    {
        Task::factory()->count(12)->create(['project_id' => $this->project->id, 'assignee_id' => $this->member->id]);

        DB::enableQueryLog();
        $this->actingAs($this->member)->get(route('projects.tasks.index', $this->project))->assertOk();

        $this->assertLessThan(25, count(DB::getQueryLog()));
    }

    public function test_ordinary_member_cannot_create_tasks_but_a_lead_can(): void
    {
        $this->actingAs($this->member)->get(route('projects.tasks.create', $this->project))->assertForbidden();

        ProjectMember::where('user_id', $this->member->id)->update(['role' => ProjectMemberRole::Lead]);

        $this->actingAs($this->member)->get(route('projects.tasks.create', $this->project))->assertOk();
    }

    public function test_assignee_moves_their_task_and_dates_are_recorded(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id, 'assignee_id' => $this->member->id]);

        $this->actingAs($this->member)->patch(route('tasks.move', $task), ['status' => 'in_progress']);
        $task->refresh();
        $this->assertSame(TaskStatus::InProgress, $task->status);
        $this->assertNotNull($startedAt = $task->started_at);

        $this->actingAs($this->member)->patch(route('tasks.move', $task), ['status' => 'done']);
        $this->assertNotNull($task->fresh()->completed_at);

        // إعادة الفتح تمحو الإنجاز ولا تمسّ تاريخ البدء الأول. القفزة أقل من مهلة
        // الخمول (15 دقيقة) وإلا سُجّل خروج العضو قبل الطلب.
        $this->travel(10)->minutes();
        $this->actingAs($this->member)->patch(route('tasks.move', $task), ['status' => 'review']);
        $task->refresh();
        $this->assertNull($task->completed_at);
        $this->assertTrue($task->started_at->equalTo($startedAt));
    }

    public function test_a_member_cannot_move_someone_elses_task(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id, 'assignee_id' => $this->pm->id]);

        $this->actingAs($this->member)->patch(route('tasks.move', $task), ['status' => 'done'])->assertForbidden();
    }

    public function test_tasks_do_not_progress_before_approval(): void
    {
        $draft = Project::factory()->create(['pm_id' => $this->pm->id]);
        $task = Task::factory()->create(['project_id' => $draft->id, 'assignee_id' => $this->pm->id]);

        $this->actingAs($this->pm)->patch(route('tasks.move', $task), ['status' => 'in_progress'])->assertForbidden();
    }

    public function test_kanban_drop_returns_json_and_reorders_the_column(): void
    {
        [$first, $second, $moved] = Task::factory()->count(3)->sequence(
            ['position' => 0, 'status' => TaskStatus::InProgress],
            ['position' => 1, 'status' => TaskStatus::InProgress],
            ['position' => 0, 'status' => TaskStatus::Todo],
        )->create(['project_id' => $this->project->id]);

        $this->actingAs($this->pm)
            ->patchJson(route('tasks.move', $moved), ['status' => 'in_progress', 'position' => 1])
            ->assertOk()
            ->assertJson(['status' => 'in_progress', 'position' => 1]);

        $order = Task::where('status', TaskStatus::InProgress)->orderBy('position')->pluck('id')->all();
        $this->assertSame([$first->id, $moved->id, $second->id], $order);
    }

    public function test_board_and_list_views_render(): void
    {
        Task::factory()->create(['project_id' => $this->project->id, 'title' => 'مهمة على اللوحة']);

        $this->actingAs($this->member)->get(route('projects.tasks.index', $this->project))
            ->assertOk()->assertSee('مهمة على اللوحة')->assertSee('قائمة الانتظار');
        $this->actingAs($this->member)->get(route('projects.tasks.index', [$this->project, 'view' => 'list']))
            ->assertOk()->assertSee('مهمة على اللوحة');
    }

    public function test_team_comments_on_tasks(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);

        $this->actingAs($this->member)->post(route('tasks.comments.store', $task), ['body' => 'أنجزت المسودة الأولى']);

        $this->actingAs($this->pm)->get(route('tasks.show', $task))->assertOk()->assertSee('أنجزت المسودة الأولى');
    }

    public function test_only_the_author_or_management_deletes_a_comment(): void
    {
        $task = Task::factory()->create(['project_id' => $this->project->id]);
        $comment = $task->comments()->create(['user_id' => $this->pm->id, 'body' => 'تعليق المدير']);

        $this->actingAs($this->member)->delete(route('comments.destroy', $comment))->assertForbidden();
        $this->actingAs($this->pm)->delete(route('comments.destroy', $comment));
        $this->assertModelMissing($comment);
    }

    public function test_my_tasks_groups_what_needs_attention(): void
    {
        Task::factory()->create(['project_id' => $this->project->id, 'assignee_id' => $this->member->id, 'title' => 'مهمة متأخرة', 'due_date' => now()->subDays(2)]);
        Task::factory()->create(['project_id' => $this->project->id, 'assignee_id' => $this->member->id, 'title' => 'مهمة قادمة', 'due_date' => now()->addDays(5)]);
        Task::factory()->create(['project_id' => $this->project->id, 'assignee_id' => $this->pm->id, 'title' => 'مهمة غيري']);

        $this->actingAs($this->member)->get(route('tasks.mine'))
            ->assertOk()
            ->assertSeeInOrder(['متأخرة', 'مهمة متأخرة', 'لم تبدأ بعد', 'مهمة قادمة'])
            ->assertDontSee('مهمة غيري');
    }

    public function test_pm_sees_tasks_and_team_workload_across_projects(): void
    {
        Task::factory()->create(['project_id' => $this->project->id, 'assignee_id' => $this->member->id, 'title' => 'مهمة الفريق', 'due_date' => now()->subDay()]);

        $this->actingAs($this->pm)->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('حمل الفريق')
            ->assertSee($this->member->name)
            ->assertSee('مهمة الفريق');

        $this->actingAs($this->member)->get(route('tasks.index'))->assertForbidden();
    }

    public function test_removing_a_member_unassigns_their_open_tasks(): void
    {
        $open = Task::factory()->create(['project_id' => $this->project->id, 'assignee_id' => $this->member->id]);
        $done = Task::factory()->status(TaskStatus::Done)->create(['project_id' => $this->project->id, 'assignee_id' => $this->member->id]);
        $membership = ProjectMember::where('user_id', $this->member->id)->sole();

        $this->actingAs($this->pm)->delete(route('projects.members.destroy', [$this->project, $membership]));

        $this->assertNull($open->fresh()->assignee_id);
        $this->assertSame($this->member->id, $done->fresh()->assignee_id);
    }

    public function test_clients_cannot_join_a_project_team(): void
    {
        $client = User::factory()->role('client')->create();

        $this->actingAs($this->pm)
            ->post(route('projects.members.store', $this->project), ['user_id' => $client->id, 'role' => 'member'])
            ->assertSessionHasErrors('user_id');
    }

    public function test_membership_routes_are_scoped_to_their_project(): void
    {
        $otherProject = Project::factory()->create(['pm_id' => $this->pm->id]);
        $membership = ProjectMember::where('user_id', $this->member->id)->sole();

        $this->actingAs($this->pm)
            ->delete(route('projects.members.destroy', [$otherProject, $membership]))
            ->assertNotFound();
    }
}
