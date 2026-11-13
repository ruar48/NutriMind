<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexStudentRequest;
use App\Models\Classroom;
use App\Models\User;
use App\Services\CsvExport;
use App\Services\LevelCalculator;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download the learners list as CSV, with the same filters and order as the list page.
 */
class StudentExportController extends Controller
{
    /**
     * The CSV headings, in column order.
     *
     * @var list<string>
     */
    public const HEADINGS = ['Name', 'Username', 'LRN', 'Grade', 'Sex', 'Birthdate', 'Classrooms', 'Guardians', 'Points', 'Level', 'Status', 'Last active'];

    /**
     * Stream the filtered learners (read in chunks while the file is sent).
     */
    public function __invoke(IndexStudentRequest $request, CsvExport $csv, LevelCalculator $levels): StreamedResponse
    {
        $students = $request->students()->with([
            'studentProfile',
            'enrolledClassrooms' => fn (BelongsToMany $classrooms): BelongsToMany => $classrooms->active()->orderBy('name')->orderBy('classrooms.id'),
            'guardians' => fn (BelongsToMany $guardians): BelongsToMany => $guardians->orderBy('name')->orderBy('users.id'),
        ]);

        return $csv->download('learners-'.now()->format('Y-m-d'), self::HEADINGS, $students->lazy()->map(
            fn (User $student): array => [
                $student->name,
                $student->username,
                $student->studentProfile?->lrn,
                $student->studentProfile?->grade_level,
                $student->studentProfile?->sex,
                $student->studentProfile?->birthdate?->toDateString(),
                $student->enrolledClassrooms->map(fn (Classroom $classroom): string => $classroom->name)->join('; '),
                $student->guardians->map(fn (User $guardian): string => $guardian->name.' ('.$guardian->pivot->relationship->label().')')->join('; '),
                (int) ($student->studentProfile?->total_points ?? 0),
                $levels->levelFor((int) ($student->studentProfile?->total_points ?? 0)),
                $student->is_active ? 'Active' : 'Inactive',
                $student->studentProfile?->last_active_at,
            ],
        ));
    }
}
