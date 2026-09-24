<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('purchase_order');

        return $order
            ? $this->user()->can('update', $order)
            : $this->user()->can('create', PurchaseOrder::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', Rule::in(array_keys(self::projectOptions($this->user())))],
            'vendor_name' => ['required', 'string', 'max:255'],
            'vendor_contact' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'needed_by' => ['nullable', 'date'],
            ...PurchaseOrder::itemRules(),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! PurchaseOrder::withinTotalLimit((array) $this->input('items', []))) {
                    $validator->errors()->add('items', 'مجموع البنود يتجاوز الحد المسموح لمستند واحد.');
                }
            },
        ];
    }

    /**
     * لا شراء على مشروع لم يُعتمد بعد أو أُغلق: الإنفاق يتبع قرار الاعتماد.
     *
     * @return array<int, string>
     */
    public static function projectOptions(User $user): array
    {
        return Project::query()
            ->whereIn('status', [ProjectStatus::Approved, ProjectStatus::InProgress])
            ->when(! $user->hasAnyRole(['finance', 'executive']), fn ($query) => $query->where('pm_id', $user->id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
