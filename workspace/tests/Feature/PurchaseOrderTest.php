<?php

namespace Tests\Feature;

use App\Enums\ApprovalStage;
use App\Enums\AuditAction;
use App\Enums\ProjectStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    private User $finance;

    private User $executive;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        config(['workspace.executive_approval_threshold' => 50000]);
        $this->pm = User::factory()->role('pm')->create();
        $this->finance = User::factory()->role('finance')->create();
        $this->executive = User::factory()->role('executive')->create();
        $this->project = Project::factory()->status(ProjectStatus::InProgress)->create(['pm_id' => $this->pm->id, 'budget' => 100000]);
    }

    public function test_pm_drafts_an_order_with_items_and_totals(): void
    {
        $this->actingAs($this->pm)->post(route('purchase-orders.store'), [
            'project_id' => $this->project->id,
            'vendor_name' => 'مؤسسة الأجهزة المساعدة',
            'items' => [
                ['description' => 'قارئ شاشة (ترخيص)', 'quantity' => '4', 'unit_price' => '1250'],
                ['description' => 'سماعات', 'quantity' => '4', 'unit_price' => '300'],
            ],
        ])->assertSessionHasNoErrors();

        $order = PurchaseOrder::sole();
        $this->assertSame(PurchaseOrderStatus::Draft, $order->status);
        $this->assertMatchesRegularExpression('/^PO-\d{4}-0001$/', $order->number);
        $this->assertSame('6200.00', $order->subtotal);
        $this->assertSame('930.00', $order->vat_amount);
        $this->assertSame('7130.00', $order->total);
        $this->assertSame($this->pm->id, $order->requested_by);
    }

    public function test_orders_need_items_and_an_approved_project(): void
    {
        $draftProject = Project::factory()->create(['pm_id' => $this->pm->id]);

        $this->actingAs($this->pm)->post(route('purchase-orders.store'), [
            'project_id' => $draftProject->id,
            'vendor_name' => 'مورد',
            'items' => [],
        ])->assertSessionHasErrors(['project_id', 'items']);
    }

    public function test_pm_cannot_order_for_someone_elses_project(): void
    {
        $other = Project::factory()->status(ProjectStatus::InProgress)->create();

        $this->actingAs($this->pm)->post(route('purchase-orders.store'), [
            'project_id' => $other->id,
            'vendor_name' => 'مورد',
            'items' => [['description' => 'x', 'quantity' => '1', 'unit_price' => '10']],
        ])->assertSessionHasErrors('project_id');
    }

    public function test_small_order_is_approved_by_finance_alone(): void
    {
        $order = PurchaseOrder::factory()->withAmount('10000')->create(['project_id' => $this->project->id]);

        $this->actingAs($this->pm)->post(route('purchase-orders.submit', $order));
        $this->assertSame(PurchaseOrderStatus::PendingFinance, $order->fresh()->status);

        $this->actingAs($this->finance)->post(route('purchase-orders.review', $order), ['decision' => 'approved']);

        $this->assertSame(PurchaseOrderStatus::Approved, $order->fresh()->status);
        $this->assertDatabaseHas('purchase_order_approvals', ['purchase_order_id' => $order->id, 'stage' => 'finance', 'decision' => 'approved']);
    }

    public function test_large_order_goes_on_to_the_executive(): void
    {
        $order = PurchaseOrder::factory()->withAmount('60000')->status(PurchaseOrderStatus::PendingFinance)->create(['project_id' => $this->project->id]);

        $this->actingAs($this->finance)->post(route('purchase-orders.review', $order), ['decision' => 'approved']);
        $this->assertSame(PurchaseOrderStatus::PendingExecutive, $order->fresh()->status);

        $this->actingAs($this->executive)->get(route('approvals.financial'))->assertOk()->assertSee($order->number);

        $this->actingAs($this->executive)->post(route('purchase-orders.decide', $order), ['decision' => 'approved']);
        $this->assertSame(PurchaseOrderStatus::Approved, $order->fresh()->status);
        $this->assertSame([ApprovalStage::Executive, ApprovalStage::Finance], $order->approvals()->get()->pluck('stage')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::PurchaseOrderDecided->value, 'subject_id' => $order->id]);
    }

    public function test_rejection_needs_a_reason_and_returns_the_order_for_editing(): void
    {
        $order = PurchaseOrder::factory()->withAmount('5000')->status(PurchaseOrderStatus::PendingFinance)->create(['project_id' => $this->project->id]);

        $this->actingAs($this->finance)->post(route('purchase-orders.review', $order), ['decision' => 'rejected'])->assertSessionHasErrors('note');

        $this->actingAs($this->finance)->post(route('purchase-orders.review', $order), ['decision' => 'rejected', 'note' => 'عرض سعر واحد لا يكفي']);
        $this->assertSame(PurchaseOrderStatus::Rejected, $order->fresh()->status);

        $this->actingAs($this->pm)->get(route('purchase-orders.show', $order))->assertSee('عرض سعر واحد لا يكفي');
        $this->actingAs($this->pm)->get(route('purchase-orders.edit', $order))->assertOk();
        $this->actingAs($this->pm)->post(route('purchase-orders.submit', $order));
        $this->assertSame(PurchaseOrderStatus::PendingFinance, $order->fresh()->status);
    }

    public function test_nobody_approves_their_own_request(): void
    {
        $order = PurchaseOrder::factory()->withAmount('5000')->status(PurchaseOrderStatus::PendingFinance)->create([
            'project_id' => $this->project->id,
            'requested_by' => $this->finance->id,
        ]);

        $this->actingAs($this->finance)->post(route('purchase-orders.review', $order), ['decision' => 'approved'])->assertForbidden();
    }

    public function test_stages_cannot_be_skipped(): void
    {
        $order = PurchaseOrder::factory()->withAmount('60000')->status(PurchaseOrderStatus::PendingFinance)->create(['project_id' => $this->project->id]);

        $this->actingAs($this->executive)->post(route('purchase-orders.decide', $order), ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->pm)->post(route('purchase-orders.review', $order), ['decision' => 'approved'])->assertForbidden();
    }

    public function test_submitted_orders_are_locked_and_approved_ones_can_be_received(): void
    {
        $order = PurchaseOrder::factory()->withAmount('5000')->status(PurchaseOrderStatus::Approved)->create(['project_id' => $this->project->id]);

        $this->actingAs($this->pm)->get(route('purchase-orders.edit', $order))->assertForbidden();

        $this->actingAs($this->pm)->post(route('purchase-orders.receive', $order));
        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->received_at);
    }

    public function test_committed_spend_counts_live_orders_only(): void
    {
        PurchaseOrder::factory()->withAmount('1000')->status(PurchaseOrderStatus::Approved)->create(['project_id' => $this->project->id]);
        PurchaseOrder::factory()->withAmount('2000')->status(PurchaseOrderStatus::PendingFinance)->create(['project_id' => $this->project->id]);
        PurchaseOrder::factory()->withAmount('4000')->create(['project_id' => $this->project->id]);
        PurchaseOrder::factory()->withAmount('8000')->status(PurchaseOrderStatus::Rejected)->create(['project_id' => $this->project->id]);

        $this->assertEquals(3450.0, (float) $this->project->committedSpend()); // (1000 + 2000) × 1.15
    }

    public function test_visibility_follows_the_project(): void
    {
        $order = PurchaseOrder::factory()->withAmount('100')->create(['project_id' => $this->project->id]);
        $otherPm = User::factory()->role('pm')->create();
        $client = User::factory()->role('client')->create();

        $this->actingAs($otherPm)->get(route('purchase-orders.show', $order))->assertForbidden();
        $this->actingAs($client)->get(route('purchase-orders.index'))->assertForbidden();
        $this->actingAs($this->finance)->get(route('purchase-orders.show', $order))->assertOk();
        $this->actingAs($otherPm)->get(route('purchase-orders.index'))->assertDontSee($order->number);
    }

    public function test_finance_cancels_with_a_reason(): void
    {
        $order = PurchaseOrder::factory()->withAmount('100')->status(PurchaseOrderStatus::Approved)->create(['project_id' => $this->project->id]);

        $this->actingAs($this->finance)->post(route('purchase-orders.cancel', $order), ['reason' => 'ألغى المورّد العرض']);

        $this->assertSame(PurchaseOrderStatus::Cancelled, $order->fresh()->status);
    }
}
