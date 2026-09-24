<?php

namespace Tests\Feature;

use App\Enums\ProjectDecisionType;
use App\Enums\ProjectStatus;
use App\Models\HiringRequest;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutivePagesTest extends TestCase
{
    use RefreshDatabase;

    private User $executive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->executive = User::factory()->role('executive')->create();
    }

    public function test_overview_counts_what_awaits_the_executive_and_flags_trouble(): void
    {
        Project::factory()->status(ProjectStatus::PendingApproval)->create();
        HiringRequest::factory()->create();
        Project::factory()->status(ProjectStatus::InProgress)->create(['name' => 'مشروع تجاوز موعده', 'end_date' => today()->subDays(3)]);
        $late = Invoice::factory()->withAmount('1000.00')->issued(today()->subDays(5)->toDateString())->create();

        $this->actingAs($this->executive)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('queues', fn (array $queues): bool => collect($queues)->pluck('count', 'label')->all() === [
                'مشاريع بانتظار الاعتماد' => 1,
                'أوامر شراء بانتظار اعتمادك' => 0,
                'طلبات توظيف' => 1,
                'طلبات إفادة' => 0,
            ])
            ->assertSee('مشروع تجاوز موعده')
            ->assertSee($late->number);
    }

    public function test_approvals_page_lists_what_awaits_a_decision(): void
    {
        Project::factory()->status(ProjectStatus::PendingApproval)->create(['name' => 'مشروع ينتظر']);
        Project::factory()->status(ProjectStatus::InProgress)->create(['name' => 'مشروع متأخر', 'end_date' => now()->subWeek()]);
        Project::factory()->create(['name' => 'مسودة لم تُقدَّم']);

        $this->actingAs($this->executive)->get(route('approvals.projects'))
            ->assertOk()
            ->assertSee('مشروع ينتظر')
            ->assertSee('مشروع متأخر')
            ->assertDontSee('مسودة لم تُقدَّم');
    }

    public function test_decisions_log_can_be_filtered_by_type(): void
    {
        $approved = Project::factory()->status(ProjectStatus::PendingApproval)->create(['name' => 'مشروع معتمد']);
        $rejected = Project::factory()->status(ProjectStatus::PendingApproval)->create(['name' => 'مشروع مرفوض']);
        $approved->decide(ProjectDecisionType::Approved, $this->executive);
        $rejected->decide(ProjectDecisionType::Rejected, $this->executive, 'خارج الاستراتيجية');

        $this->actingAs($this->executive)->get(route('decisions.index', ['type' => 'rejected']))
            ->assertOk()
            ->assertSee('مشروع مرفوض')
            ->assertSee('خارج الاستراتيجية')
            ->assertDontSee('مشروع معتمد');
    }

    public function test_team_page_shows_each_persons_load(): void
    {
        $member = User::factory()->role('team_member')->create(['name' => 'ريم']);
        $project = Project::factory()->status(ProjectStatus::InProgress)->create();
        Task::factory()->count(2)->create(['project_id' => $project->id, 'assignee_id' => $member->id, 'due_date' => now()->subDay()]);

        $this->actingAs($this->executive)->get(route('team.index'))
            ->assertOk()
            ->assertViewHas('people', fn ($people): bool => $people->firstWhere('name', 'ريم')->overdue_tasks_count === 2);
    }

    public function test_executive_pages_are_for_the_executive_only(): void
    {
        $pm = User::factory()->role('pm')->create();

        foreach (['approvals.projects', 'decisions.index', 'team.index'] as $route) {
            $this->actingAs($pm)->get(route($route))->assertForbidden();
        }
    }
}
