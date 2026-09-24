<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Models\Contract;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contract = $this->route('contract');

        return $contract
            ? $this->user()->can('update', $contract)
            : $this->user()->can('create', Contract::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', Rule::in(array_keys(self::projectOptions($this->user())))],
            'title' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * مدير المشاريع يصوغ عقود مشاريعه؛ المالية لأي مشروع غير ملغى.
     *
     * @return array<int, string>
     */
    public static function projectOptions(User $user): array
    {
        return Project::query()
            ->where('status', '!=', ProjectStatus::Cancelled)
            ->when(! $user->hasAnyRole(['finance', 'executive']), fn ($query) => $query->where('pm_id', $user->id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
