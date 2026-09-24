<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\ProjectStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\TaskStatus;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-09-15 10:00'));
    }

    public function test_each_role_reaches_only_its_own_report(): void
    {
        $reports = [
            'executive' => 'reports.executive',
            'pm' => 'reports.pm',
            'finance' => 'reports.finance',
            'team_member' => 'reports.mine',
            'client' => 'reports.client',
        ];

        foreach ($reports as $role => $ownReport) {
            $user = User::factory()->role($role)->create();

            foreach ($reports as $report) {
                $allowed = $report === $ownReport || ($role === 'executive' && $report === 'reports.finance');

                $this->actingAs($user)->get(route($report))->assertStatus($allowed ? 200 : 403);
            }
        }
    }

    public function test_executive_report_totals_money_by_month_and_ages_receivables(): void
    {
        $executive = User::factory()->role('executive')->create();

        $recent = Invoice::factory()->withAmount('1000.00')->issued()->create();
        $older = Invoice::factory()->withAmount('2000.00')->issued('2026-08-01')->create();
        $older->forceFill(['issue_date' => '2026-07-10'])->save();
        $recent->recordPayment('150.00', today(), PaymentMethod::BankTransfer, null, $executive);

        $this->actingAs($executive)->get(route('reports.executive', ['months' => 3]))
            ->assertOk()
            ->assertViewHas('invoicedSeries', [2300.0, 0.0, 1150.0])
            ->assertViewHas('collectedSeries', [0.0, 0.0, 150.0])
            ->assertViewHas('kpis', fn (array $kpis): bool => $kpis['outstanding'] == 3300 && $kpis['overdueReceivables'] == 2300)
            ->assertViewHas('aging', fn (array $aging): bool => collect($aging)->pluck('value', 'label')->all() == [
                'لم يستحق بعد' => 1000,
                '١–٣٠ يوماً' => 0,
                '٣١–٦٠ يوماً' => 2300,
                '٦١–٩٠ يوماً' => 0,
                'أكثر من ٩٠ يوماً' => 0,
            ]);
    }

    public function test_period_defaults_to_six_months_and_accepts_only_known_ranges(): void
    {
        $executive = User::factory()->role('executive')->create();

        $this->actingAs($executive)->get(route('reports.executive', ['months' => 12]))
            ->assertViewHas('period', fn (array $period): bool => $period['keys'][0] === '2025-10' && count($period['keys']) === 12);

        $this->actingAs($executive)->get(route('reports.executive', ['months' => 999]))
            ->assertViewHas('period', fn (array $period): bool => $period['months'] === 6 && count($period['keys']) === 6);
    }

    public function test_csv_export_opens_in_excel_and_cannot_smuggle_formulas(): void
    {
        $executive = User::factory()->role('executive')->create();
        Project::factory()->status(ProjectStatus::InProgress)->create(['name' => '=HYPERLINK("https://evil.test","اضغط")']);

        $response = $this->actingAs($executive)->get(route('reports.executive', ['export' => 'projects']));

        $response->assertOk()->assertDownload('active-projects-2026-09-15.csv');
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertDoesNotMatchRegularExpression('/(^|,|\n)"?=HYPERLINK/', $csv);
    }

    public function test_pm_report_covers_their_projects_and_team_load_only(): void
    {
        $pm = User::factory()->role('pm')->create();
        $member = User::factory()->role('team_member')->create(['name' => 'سارة']);
        $mine = Project::factory()->status(ProjectStatus::InProgress)->create(['pm_id' => $pm->id, 'name' => 'مشروعي']);
        $colleagues = Project::factory()->status(ProjectStatus::InProgress)->create(['name' => 'مشروع زميل']);

        Task::factory()->count(2)->create(['project_id' => $mine->id, 'assignee_id' => $member->id]);
        Task::factory()->create(['project_id' => $mine->id, 'assignee_id' => $member->id, 'due_date' => '2026-09-01']);
        Task::factory()->status(TaskStatus::Done)->create(['project_id' => $mine->id, 'assignee_id' => $member->id]);
        Task::factory()->create(['project_id' => $colleagues->id, 'assignee_id' => $member->id]);

        $this->actingAs($pm)->get(route('reports.pm'))
            ->assertOk()
            ->assertSee('مشروعي')
            ->assertDontSee('مشروع زميل')
            ->assertViewHas('kpis', fn (array $kpis): bool => $kpis['openTasks'] == 3 && $kpis['overdueTasks'] == 1)
            ->assertViewHas('workloadRows', [['label' => 'سارة', 'value' => 3, 'url' => route('tasks.index', ['assignee' => $member->id])]])
            ->assertViewHas('completedTasks', fn (array $series): bool => end($series) === 1.0);
    }

    public function test_finance_report_tracks_contract_coverage_and_budget_use(): void
    {
        $finance = User::factory()->role('finance')->create();
        $project = Project::factory()->forClient()->status(ProjectStatus::InProgress)->create(['budget' => 10000]);
        $contract = Contract::factory()->active()->create(['project_id' => $project->id, 'value' => 5000]);
        Invoice::factory()->withAmount('2000.00')->issued()->create(['project_id' => $project->id, 'contract_id' => $contract->id]);
        PurchaseOrder::factory()->withAmount('4000.00')->status(PurchaseOrderStatus::Approved)->create(['project_id' => $project->id]);
        PurchaseOrder::factory()->withAmount('9000.00')->status(PurchaseOrderStatus::Cancelled)->create(['project_id' => $project->id]);

        $this->actingAs($finance)->get(route('reports.finance'))
            ->assertOk()
            ->assertViewHas('contracts', fn ($contracts): bool => (float) $contracts->sole()->invoiced_sum === 2000.0)
            ->assertViewHas('budgets', fn ($budgets): bool => (float) $budgets->sole()->committed_sum === 4600.0)
            ->assertViewHas('byClient', [['label' => $project->client->name, 'value' => 2300.0]])
            ->assertSee('46٪');
    }

    public function test_team_member_report_measures_on_time_delivery_of_their_own_tasks(): void
    {
        $member = User::factory()->role('team_member')->create();

        Task::factory()->status(TaskStatus::Done)->create(['assignee_id' => $member->id, 'due_date' => '2026-09-10', 'completed_at' => '2026-09-09 12:00']);
        Task::factory()->status(TaskStatus::Done)->create(['assignee_id' => $member->id, 'due_date' => '2026-09-05', 'completed_at' => '2026-09-08 12:00']);
        Task::factory()->status(TaskStatus::Done)->create(['assignee_id' => $member->id, 'due_date' => null, 'completed_at' => '2026-09-01 12:00']);
        Task::factory()->create(['assignee_id' => $member->id, 'due_date' => '2026-09-01']);
        Task::factory()->status(TaskStatus::Done)->create(['assignee_id' => User::factory()->role('team_member'), 'title' => 'مهمة زميل']);

        $this->actingAs($member)->get(route('reports.mine'))
            ->assertOk()
            ->assertViewHas('kpis', ['open' => 1, 'overdue' => 1, 'completed' => 3, 'onTimeRate' => 50])
            ->assertSee('بعد الموعد')
            ->assertDontSee('مهمة زميل');
    }

    public function test_client_status_report_shows_their_documents_and_nothing_internal(): void
    {
        $client = User::factory()->role('client')->create();
        $project = Project::factory()->forClient($client)->status(ProjectStatus::InProgress)->create(['budget' => 777777]);
        $foreign = Project::factory()->forClient()->status(ProjectStatus::InProgress)->create();

        Task::factory()->create(['project_id' => $project->id, 'title' => 'مهمة داخلية سرية']);
        $issued = Invoice::factory()->withAmount('1000.00')->issued()->create(['project_id' => $project->id]);
        Invoice::factory()->withAmount('500.00')->create(['project_id' => $project->id]);
        Contract::factory()->active()->create(['project_id' => $project->id, 'value' => 20000, 'title' => 'عقد التطوير']);
        Contract::factory()->create(['project_id' => $project->id, 'title' => 'مسودة عقد داخلية']);

        $this->actingAs($client)->get(route('reports.client'))
            ->assertOk()
            ->assertSee($project->name)
            ->assertSee($issued->number)
            ->assertSee('عقد التطوير')
            ->assertDontSee('مسودة عقد داخلية')
            ->assertDontSee('مهمة داخلية سرية')
            ->assertDontSee('777,777')
            ->assertViewHas('totals', ['contracted' => 20000, 'invoiced' => 1150, 'paid' => 0, 'outstanding' => 1150]);

        $this->actingAs($client)->get(route('reports.client', ['project' => $foreign->id]))->assertNotFound();
    }

    public function test_client_without_projects_gets_an_empty_state(): void
    {
        $this->actingAs(User::factory()->role('client')->create())
            ->get(route('reports.client'))
            ->assertOk()
            ->assertSee('لا مشاريع بعد');
    }
}
