<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * البريد ليس هنا عمداً: هو هوية الدخول وعنوان الدعوات، ويغيّره مدير
     * النظام وحده.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ]{7,20}$/'],
        ];
    }
}
