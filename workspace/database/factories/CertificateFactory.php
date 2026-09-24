<?php

namespace Database\Factories;

use App\Enums\CertificateType;
use App\Enums\ProjectStatus;
use App\Models\Certificate;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CertificateType::Completion,
            'project_id' => Project::factory()->forClient()->status(ProjectStatus::Completed),
            'recipient_id' => fn (array $attributes) => Project::find($attributes['project_id'])->client_id,
            'title' => 'شهادة إنجاز',
            'issued_by' => fn (array $attributes) => Project::find($attributes['project_id'])->pm_id,
        ];
    }
}
