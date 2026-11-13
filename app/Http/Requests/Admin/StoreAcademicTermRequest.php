<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademicTerm;
use App\Models\SchoolYear;

class StoreAcademicTermRequest extends AcademicTermFormRequest
{
    /**
     * Get the school year the quarter is added to (the route's school year).
     */
    protected function schoolYear(): SchoolYear
    {
        return $this->route('school_year');
    }

    /**
     * Get the quarter being edited: none, a new one is being added.
     */
    protected function term(): ?AcademicTerm
    {
        return null;
    }
}
