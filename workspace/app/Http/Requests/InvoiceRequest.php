<?php

namespace App\Http\Requests;

use App\Enums\ContractStatus;
use App\Enums\ProjectStatus;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice
            ? $this->user()->can('update', $invoice)
            : $this->user()->can('create', Invoice::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', Rule::in(array_keys(self::projectOptions()))],
            // العقد من نفس المشروع وساري أو منتهٍ؛ لا فوترة على مسودة عقد.
            'contract_id' => ['nullable', Rule::exists('contracts', 'id')
                ->where('project_id', $this->integer('project_id'))
                ->whereIn('status', [ContractStatus::Active->value, ContractStatus::Completed->value])],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...Invoice::itemRules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! Invoice::withinTotalLimit((array) $this->input('items', []))) {
                    $validator->errors()->add('items', 'مجموع البنود يتجاوز الحد المسموح لمستند واحد.');
                }
            },
        ];
    }

    /**
     * فوترة ما اعتُمد: المشاريع المعتمدة والجارية والمنجزة.
     *
     * @return array<int, string>
     */
    public static function projectOptions(): array
    {
        return Project::query()
            ->whereIn('status', [ProjectStatus::Approved, ProjectStatus::InProgress, ProjectStatus::Completed])
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
