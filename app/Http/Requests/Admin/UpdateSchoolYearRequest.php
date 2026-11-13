<?php

namespace App\Http\Requests\Admin;

use App\Models\SchoolYear;

class UpdateSchoolYearRequest extends SchoolYearFormRequest
{
    /**
     * Get the school year being edited (the route's school year).
     */
    protected function schoolYear(): ?SchoolYear
    {
        $schoolYear = $this->route('school_year');

        return $schoolYear instanceof SchoolYear ? $schoolYear : null;
    }
}
