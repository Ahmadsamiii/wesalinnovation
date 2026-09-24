<?php

namespace Database\Factories;

use App\Enums\HealthContentCategory;
use App\Enums\HealthContentStatus;
use App\Models\HealthContent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthContent>
 */
class HealthContentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => HealthContentCategory::HealthCare,
            'title' => 'العناية بقرح الفراش لمستخدمي الكراسي المتحركة',
            'summary' => 'علامات الإنذار المبكر وخطوات الوقاية اليومية.',
            'body' => "تغيير وضعية الجلوس كل ١٥ إلى ٣٠ دقيقة يخفف الضغط على الجلد.\n\nراجع الطبيب فوراً عند احمرار لا يزول أو جرح مفتوح.",
            'author_id' => User::factory()->role('sysadmin'),
            'status' => HealthContentStatus::Draft,
        ];
    }

    public function inReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => HealthContentStatus::InReview,
            'version' => 1,
            'submitted_at' => now(),
        ]);
    }
}
