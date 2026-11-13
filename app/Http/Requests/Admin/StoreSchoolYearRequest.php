<?php

namespace App\Http\Requests\Admin;

use App\Models\SchoolYear;

class StoreSchoolYearRequest extends SchoolYearFormRequest
{
    /**
     * Get the school year being edited: none, a new one is being created.
     */
    protected function schoolYear(): ?SchoolYear
    {
        return null;
    }
}
