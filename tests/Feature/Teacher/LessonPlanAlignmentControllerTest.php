<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use App\Policies\LessonPlanPolicy;
use App\Services\RolePermissions;
use Illuminate\Database\Eloquent\Collection;

/**
 * Create a Grade 5 lesson plan with content that fits it: two activities, a quiz of its teacher and a mission.
 *
 * @return array{lessonPlan: LessonPlan, activities: Collection<int, LearningActivity>, quiz: Quiz, mission: Mission}
 */
function lessonPlanAlignmentScenario(): array
{
    $subject = Subject::factory()->create();
    $teacher = User::factory()->teaching($subject)->create();
    $module = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);

    return [
        'lessonPlan' => LessonPlan::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]),
        'activities' => LearningActivity::factory()->for($module, 'module')->count(2)->create(),
        'quiz' => Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]),
        'mission' => Mission::factory()->for($subject)->create(['grade_level' => null]),
    ];
}

it('sends guests to the sign-in page', function () {
    $lessonPlan = LessonPlan::factory()->create();

    $this->put(route('teacher.lesson-plans.alignment.update', $lessonPlan), [])->assertRedirectToRoute('login');
});

it('forbids the other portals', function (UserRole $role) {
    $lessonPlan = LessonPlan::factory()->create();

    $this->actingAs(User::factory()->{$role->value}()->create())
        ->put(route('teacher.lesson-plans.alignment.update', $lessonPlan), [])
        ->assertForbidden();
})->with([UserRole::Admin, UserRole::Parent]);

it('hides another teacher\'s lesson plan', function () {
    ['lessonPlan' => $lessonPlan, 'activities' => $activities] = lessonPlanAlignmentScenario();
    $other = User::factory()->teaching($lessonPlan->subject)->create();

    $this->actingAs($other)
        ->put(route('teacher.lesson-plans.alignment.update', $lessonPlan), ['activities' => [$activities[0]->id]])
        ->assertNotFound();

    expect($lessonPlan->activities()->count())->toBe(0);
});

it('refuses the owner after the administrator revoked the subject', function () {
    $lessonPlan = LessonPlan::factory()->revokedSubject()->create();

    $this->actingAs($lessonPlan->teacher)
        ->put(route('teacher.lesson-plans.alignment.update', $lessonPlan), ['activities' => ['not-an-id']])
        ->assertForbidden()
        ->assertSeeText(LessonPlanPolicy::SUBJECT_REVOKED_MESSAGE)
        ->assertSessionHasNoErrors();
});

it('refuses teachers whose lesson plan permission was revoked', function () {
    ['lessonPlan' => $lessonPlan, 'activities' => $activities] = lessonPlanAlignmentScenario();
    app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ManageLessonPlans);

    $this->actingAs($lessonPlan->teacher)
        ->put(route('teacher.lesson-plans.alignment.update', $lessonPlan), ['activities' => [$activities[0]->id]])
        ->assertForbidden()
        ->assertSeeText('You do not have permission to manage lesson plans.');

    expect($lessonPlan->activities()->count())->toBe(0);
});

it('syncs the aligned activities, quizzes and missions', function () {
    ['lessonPlan' => $lessonPlan, 'activities' => $activities, 'quiz' => $quiz, 'mission' => $mission] = lessonPlanAlignmentScenario();
    $lessonPlan->activities()->attach($activities[0]);

    $this->actingAs($lessonPlan->teacher)
        ->put(route('teacher.lesson-plans.alignment.update', $lessonPlan), [
            'activities' => [$activities[1]->id],
            'quizzes' => [$quiz->id],
            'missions' => [$mission->id],
        ])
        ->assertRedirectToRoute('teacher.lesson-plans.show', $lessonPlan)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Alignment saved: 1 activity, 1 quiz and 1 mission.');

    expect($lessonPlan->activities()->pluck('learning_activities.id')->all())->toBe([$activities[1]->id])
        ->and($lessonPlan->quizzes()->pluck('quizzes.id')->all())->toBe([$quiz->id])
        ->and($lessonPlan->missions()->pluck('missions.id')->all())->toBe([$mission->id]);
});

it('summarizes several aligned items', function () {
    ['lessonPlan' => $lessonPlan, 'activities' => $activities] = lessonPlanAlignmentScenario();

    $this->actingAs($lessonPlan->teacher)
        ->put(route('teacher.lesson-plans.alignment.update', $lessonPlan), ['activities' => $activities->modelKeys()])
        ->assertSessionHas('status', 'Alignment saved: 2 activities, no quizzes and no missions.');
});

it('clears the alignment when nothing is selected', function () {
    ['lessonPlan' => $lessonPlan, 'activities' => $activities, 'quiz' => $quiz, 'mission' => $mission] = lessonPlanAlignmentScenario();
    $lessonPlan->activities()->attach($activities);
    $lessonPlan->quizzes()->attach($quiz);
    $lessonPlan->missions()->attach($mission);

    $this->actingAs($lessonPlan->teacher)
        ->put(route('teacher.lesson-plans.alignment.update', $lessonPlan), [])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Alignment saved: no activities, no quizzes and no missions.');

    $this->assertDatabaseMissing('learning_activity_lesson_plan', ['lesson_plan_id' => $lessonPlan->id]);
    $this->assertDatabaseMissing('lesson_plan_quiz', ['lesson_plan_id' => $lessonPlan->id]);
    $this->assertDatabaseMissing('lesson_plan_mission', ['lesson_plan_id' => $lessonPlan->id]);
});

it('keeps an aligned activity that was unpublished since', function () {
    ['lessonPlan' => $lessonPlan, 'activities' => $activities] = lessonPlanAlignmentScenario();
    $lessonPlan->activities()->attach($activities[0]);
    $activities[0]->update(['is_published' => false]);

    $this->actingAs($lessonPlan->teacher)
        ->put(route('teacher.lesson-plans.alignment.update', $lessonPlan), ['activities' => [$activities[0]->id]])
        ->assertSessionHasNoErrors();

    expect($lessonPlan->activities()->count())->toBe(1);
});

it('rejects content outside the plan\'s allowed sets', function (string $field, Closure $makeId, string $message) {
    ['lessonPlan' => $lessonPlan] = lessonPlanAlignmentScenario();
    $id = $makeId($lessonPlan);

    $this->actingAs($lessonPlan->teacher)
        ->put(route('teacher.lesson-plans.alignment.update', $lessonPlan), [$field => [$id]])
        ->assertSessionHasErrors([$field.'.0' => $message]);

    $this->assertDatabaseMissing('learning_activity_lesson_plan', ['lesson_plan_id' => $lessonPlan->id]);
    $this->assertDatabaseMissing('lesson_plan_quiz', ['lesson_plan_id' => $lessonPlan->id]);
    $this->assertDatabaseMissing('lesson_plan_mission', ['lesson_plan_id' => $lessonPlan->id]);
})->with([
    'activity of another subject' => ['activities', fn (LessonPlan $lessonPlan): int => LearningActivity::factory()->for(LearningModule::factory()->state(['grade_level' => GradeLevel::Five]), 'module')->create()->id, 'Choose activities from published modules of this lesson plan\'s subject and grade.'],
    'activity of another grade' => ['activities', fn (LessonPlan $lessonPlan): int => LearningActivity::factory()->for(LearningModule::factory()->for($lessonPlan->subject)->state(['grade_level' => GradeLevel::Six]), 'module')->create()->id, 'Choose activities from published modules of this lesson plan\'s subject and grade.'],
    'unpublished activity' => ['activities', fn (LessonPlan $lessonPlan): int => LearningActivity::factory()->for(LearningModule::factory()->for($lessonPlan->subject)->state(['grade_level' => GradeLevel::Five]), 'module')->unpublished()->create()->id, 'Choose activities from published modules of this lesson plan\'s subject and grade.'],
    'another teacher\'s quiz' => ['quizzes', fn (LessonPlan $lessonPlan): int => Quiz::factory()->for($lessonPlan->subject)->create(['grade_level' => GradeLevel::Five])->id, 'Choose your own quizzes for this lesson plan\'s subject and grade.'],
    'quiz of another grade' => ['quizzes', fn (LessonPlan $lessonPlan): int => Quiz::factory()->for($lessonPlan->teacher, 'teacher')->for($lessonPlan->subject)->create(['grade_level' => GradeLevel::Six])->id, 'Choose your own quizzes for this lesson plan\'s subject and grade.'],
    'mission of another grade' => ['missions', fn (LessonPlan $lessonPlan): int => Mission::factory()->for($lessonPlan->subject)->create(['grade_level' => GradeLevel::Six])->id, 'Choose active missions for this lesson plan\'s subject and grade.'],
    'inactive mission' => ['missions', fn (LessonPlan $lessonPlan): int => Mission::factory()->for($lessonPlan->subject)->inactive()->create(['grade_level' => null])->id, 'Choose active missions for this lesson plan\'s subject and grade.'],
    'unknown activity' => ['activities', fn (LessonPlan $lessonPlan): int => 999999, 'Choose activities from published modules of this lesson plan\'s subject and grade.'],
]);

it('rejects malformed selections', function (array $payload, string $field, string $message) {
    ['lessonPlan' => $lessonPlan, 'activities' => $activities] = lessonPlanAlignmentScenario();
    $payload = array_map(fn (mixed $value): mixed => $value === 'activity' ? [$activities[0]->id, $activities[0]->id] : $value, $payload);

    $this->actingAs($lessonPlan->teacher)
        ->put(route('teacher.lesson-plans.alignment.update', $lessonPlan), $payload)
        ->assertSessionHasErrors([$field => $message]);

    expect($lessonPlan->activities()->count())->toBe(0);
})->with([
    'not a list' => [['activities' => 'all'], 'activities', 'The activities field must be an array.'],
    'not an id' => [['quizzes' => ['abc']], 'quizzes.0', 'The quiz field must be an integer.'],
    'duplicate' => [['activities' => 'activity'], 'activities.0', 'The activity field has a duplicate value.'],
]);
