<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademicTerm;
use App\Models\SchoolYear;

class UpdateAcademicTermRequest extends AcademicTermFormRequest
{
    /**
     * Get the school year of the quarter being edited.
     */
    protected function schoolYear(): SchoolYear
    {
        return $this->term()->schoolYear;
    }

    /**
     * Get the quarter being edited (the route's term).
     */
    protected function term(): AcademicTerm
    {
        return $this->route('term');
    }
}
