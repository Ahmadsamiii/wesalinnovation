<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('sysadmin');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->route('user'))],
            'role' => ['required', Rule::in(array_keys(config('roles')))],
            'department' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ]{7,20}$/'],
            'joined_at' => ['nullable', 'date'],
        ];
    }

    /**
     * تغيير الدور محظور في حالتين: على النفس (مدير يخفّض نفسه بالخطأ)، وعلى
     * آخر مدير نظام قادر على الدخول (تُقفل إدارة الحسابات على نفسها).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $target */
                $target = $this->route('user');

                if ($this->input('role') === $target->roleName()) {
                    return;
                }

                if ($target->is($this->user())) {
                    $validator->errors()->add('role', 'لا يمكنك تغيير دورك بنفسك. اطلب ذلك من مدير نظام آخر.');
                } elseif ($target->isLastActiveSysadmin()) {
                    $validator->errors()->add('role', 'هذا آخر مدير نظام نشط؛ عيّن مديراً آخر قبل تغيير دوره.');
                }
            },
        ];
    }
}
