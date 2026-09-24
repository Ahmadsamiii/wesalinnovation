<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\HiringRequestStatus;
use App\Models\HiringRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HiringRequest>
 */
class HiringRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requested_by' => User::factory()->role('pm'),
            'title' => fake()->randomElement(['مطور واجهات', 'محلل بيانات', 'مصمم تجربة مستخدم', 'محاسب']),
            'department' => 'الهندسة',
            'headcount' => 1,
            'employment_type' => EmploymentType::FullTime,
            'justification' => 'الحمل الحالي يتجاوز طاقة الفريق.',
            'status' => HiringRequestStatus::Pending,
        ];
    }

    public function approved(?User $by = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => HiringRequestStatus::Approved,
            'decided_by' => $by?->id ?? User::factory()->role('executive'),
            'decided_at' => now(),
        ]);
    }
}
