<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory()->forClient()->status(ProjectStatus::InProgress),
            'status' => InvoiceStatus::Draft,
            'created_by' => User::factory()->role('finance'),
        ];
    }

    public function withAmount(string $subtotal): static
    {
        return $this->afterCreating(function (Invoice $invoice) use ($subtotal): void {
            $invoice->syncItems([['description' => 'خدمات', 'quantity' => '1', 'unit_price' => $subtotal]]);
        });
    }

    /**
     * فاتورة مصدرة برقم تسلسلي فعلي، عبر نفس مسار الإصدار.
     */
    public function issued(?string $dueDate = null): static
    {
        return $this->afterCreating(function (Invoice $invoice) use ($dueDate): void {
            if ($dueDate) {
                $invoice->forceFill(['due_date' => $dueDate])->save();
            }
            $invoice->issue($invoice->creator);
        });
    }
}
