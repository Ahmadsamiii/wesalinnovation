<?php

namespace App\Http\Controllers\Reports;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «تقرير مهامي» لعضو الفريق: ما أنجزه في الفترة، وهل كان في الموعد، وما
 * زال مفتوحاً عليه.
 */
class MyTasksReportController extends Controller
{
    use BuildsMonthlySeries, WritesCsv;

    public function __invoke(Request $request): View|StreamedResponse
    {
        $user = $request->user();
        $period = $this->period($request);

        $completed = Task::query()
            ->where('assignee_id', $user->id)
            ->where('status', TaskStatus::Done)
            ->where('completed_at', '>=', $period['from'])
            ->with('project:id,name')
            ->latest('completed_at')
            ->get();

        if ($request->query('export') === 'tasks') {
            return $this->exportTasks($completed);
        }

        $withDueDate = $completed->filter(fn (Task $task): bool => $task->due_date !== null);

        return view('reports.mine', [
            'period' => $period,
            'recent' => $completed->take(15),
            'kpis' => [
                'open' => Task::query()->where('assignee_id', $user->id)->open()->count(),
                'overdue' => Task::query()->where('assignee_id', $user->id)->overdue()->count(),
                'completed' => $completed->count(),
                'onTimeRate' => $withDueDate->isEmpty()
                    ? null
                    : (int) round($withDueDate->filter(self::finishedOnTime(...))->count() * 100 / $withDueDate->count()),
            ],
            'completedSeries' => $this->monthly($completed, $period['keys'], fn (Task $task) => $task->completed_at),
            'byProject' => $completed
                ->groupBy('project_id')
                ->map(fn (Collection $tasks): array => ['label' => $tasks->first()->project->name, 'value' => $tasks->count()])
                ->sortByDesc('value')
                ->values()
                ->all(),
        ]);
    }

    /**
     * في الموعد: أُنجزت في يوم الاستحقاق أو قبله.
     */
    private static function finishedOnTime(Task $task): bool
    {
        return $task->due_date !== null && $task->completed_at->toDateString() <= $task->due_date->toDateString();
    }

    /**
     * @param  Collection<int, Task>  $tasks
     */
    private function exportTasks(Collection $tasks): StreamedResponse
    {
        return $this->csvDownload(
            'my-completed-tasks-'.today()->toDateString().'.csv',
            ['المهمة', 'المشروع', 'الاستحقاق', 'تاريخ الإنجاز', 'في الموعد'],
            $tasks->map(fn (Task $task): array => [
                $task->title,
                $task->project->name,
                $task->due_date?->toDateString(),
                $task->completed_at->toDateString(),
                $task->due_date === null ? '—' : (self::finishedOnTime($task) ? 'نعم' : 'لا'),
            ]),
        );
    }
}
