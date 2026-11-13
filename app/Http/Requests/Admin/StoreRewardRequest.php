<?php

namespace App\Http\Requests\Admin;

use App\Enums\RewardType;
use App\Services\Content\RewardAppearance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreRewardRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', $this->uniqueNameRule()],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::enum(RewardType::class)],
            'icon' => ['required', 'string', Rule::in($this->allowedIcons())],
            'color' => ['required', 'string', Rule::in(RewardAppearance::COLORS)],
            'criteria' => ['nullable', 'string', 'max:255'],
            'points_required' => ['nullable', 'integer', 'between:1,100000'],
            'is_active' => ['required', 'boolean'],
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
            'type' => 'reward type',
            'criteria' => 'how to earn it',
            'points_required' => 'points required',
            'is_active' => 'active setting',
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
            'name.unique' => 'Another reward already uses this name.',
            'icon.in' => 'Choose one of the reward icons.',
            'color.in' => 'Choose one of the reward colors.',
        ];
    }

    /**
     * Get the rule that keeps reward names unique.
     */
    protected function uniqueNameRule(): Unique
    {
        return Rule::unique('rewards', 'name');
    }

    /**
     * Get the icons the reward may use.
     *
     * @return list<string>
     */
    protected function allowedIcons(): array
    {
        return RewardAppearance::ICONS;
    }
}
