<?php

namespace App\Http\Controllers\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * فترة التقرير وتجميع التواريخ شهرياً. التجميع في PHP لا في SQL: دوال التاريخ
 * تختلف بين MySQL (الإنتاج) وSQLite (الاختبارات)، والبيانات هنا بالآلاف لا
 * بالملايين.
 */
trait BuildsMonthlySeries
{
    /**
     * @return array{months: int, from: Carbon, keys: list<string>, labels: list<string>, titles: list<string>}
     */
    protected function period(Request $request): array
    {
        $months = in_array($request->integer('months'), [3, 6, 12], true) ? $request->integer('months') : 6;
        $from = now()->startOfMonth()->subMonths($months - 1);

        $keys = [];
        $labels = [];
        $titles = [];
        for ($cursor = $from->copy(); $cursor->lessThanOrEqualTo(now()); $cursor->addMonth()) {
            $keys[] = $cursor->format('Y-m');
            $labels[] = $cursor->translatedFormat('F');
            $titles[] = $cursor->translatedFormat('F Y');
        }

        return ['months' => $months, 'from' => $from, 'keys' => $keys, 'labels' => $labels, 'titles' => $titles];
    }

    /**
     * يجمع قيمة كل سجل في شهره، ويعيد قيمة لكل شهر في الفترة (صفر لما لا سجل فيه).
     *
     * @param  Collection<int, mixed>  $records
     * @param  list<string>  $keys
     * @return list<float>
     */
    protected function monthly(Collection $records, array $keys, callable $date, ?callable $value = null): array
    {
        $totals = array_fill_keys($keys, 0.0);

        foreach ($records as $record) {
            $key = Carbon::parse($date($record))->format('Y-m');

            if (array_key_exists($key, $totals)) {
                $totals[$key] += $value ? (float) $value($record) : 1;
            }
        }

        return array_values($totals);
    }
}
