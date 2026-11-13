<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAcademicTermRequest;
use App\Http\Requests\Admin\UpdateAcademicTermRequest;
use App\Models\AcademicTerm;
use App\Models\SchoolYear;
use App\Services\Setup\AcademicCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Quarters (academic terms) of a school year: add, edit and delete (administrator only, see SchoolYearController).
 */
#[Middleware('role:admin')]
class AcademicTermController extends Controller
{
    /**
     * Show the form for a new quarter, prefilled with the next number and the days after the last quarter.
     */
    public function create(SchoolYear $schoolYear): View|RedirectResponse
    {
        $terms = $schoolYear->terms()->get();

        if ($terms->count() >= AcademicCalendar::MAX_TERMS) {
            return redirect()->route('admin.school-years.show', $schoolYear)
                ->with('error', "{$schoolYear->name} already has ".AcademicCalendar::MAX_TERMS.' quarters, the most a school year can have.');
        }

        $sequence = min(AcademicCalendar::MAX_TERMS, ((int) $terms->max('sequence')) + 1);
        $lastTerm = $terms->sortBy(fn (AcademicTerm $term): string => $term->ends_on->format('Y-m-d'))->last();
        $startsOn = $lastTerm?->ends_on->copy()->addDay() ?? $schoolYear->starts_on->copy();

        $term = new AcademicTerm([
            'name' => AcademicCalendar::quarterName($sequence),
            'sequence' => $sequence,
            'starts_on' => $startsOn->lessThanOrEqualTo($schoolYear->ends_on) ? $startsOn : null,
            'ends_on' => $startsOn->lessThan($schoolYear->ends_on) ? $schoolYear->ends_on->copy() : null,
        ]);

        return view('admin.terms.form', [
            'schoolYear' => $schoolYear,
            'term' => $term,
            'otherTerms' => $terms,
        ]);
    }

    /**
     * Add a quarter to the school year.
     */
    public function store(StoreAcademicTermRequest $request, SchoolYear $schoolYear): RedirectResponse
    {
        $term = $schoolYear->terms()->create($request->safe()->only(['name', 'sequence', 'starts_on', 'ends_on']));

        return redirect()->route('admin.school-years.show', $schoolYear)
            ->with('status', "{$term->name} was added to {$schoolYear->name}.");
    }

    /**
     * Show the form for editing a quarter.
     */
    public function edit(AcademicTerm $term): View
    {
        $schoolYear = $term->schoolYear;

        return view('admin.terms.form', [
            'schoolYear' => $schoolYear,
            'term' => $term,
            'otherTerms' => $schoolYear->terms()->whereKeyNot($term->getKey())->get(),
        ]);
    }

    /**
     * Update a quarter's name, number and dates.
     */
    public function update(UpdateAcademicTermRequest $request, AcademicTerm $term): RedirectResponse
    {
        $term->update($request->safe()->only(['name', 'sequence', 'starts_on', 'ends_on']));

        return redirect()->route('admin.school-years.show', $term->school_year_id)
            ->with('status', "{$term->name} was updated.");
    }

    /**
     * Delete a quarter, unless it is the current one. Quizzes and lesson plans linked to it lose the link.
     */
    public function destroy(AcademicTerm $term): RedirectResponse
    {
        if ($term->is_active) {
            return redirect()->route('admin.school-years.show', $term->school_year_id)
                ->with('error', "{$term->name} is the current quarter. Set another quarter as current first.");
        }

        $term->delete();

        return redirect()->route('admin.school-years.show', $term->school_year_id)
            ->with('status', "{$term->name} was deleted.");
    }
}
