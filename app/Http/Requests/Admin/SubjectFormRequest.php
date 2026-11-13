<?php

namespace App\Http\Requests\Admin;

use App\Models\Subject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The fields shared by the create and edit subject forms.
 */
abstract class SubjectFormRequest extends FormRequest
{
    /**
     * The palette keys a subject color can use.
     *
     * @var list<string>
     */
    public const COLORS = ['leaf', 'carrot', 'sun', 'berry', 'grape', 'sky', 'teal', 'lime', 'pink', 'indigo', 'slate'];

    /**
     * The curated icons a subject can use (every name is in the design-system icon set).
     *
     * @var list<string>
     */
    public const ICONS = [
        'book-open', 'book', 'book-marked', 'notebook-pen', 'library', 'graduation-cap', 'school', 'lightbulb', 'brain',
        'heart-pulse', 'heart', 'activity', 'flame', 'zap', 'target', 'apple', 'carrot', 'salad', 'leaf', 'sprout',
        'cherry', 'citrus', 'banana', 'wheat', 'egg', 'fish', 'milk', 'glass-water', 'droplet', 'utensils', 'cooking-pot',
        'sun', 'globe', 'palette', 'puzzle', 'shapes', 'gamepad-2', 'trophy', 'hand-heart', 'smile',
    ];

    /**
     * Get the subject being edited, or null while creating one.
     */
    abstract protected function subject(): ?Subject;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the code to uppercase before it is validated and checked for uniqueness.
     */
    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => Str::upper(trim($code))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('subjects', 'name')->ignore($this->subject())],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('subjects', 'code')->ignore($this->subject())],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['required', 'string', Rule::in(self::COLORS)],
            'icon' => ['required', 'string', Rule::in(self::ICONS)],
            'is_active' => ['nullable', 'boolean'],
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
            'name.unique' => 'Another subject already uses this name.',
            'code.unique' => 'Another subject already uses this code.',
            'code.regex' => 'The subject code may only contain letters, numbers and hyphens.',
            'color.in' => 'Choose one of the palette colors.',
            'icon.in' => 'Choose one of the subject icons.',
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
            'name' => 'subject name',
            'code' => 'subject code',
            'is_active' => 'active',
        ];
    }
}
