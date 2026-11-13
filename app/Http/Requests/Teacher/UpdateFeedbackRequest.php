<?php

namespace App\Http\Requests\Teacher;

use App\Models\LearnerFeedback;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates changes to learner feedback. The learner cannot change; the classroom and attempt are always checked
 * against the feedback's learner and the teacher.
 */
class UpdateFeedbackRequest extends StoreFeedbackRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->feedbackRules();
    }

    /**
     * Get the learner the feedback is about.
     */
    protected function feedbackStudent(): ?User
    {
        $feedback = $this->route('feedback');

        return $feedback instanceof LearnerFeedback ? $feedback->student : null;
    }

    /**
     * The checks always apply: the feedback stays editable after the learner leaves the teacher's classrooms, and its
     * classroom and attempt must still be the teacher's.
     */
    protected function checksTargetsOf(User $student): bool
    {
        return true;
    }
}
