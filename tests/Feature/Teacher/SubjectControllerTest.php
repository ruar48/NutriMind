<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\LearningModule;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use App\Services\RolePermissions;
use Illuminate\Database\Eloquent\Collection;

it('sends guests to the sign-in page', function () {
    $this->get(route('teacher.subjects.index'))->assertRedirectToRoute('login');
});

it('forbids accounts of the other portals', function (UserRole $role) {
    $this->actingAs(User::factory()->{$role->value}()->create())
        ->get(route('teacher.subjects.index'))
        ->assertForbidden();
})->with([
    'administrator' => UserRole::Admin,
    'parent' => UserRole::Parent,
    'learner' => UserRole::Student,
]);

it('shows each active assigned subject with the teacher’s classrooms, learners, quizzes and game modules', function () {
    $health = Subject::factory()->create(['name' => 'Health', 'code' => 'HEALTH']);
    $science = Subject::factory()->create(['name' => 'Science', 'code' => 'SCIENCE']);
    $teacher = User::factory()->teaching($health, $science)->create();
    $grade5 = Classroom::factory()->for($teacher, 'teacher')->for($health)->create(['grade_level' => GradeLevel::Five]);
    $secondGrade5 = Classroom::factory()->for($teacher, 'teacher')->for($health)->create(['grade_level' => GradeLevel::Five]);
    $archived = Classroom::factory()->archived()->for($teacher, 'teacher')->for($health)->create(['grade_level' => GradeLevel::Six]);
    $othersClassroom = Classroom::factory()->for($health)->create(['grade_level' => GradeLevel::Five]);
    User::factory()->student(GradeLevel::Five)->enrolledIn($grade5, $secondGrade5)->create();
    User::factory()->student(GradeLevel::Five)->enrolledIn($grade5)->create();
    User::factory()->student(GradeLevel::Six)->enrolledIn($archived)->create();
    User::factory()->student(GradeLevel::Five)->enrolledIn($othersClassroom)->create();
    Quiz::factory()->published()->for($teacher, 'teacher')->for($health)->create();
    Quiz::factory()->for($teacher, 'teacher')->for($health)->create();
    Quiz::factory()->published()->for($othersClassroom->teacher, 'teacher')->for($health)->create();
    LearningModule::factory()->for($health)->count(2)->create(['grade_level' => GradeLevel::Five]);
    LearningModule::factory()->unpublished()->for($health)->create(['grade_level' => GradeLevel::Five]);
    LearningModule::factory()->for($health)->create(['grade_level' => GradeLevel::Six]);
    LearningModule::factory()->for($science)->create(['grade_level' => GradeLevel::Five]);
    LearningModule::factory()->for($science)->create(['grade_level' => GradeLevel::Six]);

    $response = $this->actingAs($teacher)->get(route('teacher.subjects.index'));

    $response->assertOk()
        ->assertViewIs('teacher.subjects.index')
        ->assertViewHas('subjects', function (Collection $subjects) use ($health, $science): bool {
            [$first, $second] = $subjects->all();

            return $subjects->modelKeys() === [$health->id, $science->id]
                && (int) $first->classrooms_count === 2
                && $first->learners_count === 2
                && (int) $first->quizzes_count === 2
                && (int) $first->published_quizzes_count === 1
                && $first->grade_levels === [GradeLevel::Five]
                && $first->modules_count === 2
                && (int) $second->classrooms_count === 0
                && $second->learners_count === 0
                && $second->grade_levels === []
                && $second->modules_count === 2;
        })
        ->assertSee('data-subject-card="'.$health->id.'"', false)
        ->assertSee('data-subject-stat="learners">2<', false)
        ->assertSeeText('HEALTH')
        ->assertSee('href="'.route('teacher.classrooms.index', ['subject' => $health->id]).'"', false)
        ->assertSee('href="'.route('teacher.classrooms.create', ['subject_id' => $health->id]).'"', false)
        ->assertSee('href="'.route('teacher.quizzes.create', ['subject_id' => $health->id]).'"', false)
        ->assertSee('href="'.route('teacher.question-bank.index', ['subject' => $health->id]).'"', false);

});

it('leaves out subjects that are not assigned or are inactive, and says how many inactive ones are hidden', function () {
    $active = Subject::factory()->create();
    $paused = Subject::factory()->inactive()->create();
    Subject::factory()->create();
    $teacher = User::factory()->teaching($active, $paused)->create();

    $this->actingAs($teacher)
        ->get(route('teacher.subjects.index'))
        ->assertOk()
        ->assertViewHas('subjects', fn (Collection $subjects): bool => $subjects->modelKeys() === [$active->id])
        ->assertViewHas('inactiveSubjectsCount', 1)
        ->assertSeeText('1 more subject is assigned to you but inactive, so it is hidden here.');
});

it('hides quick links the teacher has no permission for', function () {
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::CreateQuizzes, Permission::ManageQuestionBank, Permission::ManageClassrooms);

    $this->actingAs($teacher)
        ->get(route('teacher.subjects.index'))
        ->assertOk()
        ->assertSee('href="'.route('teacher.classrooms.index', ['subject' => $subject->id]).'"', false)
        ->assertDontSee('href="'.route('teacher.classrooms.create', ['subject_id' => $subject->id]).'"', false)
        ->assertDontSee('href="'.route('teacher.quizzes.create', ['subject_id' => $subject->id]).'"', false)
        ->assertDontSee('href="'.route('teacher.question-bank.index', ['subject' => $subject->id]).'"', false);
});

it('explains that the administrator assigns subjects when the teacher has none', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('teacher.subjects.index'))
        ->assertOk()
        ->assertSeeText('No subjects assigned yet')
        ->assertSeeText('Your school administrator assigns the subjects you teach.');
});

it('explains that the assigned subjects are inactive when none is active', function () {
    $teacher = User::factory()->teaching(Subject::factory()->inactive()->create())->create();

    $this->actingAs($teacher)
        ->get(route('teacher.subjects.index'))
        ->assertOk()
        ->assertSeeText('Your subjects are inactive');
});
