<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 months', '+1 month');

        return [
            'name' => 'مشروع '.fake()->words(2, true),
            'description' => fake()->paragraph(),
            'pm_id' => User::factory()->role('pm'),
            'created_by' => fn (array $attributes) => $attributes['pm_id'],
            'client_id' => null,
            'status' => ProjectStatus::Draft,
            'priority' => Priority::Normal,
            'budget' => fake()->numberBetween(20, 500) * 1000,
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+3 months'),
        ];
    }

    public function forClient(?User $client = null): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client?->id ?? User::factory()->role('client'),
        ]);
    }

    public function status(ProjectStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'submitted_at' => $status === ProjectStatus::Draft ? null : now()->subDays(5),
            'actual_start_date' => in_array($status, [ProjectStatus::InProgress, ProjectStatus::Completed], true) ? now()->subDays(3) : null,
            'actual_end_date' => $status === ProjectStatus::Completed ? now() : null,
        ]);
    }
}
