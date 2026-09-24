<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory()->forClient(),
            'title' => 'عقد '.fake()->words(2, true),
            'value' => fake()->numberBetween(10, 400) * 1000,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'status' => ContractStatus::Draft,
            'created_by' => fn (array $attributes) => Project::find($attributes['project_id'])->pm_id,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContractStatus::Active,
            'signed_on' => now()->subWeek(),
        ]);
    }
}
