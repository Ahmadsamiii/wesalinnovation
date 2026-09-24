<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'role' => ['required', Rule::in(array_keys(config('roles')))],
            'department' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ]{7,20}$/'],
            'joined_at' => ['nullable', 'date'],
        ];
    }
}
