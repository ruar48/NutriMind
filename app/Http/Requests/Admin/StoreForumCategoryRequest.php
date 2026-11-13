<?php

namespace App\Http\Requests\Admin;

use App\Services\Forum\ForumCategories;
use App\View\Components\Icon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreForumCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:60', Rule::unique('forum_categories', 'name')],
            'description' => ['nullable', 'string', 'max:200'],
            'color' => ['required', 'string', Rule::in(ForumCategories::COLORS)],
            'icon' => ['required', 'string', Rule::in(Icon::names())],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Another category already uses this name.',
            'color.in' => 'Choose one of the listed colors.',
            'icon.in' => 'Choose one of the listed icons.',
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
            'sort_order' => 'display order',
            'is_active' => 'status',
        ];
    }
}
