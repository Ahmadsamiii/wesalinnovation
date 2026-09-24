<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => TaskStatus::Todo,
            'priority' => Priority::Normal,
            'assignee_id' => null,
            'created_by' => fn (array $attributes) => Project::find($attributes['project_id'])->pm_id,
            'due_date' => fake()->dateTimeBetween('now', '+1 month'),
            'position' => 0,
        ];
    }

    public function status(TaskStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'started_at' => $status === TaskStatus::Todo ? null : now()->subDay(),
            'completed_at' => $status === TaskStatus::Done ? now() : null,
        ]);
    }
}
