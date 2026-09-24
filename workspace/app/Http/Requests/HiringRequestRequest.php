<?php

namespace App\Http\Requests;

use App\Enums\EmploymentType;
use App\Enums\ProjectStatus;
use App\Models\HiringRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HiringRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $hiringRequest = $this->route('hiring_request');

        return $hiringRequest
            ? $this->user()->can('update', $hiringRequest)
            : $this->user()->can('create', HiringRequest::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'headcount' => ['required', 'integer', 'min:1', 'max:20'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'justification' => ['required', 'string', 'max:5000'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'target_start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'project_id' => ['nullable', Rule::in(array_keys(self::projectOptions($this->user())))],
        ];
    }

    /**
     * وظيفة لمشروع: من مشاريع الطالب قيد التسليم فقط.
     *
     * @return array<int, string>
     */
    public static function projectOptions(User $user): array
    {
        return Project::query()
            ->where('pm_id', $user->id)
            ->whereIn('status', [ProjectStatus::Approved, ProjectStatus::InProgress, ProjectStatus::OnHold])
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
