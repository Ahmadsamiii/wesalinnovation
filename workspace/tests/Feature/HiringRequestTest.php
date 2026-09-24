<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\HiringRequestStatus;
use App\Enums\ProjectStatus;
use App\Models\HiringRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HiringRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return [
            'title' => 'مطور واجهات أمامية',
            'headcount' => 2,
            'employment_type' => 'full_time',
            'justification' => 'مرحلة التطوير تحتاج مطورَين إضافيين.',
            'monthly_budget' => '14000.00',
            ...$overrides,
        ];
    }

    public function test_team_lead_requests_and_executive_approves_then_it_is_filled(): void
    {
        $pm = User::factory()->role('pm')->create(['department' => 'الهندسة']);
        $executive = User::factory()->role('executive')->create();

        $this->actingAs($pm)->post(route('hiring-requests.store'), $this->validPayload())->assertSessionHasNoErrors();

        $hiring = HiringRequest::sole();
        $this->assertMatchesRegularExpression('/^HIR-\d{4}-0001$/', $hiring->number);
        $this->assertSame('الهندسة', $hiring->department);
        $this->assertSame(HiringRequestStatus::Pending, $hiring->status);

        $this->actingAs($executive)->get(route('hiring-requests.index'))->assertSee('مطور واجهات أمامية')->assertSee($pm->name);
        $this->actingAs($executive)->post(route('hiring-requests.approve', $hiring), ['note' => 'ضمن خطة الربع'])->assertSessionHasNoErrors();
        $this->assertSame(HiringRequestStatus::Approved, $hiring->fresh()->status);

        $this->actingAs($pm)->post(route('hiring-requests.fill', $hiring), ['note' => 'عُيّنت نورة وتباشر ١ أكتوبر'])->assertSessionHasNoErrors();

        $hiring->refresh();
        $this->assertSame(HiringRequestStatus::Filled, $hiring->status);
        $this->assertSame($pm->id, $hiring->closed_by);

        foreach ([AuditAction::HiringRequested, AuditAction::HiringApproved, AuditAction::HiringFilled] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action->value, 'subject_type' => 'hiring_request', 'subject_id' => $hiring->id]);
        }
    }

    public function test_rejection_needs_a_reason_the_requester_sees(): void
    {
        $hiring = HiringRequest::factory()->create();
        $executive = User::factory()->role('executive')->create();

        $this->actingAs($executive)->post(route('hiring-requests.reject', $hiring), [])->assertSessionHasErrors('reason');
        $this->actingAs($executive)->post(route('hiring-requests.reject', $hiring), ['reason' => 'يُؤجَّل للربع القادم']);

        $this->assertSame(HiringRequestStatus::Rejected, $hiring->fresh()->status);
        $this->actingAs($hiring->requester)->get(route('hiring-requests.show', $hiring))->assertSee('يُؤجَّل للربع القادم');
    }

    public function test_only_team_leads_request_and_only_the_executive_decides(): void
    {
        foreach (['team_member', 'client', 'executive'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->post(route('hiring-requests.store'), $this->validPayload())->assertForbidden();
        }

        $hiring = HiringRequest::factory()->create();
        $finance = User::factory()->role('finance')->create();

        $this->actingAs($finance)->post(route('hiring-requests.approve', $hiring))->assertForbidden();
        $this->actingAs($hiring->requester)->post(route('hiring-requests.approve', $hiring))->assertForbidden();
        $this->assertSame(HiringRequestStatus::Pending, $hiring->fresh()->status);
    }

    public function test_requests_are_private_to_their_requester_and_the_executive(): void
    {
        $hiring = HiringRequest::factory()->create(['title' => 'محلل بيانات سري']);
        $otherLead = User::factory()->role('medical')->create();

        $this->actingAs($otherLead)->get(route('hiring-requests.show', $hiring))->assertForbidden();
        $this->actingAs($otherLead)->get(route('hiring-requests.index'))->assertOk()->assertDontSee('محلل بيانات سري');
        $this->actingAs(User::factory()->role('team_member')->create())->get(route('hiring-requests.index'))->assertForbidden();
    }

    public function test_requester_edits_or_withdraws_only_before_the_decision(): void
    {
        $hiring = HiringRequest::factory()->create();
        $requester = $hiring->requester;

        $this->actingAs($requester)->put(route('hiring-requests.update', $hiring), $this->validPayload(['title' => 'مطور خلفيات']))->assertSessionHasNoErrors();
        $this->assertSame('مطور خلفيات', $hiring->fresh()->title);

        $hiring->approve(User::factory()->role('executive')->create());

        $this->actingAs($requester)->get(route('hiring-requests.edit', $hiring))->assertForbidden();
        $this->actingAs($requester)->post(route('hiring-requests.cancel', $hiring), ['reason' => 'أُعيد توزيع المهام'])->assertSessionHasNoErrors();
        $this->assertSame(HiringRequestStatus::Cancelled, $hiring->fresh()->status);

        $this->actingAs($requester)->post(route('hiring-requests.fill', $hiring), ['note' => 'x'])->assertForbidden();
    }

    public function test_project_link_is_limited_to_the_requesters_delivery_projects(): void
    {
        $pm = User::factory()->role('pm')->create();
        $own = Project::factory()->status(ProjectStatus::InProgress)->create(['pm_id' => $pm->id]);
        $foreign = Project::factory()->status(ProjectStatus::InProgress)->create();

        $this->actingAs($pm)->post(route('hiring-requests.store'), $this->validPayload(['project_id' => $foreign->id]))->assertSessionHasErrors('project_id');
        $this->actingAs($pm)->post(route('hiring-requests.store'), $this->validPayload(['project_id' => $own->id]))->assertSessionHasNoErrors();

        $this->assertSame($own->id, HiringRequest::sole()->project_id);
    }

    public function test_team_leads_reach_requests_from_the_account_menu(): void
    {
        $this->actingAs(User::factory()->role('finance')->create())->get(route('invoices.index'))
            ->assertSee(route('hiring-requests.index'))
            ->assertSee(route('reference-letters.index'));

        $this->actingAs(User::factory()->role('client')->create())->get(route('invoices.index'))
            ->assertDontSee(route('hiring-requests.index'))
            ->assertDontSee(route('reference-letters.index'));
    }
}
