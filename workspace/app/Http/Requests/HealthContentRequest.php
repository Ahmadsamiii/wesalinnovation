<?php

namespace App\Http\Requests;

use App\Enums\HealthContentCategory;
use App\Models\HealthContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HealthContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $content = $this->route('content');

        return $content
            ? $this->user()->can('update', $content)
            : $this->user()->can('create', HealthContent::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(HealthContentCategory::class)],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:50000'],
            'source_url' => ['nullable', 'url:https,http', 'max:500'],
        ];
    }
}
