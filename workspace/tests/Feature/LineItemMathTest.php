<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineItemMathTest extends TestCase
{
    use RefreshDatabase;

    public function test_amounts_are_exact_where_floating_point_is_not(): void
    {
        $invoice = Invoice::factory()->create();

        // 0.1 + 0.2 بالأعداد العائمة = 0.30000000000000004
        $invoice->syncItems([
            ['description' => 'أ', 'quantity' => '1', 'unit_price' => '0.10'],
            ['description' => 'ب', 'quantity' => '1', 'unit_price' => '0.20'],
        ]);

        $this->assertSame('0.30', $invoice->fresh()->subtotal);
    }

    public function test_line_totals_and_vat_round_half_up_once(): void
    {
        $invoice = Invoice::factory()->create(['vat_rate' => 15]);

        $invoice->syncItems([
            ['description' => 'ساعات استشارة', 'quantity' => '2.5', 'unit_price' => '333.33'], // 833.325 → 833.33
            ['description' => 'رخصة', 'quantity' => '3', 'unit_price' => '19.99'],               // 59.97
        ]);

        $invoice->refresh();
        $this->assertSame(['833.33', '59.97'], $invoice->items->pluck('total')->all());
        $this->assertSame('893.30', $invoice->subtotal);
        $this->assertSame('134.00', $invoice->vat_amount); // 133.995 → 134.00
        $this->assertSame('1027.30', $invoice->total);
    }

    public function test_the_largest_allowed_line_is_exact(): void
    {
        $invoice = Invoice::factory()->create(['vat_rate' => 15]);

        // (10⁴ − 0.01) × (10⁷ − 0.01) = 99,999,899,900.0001 — قرب حد المستند الواحد.
        $invoice->syncItems([['description' => 'مشروع كبير', 'quantity' => '9999.99', 'unit_price' => '9999999.99']]);

        $this->assertSame('99999899900.00', $invoice->fresh()->subtotal);
        $this->assertSame('14999984985.00', $invoice->fresh()->vat_amount);
        $this->assertSame('114999884885.00', $invoice->fresh()->total);
    }

    public function test_documents_above_the_total_limit_are_refused(): void
    {
        $finance = User::factory()->role('finance')->create();
        $project = Project::factory()->forClient()->status(ProjectStatus::InProgress)->create();

        $this->actingAs($finance)->post(route('invoices.store'), [
            'project_id' => $project->id,
            'items' => array_fill(0, 11, ['description' => 'بند', 'quantity' => '99999.99', 'unit_price' => '9999999.99']),
        ])->assertSessionHasErrors('items');
    }

    public function test_sequences_are_per_type_and_year_without_repeats(): void
    {
        $this->assertSame('INV-'.now()->year.'-0001', Sequence::next('INV'));
        $this->assertSame('INV-'.now()->year.'-0002', Sequence::next('INV'));
        $this->assertSame('PO-'.now()->year.'-0001', Sequence::next('PO'));

        $this->travelTo(now()->addYear()->startOfYear());
        $this->assertSame('INV-'.now()->year.'-0001', Sequence::next('INV'));
    }
}
