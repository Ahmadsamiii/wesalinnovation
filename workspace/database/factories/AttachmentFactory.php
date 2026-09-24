<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attachable_type' => 'project',
            'attachable_id' => Project::factory(),
            'path' => 'attachments/'.now()->format('Y/m').'/'.Str::uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 900000),
            'uploaded_by' => User::factory(),
        ];
    }
}
