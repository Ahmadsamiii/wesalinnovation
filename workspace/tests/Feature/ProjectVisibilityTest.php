<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pm_sees_their_own_projects_only(): void
    {
        $pm = User::factory()->role('pm')->create();
        $mine = Project::factory()->create(['pm_id' => $pm->id, 'name' => 'مشروعي أنا']);
        $theirs = Project::factory()->create(['name' => 'مشروع زميل']);

        $this->actingAs($pm)->get(route('projects.index'))
            ->assertOk()
            ->assertSee('مشروعي أنا')
            ->assertDontSee('مشروع زميل');

        $this->actingAs($pm)->get(route('projects.show', $theirs))->assertForbidden();
        $this->actingAs($pm)->get(route('projects.show', $mine))->assertOk();
    }

    public function test_client_sees_only_their_projects_without_internal_work(): void
    {
        $client = User::factory()->role('client')->create();
        $project = Project::factory()->forClient($client)->status(ProjectStatus::InProgress)->create(['name' => 'مشروع العميل']);
        Project::factory()->forClient()->create(['name' => 'مشروع عميل آخر']);
        Task::factory()->create(['project_id' => $project->id, 'title' => 'مهمة داخلية سرية']);

        $this->actingAs($client)->get(route('projects.index'))
            ->assertSee('مشروع العميل')
            ->assertDontSee('مشروع عميل آخر');

        $this->actingAs($client)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('قيد التنفيذ')
            ->assertDontSee('مهمة داخلية سرية')
            ->assertDontSee('سجل القرارات');

        foreach (['projects.tasks.index', 'projects.members.index', 'projects.milestones.index', 'projects.files.index'] as $route) {
            $this->actingAs($client)->get(route($route, $project))->assertForbidden();
        }
        $this->actingAs($client)->get(route('tasks.show', $project->tasks()->first()))->assertForbidden();
    }

    public function test_team_member_sees_projects_they_belong_to(): void
    {
        $member = User::factory()->role('team_member')->create();
        $project = Project::factory()->create(['name' => 'مشروع أعمل فيه']);
        ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
        $other = Project::factory()->create(['name' => 'مشروع لا أعرفه']);

        $this->actingAs($member)->get(route('projects.index'))
            ->assertSee('مشروع أعمل فيه')
            ->assertDontSee('مشروع لا أعرفه');

        $this->actingAs($member)->get(route('projects.tasks.index', $project))->assertOk();
        $this->actingAs($member)->get(route('projects.show', $other))->assertForbidden();
    }

    public function test_executive_and_finance_see_every_project(): void
    {
        Project::factory()->create(['name' => 'أ']);
        Project::factory()->create(['name' => 'ب']);

        foreach (['executive', 'finance'] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get(route('projects.index'))
                ->assertViewHas('projects', fn ($projects): bool => $projects->total() === 2);
        }
    }

    public function test_finance_can_read_but_not_manage(): void
    {
        $finance = User::factory()->role('finance')->create();
        $project = Project::factory()->create();

        $this->actingAs($finance)->get(route('projects.show', $project))->assertOk();
        $this->actingAs($finance)->get(route('projects.edit', $project))->assertForbidden();
        $this->actingAs($finance)->post(route('projects.submit', $project))->assertForbidden();
    }
}
