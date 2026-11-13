<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\LessonPlan;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

$otherRoles = [
    'teacher' => UserRole::Teacher,
    'parent' => UserRole::Parent,
    'learner' => UserRole::Student,
];

describe('index', function () use ($otherRoles) {
    it('lists teachers with their teaching load', function () {
        $health = Subject::factory()->create(['name' => 'Health']);
        $science = Subject::factory()->create(['name' => 'Science']);
        $teacher = User::factory()->teaching($science, $health)->create(['name' => 'Maricel Santos']);
        $healthClass = Classroom::factory()->for($teacher, 'teacher')->for($health)->create();
        $scienceClass = Classroom::factory()->for($teacher, 'teacher')->for($science)->create();
        $archived = Classroom::factory()->archived()->for($teacher, 'teacher')->for($health)->create();
        User::factory()->student()->enrolledIn($healthClass, $scienceClass)->create();
        User::factory()->student()->enrolledIn($healthClass)->create();
        User::factory()->student()->enrolledIn($archived)->create();
        Quiz::factory()->published()->for($teacher, 'teacher')->for($health)->create();
        Quiz::factory()->for($teacher, 'teacher')->for($health)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.teachers.index'))
            ->assertOk()
            ->assertViewIs('admin.teachers.index')
            ->assertViewHas('teachers', function (LengthAwarePaginator $teachers) use ($teacher): bool {
                $row = $teachers->firstWhere('id', $teacher->id);

                return $row->active_classrooms_count === 2
                    && (int) $row->learners_count === 2
                    && $row->published_quizzes_count === 1
                    && $row->assignedSubjects->pluck('name')->all() === ['Health', 'Science'];
            })
            ->assertSeeText(['Maricel Santos', 'Health', 'Science']);
    });

    it('lists only teacher accounts', function () {
        $teacher = User::factory()->teacher()->create();
        User::factory()->parent()->create();
        User::factory()->student()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.teachers.index'))
            ->assertViewHas('teachers', fn (LengthAwarePaginator $teachers): bool => $teachers->pluck('id')->all() === [$teacher->id]);
    });

    it('warns about active teachers without subjects', function () {
        User::factory()->teacher()->create(['name' => 'Dennis Aquino']);
        User::factory()->teacher()->inactive()->create();
        User::factory()->teaching()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.teachers.index'))
            ->assertOk()
            ->assertViewHas('teachersWithoutSubjects', 1)
            ->assertSeeText('1 active teacher has no subject yet')
            ->assertSeeText('No subjects');
    });

    it('filters by assigned subject', function () {
        $health = Subject::factory()->create();
        $healthTeacher = User::factory()->teaching($health)->create();
        User::factory()->teaching()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.teachers.index', ['subject' => $health->id]))
            ->assertViewHas('teachers', fn (LengthAwarePaginator $teachers): bool => $teachers->pluck('id')->all() === [$healthTeacher->id]);
    });

    it('searches and filters by status', function () {
        $santos = User::factory()->teacher()->create(['name' => 'Maricel Santos']);
        User::factory()->teacher()->inactive()->create(['name' => 'Ramon Santos']);
        User::factory()->teacher()->create(['name' => 'Jonathan Villanueva']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.teachers.index', ['search' => 'santos', 'status' => 'active']))
            ->assertViewHas('teachers', fn (LengthAwarePaginator $teachers): bool => $teachers->pluck('id')->all() === [$santos->id]);
    });

    it('ignores tampered filters', function () {
        User::factory()->teacher()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.teachers.index', ['subject' => 'abc', 'status' => ['x'], 'sort' => 'password']))
            ->assertOk()
            ->assertViewHas('teachers', fn (LengthAwarePaginator $teachers): bool => $teachers->total() === 1);
    });

    it('sorts by last sign-in, most recent first', function () {
        $this->freezeSecond();
        User::factory()->teacher()->create(['name' => 'Older', 'last_login_at' => now()->subWeek()]);
        User::factory()->teacher()->create(['name' => 'Never', 'last_login_at' => null]);
        User::factory()->teacher()->create(['name' => 'Newer', 'last_login_at' => now()->subHour()]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.teachers.index', ['sort' => 'last_login_at', 'direction' => 'desc']))
            ->assertViewHas('teachers', fn (LengthAwarePaginator $teachers): bool => $teachers->pluck('name')->all() === ['Newer', 'Older', 'Never']);
    });

    it('renders several teachers with their actions without lazy loading', function () {
        $health = Subject::factory()->create();
        foreach (User::factory()->teaching($health)->count(3)->create() as $teacher) {
            Classroom::factory()->for($teacher, 'teacher')->for($health)->create();
        }
        User::factory()->teacher()->inactive()->create(['name' => 'Ramon Gutierrez', 'last_login_at' => null]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.teachers.index'))
            ->assertOk()
            ->assertSeeText(['Ramon Gutierrez', 'Never', 'Inactive', 'Reactivate'])
            ->assertSee('data-account-menu', false);
    });

    it('shows the empty states', function (array $query, string $text) {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.teachers.index', $query))
            ->assertOk()
            ->assertSeeText($text);
    })->with([
        'no teachers' => [[], 'No teachers yet'],
        'no matches' => [['search' => 'nobody'], 'No teachers match your filters'],
    ]);

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.teachers.index'))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.teachers.index'))
            ->assertForbidden();
    })->with($otherRoles);
});

describe('show', function () use ($otherRoles) {
    it('shows the teacher profile with classrooms, quiz counts and lesson plans', function () {
        $health = Subject::factory()->create(['name' => 'Health']);
        $teacher = User::factory()->teaching($health)->create(['name' => 'Maricel Santos']);
        $active = Classroom::factory()->for($teacher, 'teacher')->for($health)->create(['name' => 'Health 5 – Sampaguita']);
        Classroom::factory()->archived()->for($teacher, 'teacher')->for($health)->create(['name' => 'Health 5 – Old']);
        User::factory()->student()->enrolledIn($active)->count(3)->create();
        Quiz::factory()->published()->count(2)->for($teacher, 'teacher')->for($health)->create();
        Quiz::factory()->closed()->for($teacher, 'teacher')->for($health)->create();
        Quiz::factory()->for($teacher, 'teacher')->for($health)->create();
        LessonPlan::factory()->count(2)->for($teacher, 'teacher')->for($health)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.teachers.show', $teacher))
            ->assertOk()
            ->assertViewIs('admin.teachers.show')
            ->assertViewHas('quizCounts', ['draft' => 1, 'published' => 2, 'closed' => 1])
            ->assertViewHas('learnersCount', 3)
            ->assertViewHas('classrooms', fn ($classrooms): bool => $classrooms->pluck('name')->all() === ['Health 5 – Sampaguita', 'Health 5 – Old'])
            ->assertViewHas('teacher', fn (User $viewTeacher): bool => $viewTeacher->lesson_plans_count === 2)
            ->assertSeeText(['Maricel Santos', 'Health 5 – Sampaguita', 'Archived'])
            ->assertSee('data-delete-blocked', false)
            ->assertDontSee('action="'.route('admin.users.destroy', $teacher).'"', false);
    });

    it('offers deletion for a teacher without classrooms or quizzes', function () {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.teachers.show', $teacher))
            ->assertOk()
            ->assertSeeText(['No classrooms yet', 'No quizzes yet', 'No subjects yet'])
            ->assertSee('action="'.route('admin.users.destroy', $teacher).'"', false);
    });

    it('reports accounts that are not teachers as missing', function (Closure $makeUser) {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.teachers.show', $makeUser()))
            ->assertNotFound();
    })->with([
        'parent' => [fn (): User => User::factory()->parent()->create()],
        'learner' => [fn (): User => User::factory()->student()->create()],
        'administrator' => [fn (): User => User::factory()->admin()->create()],
    ]);

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.teachers.show', User::factory()->teacher()->create()))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.teachers.show', User::factory()->teacher()->create()))
            ->assertForbidden();
    })->with($otherRoles);
});
