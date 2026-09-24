<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Project;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory()->status(ProjectStatus::InProgress),
            'vendor_name' => fake()->company(),
            'status' => PurchaseOrderStatus::Draft,
            'requested_by' => fn (array $attributes) => Project::find($attributes['project_id'])->pm_id,
        ];
    }

    /**
     * يضيف بنداً واحداً بالمبلغ المعطى قبل الضريبة ويحسب المجاميع.
     */
    public function withAmount(string $subtotal): static
    {
        return $this->afterCreating(function (PurchaseOrder $order) use ($subtotal): void {
            $order->syncItems([['description' => 'بند', 'quantity' => '1', 'unit_price' => $subtotal]]);
        });
    }

    public function status(PurchaseOrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'submitted_at' => $status === PurchaseOrderStatus::Draft ? null : now(),
        ]);
    }
}
