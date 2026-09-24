<?php

namespace App\Http\Controllers\Reports;

use App\Enums\AlertOutcome;
use App\Enums\ApprovalDecision;
use App\Enums\HealthContentCategory;
use App\Enums\HealthContentStatus;
use App\Http\Controllers\Controller;
use App\Models\HealthContent;
use App\Models\HealthContentReview;
use App\Models\QuestionAlertReview;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «تقارير المراجعة» للمدير الطبي: وتيرة القرارات وزمنها، وما نُشر حسب
 * الفئة، ونتائج مراجعة الأسئلة الحساسة.
 */
class MedicalReportController extends Controller
{
    use BuildsMonthlySeries;

    public function __invoke(Request $request): View
    {
        $period = $this->period($request);

        $reviews = HealthContentReview::query()->where('created_at', '>=', $period['from'])->get(['decision', 'submitted_at', 'created_at']);
        $decisionHours = $reviews
            ->filter(fn (HealthContentReview $review): bool => $review->submitted_at !== null)
            ->map(fn (HealthContentReview $review): float => $review->submitted_at->diffInMinutes($review->created_at) / 60)
            ->sort()
            ->values();
        $published = HealthContent::query()->published()->get(['category', 'review_due_on']);

        return view('reports.medical', [
            'period' => $period,
            'kpis' => [
                'inReview' => HealthContent::query()->where('status', HealthContentStatus::InReview)->count(),
                'approved' => $reviews->where('decision', ApprovalDecision::Approved)->count(),
                'rejected' => $reviews->where('decision', ApprovalDecision::Rejected)->count(),
                'medianHours' => $decisionHours->isEmpty() ? null : $decisionHours[intdiv($decisionHours->count() - 1, 2)],
                'published' => $published->count(),
                'dueSoon' => $published->filter(fn (HealthContent $content): bool => $content->review_due_on !== null && $content->review_due_on->lte(today()->addDays(30)))->count(),
            ],
            'approvedSeries' => $this->monthly($reviews->where('decision', ApprovalDecision::Approved), $period['keys'], fn (HealthContentReview $review) => $review->created_at),
            'rejectedSeries' => $this->monthly($reviews->where('decision', ApprovalDecision::Rejected), $period['keys'], fn (HealthContentReview $review) => $review->created_at),
            'byCategory' => $published
                ->countBy(fn (HealthContent $content): string => $content->category->value)
                ->map(fn (int $count, string $category): array => [
                    'label' => HealthContentCategory::from($category)->label(),
                    'value' => $count,
                    'url' => route('content.index', ['category' => $category, 'status' => HealthContentStatus::Approved->value]),
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
            'alertOutcomes' => QuestionAlertReview::query()
                ->where('reviewed_at', '>=', $period['from'])
                ->pluck('outcome')
                ->countBy(fn (AlertOutcome $outcome): string => $outcome->value)
                ->map(fn (int $count, string $outcome): array => ['label' => AlertOutcome::from($outcome)->label(), 'value' => $count])
                ->sortByDesc('value')
                ->values()
                ->all(),
        ]);
    }
}
