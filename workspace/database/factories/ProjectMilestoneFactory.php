<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMilestone>
 */
class ProjectMilestoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => 'مرحلة '.fake()->word(),
            'description' => fake()->optional()->sentence(),
            'due_date' => fake()->dateTimeBetween('now', '+2 months'),
            'position' => 0,
        ];
    }
}
