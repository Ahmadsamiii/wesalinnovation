<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * إنشاء المشروع وتعديله. مدير المشاريع ينشئ لنفسه؛ التنفيذي وحده يختار مدير
 * المشروع أو يغيّره.
 */
class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project
            ? $this->user()->can('update', $project)
            : $this->user()->can('create', Project::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'client_id' => ['nullable', Rule::in(self::activeUserIdsWithRole('client'))],
            'pm_id' => [
                Rule::requiredIf($this->user()->hasRole('executive')),
                Rule::prohibitedIf(! $this->user()->hasRole('executive')),
                'nullable',
                Rule::in(self::activeUserIdsWithRole('pm')),
            ],
            'priority' => ['required', Rule::enum(Priority::class)],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return list<int>
     */
    public static function activeUserIdsWithRole(string $role): array
    {
        return User::withRole($role)->active()->pluck('id')->all();
    }
}
