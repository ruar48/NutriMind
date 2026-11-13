<?php

namespace App\Http\Requests\Forum;

use App\Enums\ForumReportReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreForumReportRequest extends FormRequest
{
    /**
     * The error bag of the report form, so its errors never land on the reply form of the same page.
     *
     * @var string
     */
    protected $errorBag = 'report';

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
            'forum_thread_id' => ['required', 'integer', Rule::exists('forum_threads', 'id')],
            'forum_reply_id' => [
                'nullable',
                'integer',
                Rule::exists('forum_replies', 'id')->where('forum_thread_id', $this->integer('forum_thread_id')),
            ],
            'reason' => ['required', Rule::enum(ForumReportReason::class)],
            'details' => ['nullable', 'string', 'max:500'],
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
            'forum_thread_id.required' => 'Choose the post you want to report.',
            'forum_thread_id.exists' => 'This thread no longer exists.',
            'forum_reply_id.exists' => 'This reply is not part of the thread.',
            'reason.required' => 'Choose a reason for your report.',
            'reason.enum' => 'Choose one of the listed reasons.',
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
            'forum_thread_id' => 'thread',
            'forum_reply_id' => 'reply',
            'details' => 'details',
        ];
    }
}
