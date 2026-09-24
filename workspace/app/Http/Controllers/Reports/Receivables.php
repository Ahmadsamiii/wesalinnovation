<?php

namespace App\Http\Controllers\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

/**
 * أعمار الذمم: المتبقي على الفواتير المصدرة موزّعاً على شرائح التأخر عن
 * تاريخ الاستحقاق.
 */
trait Receivables
{
    /**
     * @param  Builder<Invoice>|null  $scope
     * @return list<array{label: string, value: float}>
     */
    protected function agingBuckets(?Builder $scope = null): array
    {
        $buckets = ['لم يستحق بعد' => 0.0, '١ إلى ٣٠ يوماً' => 0.0, '٣١ إلى ٦٠ يوماً' => 0.0, '٦١ إلى ٩٠ يوماً' => 0.0, 'أكثر من ٩٠ يوماً' => 0.0];

        ($scope ?? Invoice::query())
            ->where('status', InvoiceStatus::Issued)
            ->get(['due_date', 'total', 'paid_amount'])
            ->each(function (Invoice $invoice) use (&$buckets): void {
                $late = $invoice->due_date && $invoice->due_date->isPast() ? (int) $invoice->due_date->diffInDays(today()) : 0;
                $key = match (true) {
                    $late <= 0 => 'لم يستحق بعد',
                    $late <= 30 => '١ إلى ٣٠ يوماً',
                    $late <= 60 => '٣١ إلى ٦٠ يوماً',
                    $late <= 90 => '٦١ إلى ٩٠ يوماً',
                    default => 'أكثر من ٩٠ يوماً',
                };
                $buckets[$key] += (float) $invoice->balance();
            });

        return collect($buckets)->map(fn (float $value, string $label): array => ['label' => $label, 'value' => $value])->values()->all();
    }
}
