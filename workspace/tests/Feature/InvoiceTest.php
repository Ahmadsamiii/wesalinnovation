<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProjectStatus;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $finance;

    private User $client;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->finance = User::factory()->role('finance')->create();
        $this->client = User::factory()->role('client')->create();
        $this->project = Project::factory()->forClient($this->client)->status(ProjectStatus::InProgress)->create();
    }

    public function test_finance_drafts_an_invoice_for_the_projects_client_without_a_number(): void
    {
        $this->actingAs($this->finance)->post(route('invoices.store'), [
            'project_id' => $this->project->id,
            'items' => [['description' => 'المرحلة الأولى', 'quantity' => '1', 'unit_price' => '20000']],
        ])->assertSessionHasNoErrors();

        $invoice = Invoice::sole();
        $this->assertNull($invoice->number);
        $this->assertSame($this->client->id, $invoice->client_id);
        $this->assertSame('23000.00', $invoice->total);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::InvoiceCreated->value]);
    }

    public function test_only_finance_creates_invoices(): void
    {
        foreach (['pm', 'executive', 'client'] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get(route('invoices.create'))->assertForbidden();
        }
    }

    public function test_issuing_assigns_the_next_number_and_a_due_date(): void
    {
        $first = Invoice::factory()->withAmount('100')->create(['project_id' => $this->project->id, 'created_by' => $this->finance->id]);
        $second = Invoice::factory()->withAmount('100')->create(['project_id' => $this->project->id, 'created_by' => $this->finance->id]);

        // المسودة المحذوفة لا تترك فجوة: الرقم يُسند عند الإصدار.
        $this->actingAs($this->finance)->delete(route('invoices.destroy', $first));
        $this->actingAs($this->finance)->post(route('invoices.issue', $second));

        $second->refresh();
        $this->assertSame(InvoiceStatus::Issued, $second->status);
        $this->assertSame('INV-'.now()->year.'-0001', $second->number);
        $this->assertTrue($second->issue_date->isToday());
        $this->assertTrue($second->due_date->isSameDay(today()->addDays(config('workspace.invoice_payment_terms_days'))));
    }

    public function test_issued_invoices_are_locked(): void
    {
        $invoice = Invoice::factory()->withAmount('100')->issued()->create(['project_id' => $this->project->id, 'created_by' => $this->finance->id]);

        $this->actingAs($this->finance)->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->actingAs($this->finance)->delete(route('invoices.destroy', $invoice))->assertForbidden();
    }

    public function test_partial_then_full_payment_settles_the_invoice(): void
    {
        $invoice = Invoice::factory()->withAmount('1000')->issued()->create(['project_id' => $this->project->id, 'created_by' => $this->finance->id]);

        $this->actingAs($this->finance)->post(route('invoices.payments.store', $invoice), [
            'amount' => '500', 'paid_on' => today()->toDateString(), 'method' => 'bank_transfer', 'reference' => 'TRX-1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
        $this->assertSame('650.00', $invoice->fresh()->balance());

        $this->actingAs($this->finance)->post(route('invoices.payments.store', $invoice), [
            'amount' => '650', 'paid_on' => today()->toDateString(), 'method' => 'card',
        ]);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('0.00', $invoice->balance());
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_payment_cannot_exceed_the_balance(): void
    {
        $invoice = Invoice::factory()->withAmount('100')->issued()->create(['project_id' => $this->project->id, 'created_by' => $this->finance->id]);

        $this->actingAs($this->finance)->post(route('invoices.payments.store', $invoice), [
            'amount' => '115.01', 'paid_on' => today()->toDateString(), 'method' => 'cash',
        ])->assertSessionHasErrors('amount');
    }

    public function test_an_invoice_with_payments_cannot_be_cancelled(): void
    {
        $invoice = Invoice::factory()->withAmount('100')->issued()->create(['project_id' => $this->project->id, 'created_by' => $this->finance->id]);
        $invoice->recordPayment('10', today(), PaymentMethod::Cash, null, $this->finance);

        $this->actingAs($this->finance)->post(route('invoices.cancel', $invoice), ['reason' => 'خطأ'])->assertForbidden();
    }

    public function test_cancelling_requires_a_reason(): void
    {
        $invoice = Invoice::factory()->withAmount('100')->issued()->create(['project_id' => $this->project->id, 'created_by' => $this->finance->id]);

        $this->actingAs($this->finance)->post(route('invoices.cancel', $invoice), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->finance)->post(route('invoices.cancel', $invoice), ['reason' => 'فوترة مكررة']);

        $this->assertSame(InvoiceStatus::Cancelled, $invoice->fresh()->status);
    }

    public function test_client_sees_issued_invoices_only_and_can_print_them(): void
    {
        $issued = Invoice::factory()->withAmount('100')->issued()->create(['project_id' => $this->project->id, 'created_by' => $this->finance->id]);
        $draft = Invoice::factory()->withAmount('999')->create(['project_id' => $this->project->id, 'created_by' => $this->finance->id]);
        $foreign = Invoice::factory()->withAmount('100')->issued()->create(['created_by' => $this->finance->id]);

        $this->actingAs($this->client)->get(route('invoices.index'))
            ->assertOk()
            ->assertSee($issued->number)
            ->assertDontSee('999.00')
            ->assertDontSee($foreign->number);

        $this->actingAs($this->client)->get(route('invoices.print', $issued))->assertOk()->assertSee($issued->number);
        $this->actingAs($this->client)->get(route('invoices.show', $draft))->assertForbidden();
        $this->actingAs($this->client)->get(route('invoices.show', $foreign))->assertForbidden();
    }

    public function test_overdue_invoices_are_flagged(): void
    {
        Invoice::factory()->withAmount('100')->issued(now()->subDays(3)->toDateString())->create(['project_id' => $this->project->id, 'created_by' => $this->finance->id]);

        $this->actingAs($this->finance)->get(route('invoices.index', ['status' => 'overdue']))
            ->assertOk()
            ->assertViewHas('invoices', fn ($invoices): bool => $invoices->total() === 1)
            ->assertViewHas('overdueCount', 1);
    }

    public function test_invoice_contract_must_belong_to_the_same_project(): void
    {
        $foreignContract = Contract::factory()->active()->create();

        $this->actingAs($this->finance)->post(route('invoices.store'), [
            'project_id' => $this->project->id,
            'contract_id' => $foreignContract->id,
            'items' => [['description' => 'x', 'quantity' => '1', 'unit_price' => '1']],
        ])->assertSessionHasErrors('contract_id');
    }

    public function test_project_without_a_client_cannot_be_invoiced(): void
    {
        $internal = Project::factory()->status(ProjectStatus::InProgress)->create();
        $invoice = Invoice::factory()->withAmount('100')->create(['project_id' => $internal->id, 'created_by' => $this->finance->id]);

        $this->actingAs($this->finance)->post(route('invoices.issue', $invoice))->assertSessionHas('error');
        $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);
    }
}
