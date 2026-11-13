<?php

namespace App\Http\Requests\Admin;

use App\Models\Subject;

class UpdateSubjectRequest extends SubjectFormRequest
{
    /**
     * Get the subject being edited (the route's subject).
     */
    protected function subject(): ?Subject
    {
        $subject = $this->route('subject');

        return $subject instanceof Subject ? $subject : null;
    }
}
