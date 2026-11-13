<?php

namespace App\Http\Requests\Admin;

use App\Enums\ActivityType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLearningActivityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(ActivityType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'content_url' => ['nullable', 'string', 'url:http,https', 'max:255'],
            'estimated_minutes' => ['nullable', 'integer', 'between:1,240'],
            'points' => ['required', 'integer', 'between:0,1000'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
            'is_published' => ['required', 'boolean'],
        ];
    }

    /**
     * Get the field names used in validation messages, matching the form labels.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'activity type',
            'content_url' => 'content link',
            'estimated_minutes' => 'estimated time',
            'sort_order' => 'play order',
            'is_published' => 'published setting',
        ];
    }
}
