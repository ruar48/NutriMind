<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Services\Setup\AcademicCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;

/**
 * Makes a quarter the current quarter (administrator only, see SchoolYearController).
 */
#[Middleware('role:admin')]
class AcademicTermActivationController extends Controller
{
    /**
     * Make the quarter the only current quarter; its school year must be the active one.
     */
    public function update(AcademicTerm $term, AcademicCalendar $calendar): RedirectResponse
    {
        $schoolYear = $term->schoolYear;
        $fallback = route('admin.school-years.show', $schoolYear);

        if (! $schoolYear->is_active) {
            return redirect()->back(fallback: $fallback)->with('error', "Activate {$schoolYear->name} first.");
        }

        $calendar->activateTerm($term);

        return redirect()->back(fallback: $fallback)
            ->with('status', "{$term->name} is now the current quarter of {$schoolYear->name}.");
    }
}
