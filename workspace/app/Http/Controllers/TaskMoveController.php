<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * تحريك المهمة بين أعمدة الكانبان: بالسحب والإفلات (طلب JSON)، أو بنموذج
 * «نقل إلى» العادي لمن يعمل بلوحة المفاتيح أو قارئ الشاشة.
 */
class TaskMoveController extends Controller
{
    public function __invoke(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        Gate::authorize('move', $task);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $status = TaskStatus::from($validated['status']);
        $task->moveTo($status, $validated['position'] ?? null);

        $message = 'نُقلت «'.$task->title.'» إلى '.$status->label().'.';

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'status' => $status->value, 'position' => $task->position])
            : back()->with('status', $message);
    }
}
