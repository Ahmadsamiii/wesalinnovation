<?php

namespace App\Http\Requests\Hr;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * البيانات الوظيفية وحدها: القسم والمسمى وتاريخ الانضمام. الاسم والبريد
 * والجوال والدور والتفعيل تملكها المنصة، فلا يقبلها هذا النموذج أصلاً.
 */
class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->hasRole('hr')) {
            return false;
        }

        /** @var User $employee */
        $employee = $this->route('user');

        // العميل ليس موظفاً: لا صفحة له هنا.
        abort_unless($employee->hasAnyRole(User::employeeRoles()), 404);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'joined_at' => ['nullable', 'date'],
        ];
    }
}
