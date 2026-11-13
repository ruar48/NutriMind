<?php

namespace App\Http\Requests\Forum;

use App\Models\ForumThread;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateForumThreadRequest extends FormRequest
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
     * A thread may stay in its current category after the category was deactivated, but it can only move to an
     * active one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ForumThread $thread */
        $thread = $this->route('thread');

        return [
            'forum_category_id' => [
                'required',
                'integer',
                Rule::exists('forum_categories', 'id')->where(function (Builder $query) use ($thread): void {
                    $query->where('is_active', true)->orWhere('id', $thread->forum_category_id);
                }),
            ],
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
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
            'forum_category_id.exists' => 'Choose one of the open categories.',
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
            'forum_category_id' => 'category',
            'body' => 'message',
        ];
    }
}
