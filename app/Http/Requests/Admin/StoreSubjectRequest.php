<?php

namespace App\Http\Requests\Admin;

use App\Models\Subject;

class StoreSubjectRequest extends SubjectFormRequest
{
    /**
     * Get the subject being edited: none, a new one is being created.
     */
    protected function subject(): ?Subject
    {
        return null;
    }
}
