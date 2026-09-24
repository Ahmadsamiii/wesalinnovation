<?php

namespace Database\Factories;

use App\Models\ReferenceLetter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferenceLetter>
 */
class ReferenceLetterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_id' => User::factory()->role('team_member')->state([
                'job_title' => 'مطور واجهات',
                'department' => 'الهندسة',
                'joined_at' => now()->subYears(2),
            ]),
            'purpose' => 'بنك',
        ];
    }
}
