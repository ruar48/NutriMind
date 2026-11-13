<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolYear;
use App\Services\Setup\AcademicCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;

/**
 * Makes a school year the active one (administrator only, see SchoolYearController).
 */
#[Middleware('role:admin')]
class SchoolYearActivationController extends Controller
{
    /**
     * Activate the school year: every other year and their quarters are deactivated, and the quarter that covers
     * today becomes current when the year has no current quarter yet.
     */
    public function update(SchoolYear $schoolYear, AcademicCalendar $calendar): RedirectResponse
    {
        $currentTerm = $calendar->activateSchoolYear($schoolYear);

        $message = $currentTerm !== null
            ? "{$schoolYear->name} is now the active school year, and {$currentTerm->name} is the current quarter."
            : "{$schoolYear->name} is now the active school year. No quarter covers today, so choose the current quarter.";

        return redirect()->back(fallback: route('admin.school-years.show', $schoolYear))->with('status', $message);
    }
}
