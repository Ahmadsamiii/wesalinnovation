<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('comment', $task);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $task->comments()->create([...$validated, 'user_id' => $request->user()->id]);

        return redirect()->to(route('tasks.show', $task).'#comments')->with('status', 'أُضيف تعليقك.');
    }

    /**
     * صاحب التعليق يحذفه، والإدارة تحذف أي تعليق.
     */
    public function destroy(Request $request, TaskComment $comment): RedirectResponse
    {
        $task = $comment->task;

        abort_unless(
            $comment->user_id === $request->user()->id || $request->user()->can('manageTasks', $task->project),
            403,
        );

        $comment->delete();

        return redirect()->to(route('tasks.show', $task).'#comments')->with('status', 'حُذف التعليق.');
    }
}
