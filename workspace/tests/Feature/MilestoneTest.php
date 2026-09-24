<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_pm_plans_milestones_in_order(): void
    {
        $pm = User::factory()->role('pm')->create();
        $project = Project::factory()->create(['pm_id' => $pm->id]);

        $this->actingAs($pm)->post(route('projects.milestones.store', $project), ['title' => 'التحليل', 'due_date' => '2026-11-01']);
        $this->actingAs($pm)->post(route('projects.milestones.store', $project), ['title' => 'التطوير']);

        $this->assertSame(['التحليل', 'التطوير'], $project->milestones()->pluck('title')->all());
        $this->assertSame([0, 1], $project->milestones()->pluck('position')->all());
    }

    public function test_reaching_a_milestone_records_when(): void
    {
        $pm = User::factory()->role('pm')->create();
        $milestone = ProjectMilestone::factory()->create(['project_id' => Project::factory()->create(['pm_id' => $pm->id])->id]);

        $this->actingAs($pm)->post(route('projects.milestones.toggle', [$milestone->project, $milestone]));
        $this->assertNotNull($milestone->fresh()->completed_at);

        $this->actingAs($pm)->post(route('projects.milestones.toggle', [$milestone->project, $milestone]));
        $this->assertNull($milestone->fresh()->completed_at);
    }

    public function test_deleting_a_milestone_keeps_its_tasks(): void
    {
        $pm = User::factory()->role('pm')->create();
        $project = Project::factory()->create(['pm_id' => $pm->id]);
        $milestone = ProjectMilestone::factory()->create(['project_id' => $project->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'milestone_id' => $milestone->id]);

        $this->actingAs($pm)->delete(route('projects.milestones.destroy', [$project, $milestone]));

        $this->assertModelMissing($milestone);
        $this->assertNull($task->fresh()->milestone_id);
    }

    public function test_milestones_of_another_project_are_not_reachable(): void
    {
        $pm = User::factory()->role('pm')->create();
        $project = Project::factory()->create(['pm_id' => $pm->id]);
        $foreign = ProjectMilestone::factory()->create();

        $this->actingAs($pm)->post(route('projects.milestones.toggle', [$project, $foreign]))->assertNotFound();
    }
}
