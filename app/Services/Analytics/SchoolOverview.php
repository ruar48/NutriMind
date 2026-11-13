<?php

namespace App\Services\Analytics;

use App\Enums\ForumContentStatus;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Classroom;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The school-wide facts of the administrator dashboard that are not learning analytics: head counts, the setup
 * checklist, recent sign-ins and the moderation queue.
 */
class SchoolOverview
{
    /**
     * Count the active accounts per role and the non-archived classrooms.
     *
     * @return array{learners: int, teachers: int, parents: int, administrators: int, classrooms: int}
     */
    public function headcounts(): array
    {
        $accounts = User::query()
            ->active()
            ->toBase()
            ->select('role')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return [
            'learners' => (int) ($accounts[UserRole::Student->value] ?? 0),
            'teachers' => (int) ($accounts[UserRole::Teacher->value] ?? 0),
            'parents' => (int) ($accounts[UserRole::Parent->value] ?? 0),
            'administrators' => (int) ($accounts[UserRole::Admin->value] ?? 0),
            'classrooms' => Classroom::query()->active()->count(),
        ];
    }

    /**
     * Get the setup steps the school needs before learners can play: an active school year and quarter, an
     * active subject, every active teacher assigned a subject and every active learner in a non-archived classroom.
     *
     * Each step links to the page that completes it; done is false while the step is missing.
     *
     * @param  array{learners: int, teachers: int}  $headcounts  The result of headcounts().
     * @return list<array{key: string, title: string, description: string, done: bool, action: string, url: string}>
     */
    public function setupChecklist(array $headcounts, ?AcademicTerm $currentTerm): array
    {
        $schoolYear = SchoolYear::current();
        $activeSubjects = Subject::query()->active()->count();

        $teachersWithoutSubjects = User::query()->ofRole(UserRole::Teacher)->active()->whereDoesntHave('assignedSubjects')->count();
        $learnersWithoutClassrooms = User::query()
            ->ofRole(UserRole::Student)
            ->active()
            ->whereDoesntHave('enrolledClassrooms', fn (Builder $classrooms) => $classrooms->active())
            ->count();

        return [
            [
                'key' => 'school-year',
                'title' => 'Activate a school year',
                'description' => $schoolYear === null
                    ? 'No school year is active. Classrooms and quarters belong to the active school year.'
                    : 'SY '.$schoolYear->name.' is active.',
                'done' => $schoolYear !== null,
                'action' => 'Set up school years',
                'url' => route('admin.school-years.index'),
            ],
            [
                'key' => 'quarter',
                'title' => 'Activate the current quarter',
                'description' => $currentTerm === null
                    ? 'No quarter of the active school year is active. Quizzes and lesson plans are grouped by quarter.'
                    : $currentTerm->name.' is active.',
                'done' => $currentTerm !== null,
                'action' => 'Manage quarters',
                'url' => $schoolYear === null ? route('admin.school-years.index') : route('admin.school-years.show', $schoolYear),
            ],
            [
                'key' => 'subjects',
                'title' => 'Add the subjects you teach',
                'description' => $activeSubjects === 0
                    ? 'There is no active subject yet. Teachers and classrooms are organized by subject.'
                    : trans_choice(':count active subject|:count active subjects', $activeSubjects).'.',
                'done' => $activeSubjects > 0,
                'action' => 'Add a subject',
                'url' => route('admin.subjects.create'),
            ],
            [
                'key' => 'subject-assignments',
                'title' => 'Assign subjects to every teacher',
                'description' => match (true) {
                    $headcounts['teachers'] === 0 => 'There are no teacher accounts yet. Add your teachers, then assign their subjects.',
                    $teachersWithoutSubjects > 0 => trans_choice(':count teacher has|:count teachers have', $teachersWithoutSubjects).' no subject yet and cannot create classrooms or quizzes.',
                    default => 'Every teacher has at least one subject.',
                },
                'done' => $headcounts['teachers'] > 0 && $teachersWithoutSubjects === 0,
                'action' => $headcounts['teachers'] === 0 ? 'Add a teacher' : 'Assign subjects',
                'url' => $headcounts['teachers'] === 0 ? route('admin.users.create') : route('admin.subject-assignments.index'),
            ],
            [
                'key' => 'enrollment',
                'title' => 'Enroll every learner in a classroom',
                'description' => match (true) {
                    $headcounts['learners'] === 0 => 'There are no learner accounts yet. Add learners or import a class list.',
                    $learnersWithoutClassrooms > 0 => trans_choice(':count learner is|:count learners are', $learnersWithoutClassrooms).' not in any classroom, so their game progress reaches no teacher.',
                    default => 'Every learner is enrolled in a classroom.',
                },
                'done' => $headcounts['learners'] > 0 && $learnersWithoutClassrooms === 0,
                'action' => $headcounts['learners'] === 0 ? 'Import learners' : 'Review learners',
                'url' => $headcounts['learners'] === 0 ? route('admin.students.import.create') : route('admin.students.index'),
            ],
        ];
    }

    /**
     * Get the staff and parent accounts that signed in most recently.
     *
     * @return Collection<int, User>
     */
    public function recentSignIns(int $limit = 6): Collection
    {
        return User::query()
            ->whereIn('role', array_map(fn (UserRole $role): string => $role->value, UserRole::webPortals()))
            ->whereNotNull('last_login_at')
            ->latest('last_login_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Get the forum moderation queue: unresolved reports, threads waiting for approval and the latest reports
     * (with thread, reply and reporter).
     *
     * @return array{reports: int, pendingThreads: int, latestReports: Collection<int, ForumReport>}
     */
    public function moderationQueue(int $limit = 3): array
    {
        return [
            'reports' => ForumReport::query()->unresolved()->count(),
            'pendingThreads' => ForumThread::query()->where('status', ForumContentStatus::Pending)->count(),
            'latestReports' => ForumReport::query()
                ->unresolved()
                ->with(['thread', 'reply', 'reporter'])
                ->latest()
                ->orderByDesc('id')
                ->limit($limit)
                ->get(),
        ];
    }
}
