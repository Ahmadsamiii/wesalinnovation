<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * المهمة تُسند لمدير المشروع أو لأحد أعضائه فقط، وتُربط بمعلم من نفس المشروع.
 */
class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageTasks', $this->project());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->project();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'milestone_id' => ['nullable', Rule::exists('project_milestones', 'id')->where('project_id', $project->id)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'assignee_id' => ['nullable', Rule::in([
                ...self::assignableUserIds($project),
                // مهمة مسندة سابقاً لمن أُوقف حسابه تبقى قابلة للتعديل دون إجبار على نقلها.
                ...array_filter([$this->route('task')?->assignee_id]),
            ])],
            'due_date' => ['nullable', 'date'],
        ];
    }

    public function project(): Project
    {
        $task = $this->route('task');

        return $task instanceof Task ? $task->project : $this->route('project');
    }

    /**
     * @return list<int>
     */
    public static function assignableUserIds(Project $project): array
    {
        return $project->members()
            ->whereHas('user', fn ($query) => $query->whereNull('deactivated_at'))
            ->pluck('user_id')
            ->push($project->pm_id)
            ->unique()
            ->values()
            ->all();
    }
}
