<?php

namespace Database\Factories;

use App\Enums\ProjectDecisionType;
use App\Models\Project;
use App\Models\ProjectDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectDecision>
 */
class ProjectDecisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'decided_by' => User::factory()->role('executive'),
            'type' => ProjectDecisionType::Approved,
            'note' => null,
            'decided_at' => now(),
        ];
    }
}
