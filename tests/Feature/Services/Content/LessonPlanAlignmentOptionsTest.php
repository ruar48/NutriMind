<?php

use App\Enums\GradeLevel;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use App\Services\Content\LessonPlanAlignmentOptions;

describe('activities', function () {
    it('offers the published activities of published modules with the plan\'s subject and grade', function () {
        $subject = Subject::factory()->create();
        $lessonPlan = LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $module = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $offered = LearningActivity::factory()->for($module, 'module')->create();
        LearningActivity::factory()->for($module, 'module')->unpublished()->create();
        LearningActivity::factory()->for(LearningModule::factory()->for($subject)->unpublished()->state(['grade_level' => GradeLevel::Five]), 'module')->create();
        LearningActivity::factory()->for(LearningModule::factory()->for($subject)->state(['grade_level' => GradeLevel::Six]), 'module')->create();
        LearningActivity::factory()->for(LearningModule::factory()->state(['grade_level' => GradeLevel::Five]), 'module')->create();

        expect(app(LessonPlanAlignmentOptions::class)->activities($lessonPlan)->modelKeys())->toBe([$offered->id]);
    });

    it('keeps an aligned activity that was unpublished but drops one of another subject', function () {
        $subject = Subject::factory()->create();
        $lessonPlan = LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Six]);
        $unpublished = LearningActivity::factory()
            ->for(LearningModule::factory()->for($subject)->unpublished()->state(['grade_level' => GradeLevel::Six]), 'module')
            ->unpublished()
            ->create();
        $foreign = LearningActivity::factory()->for(LearningModule::factory()->state(['grade_level' => GradeLevel::Six]), 'module')->create();
        $lessonPlan->activities()->attach([$unpublished->id, $foreign->id]);

        expect(app(LessonPlanAlignmentOptions::class)->activities($lessonPlan)->modelKeys())->toBe([$unpublished->id]);
    });

    it('groups the activities by module with suggested modules first', function () {
        $subject = Subject::factory()->create();
        $ownModule = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'quarter' => 4, 'title' => 'Own Module', 'competency_code' => 'X-1']);
        $codeModule = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'quarter' => 3, 'title' => 'Code Module', 'competency_code' => 'h5n-ia-1']);
        $firstQuarter = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'quarter' => 1, 'title' => 'First Quarter', 'competency_code' => 'X-2']);
        $noQuarter = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'quarter' => null, 'title' => 'No Quarter', 'competency_code' => null]);
        foreach ([$ownModule, $codeModule, $firstQuarter, $noQuarter] as $module) {
            LearningActivity::factory()->for($module, 'module')->create(['sort_order' => 2]);
            LearningActivity::factory()->for($module, 'module')->create(['sort_order' => 1]);
        }
        $lessonPlan = LessonPlan::factory()->for($subject)->for($ownModule, 'module')->create(['competency_code' => 'H5N-IA-1']);

        $groups = app(LessonPlanAlignmentOptions::class)->activityGroups($lessonPlan);

        expect($groups->map(fn (array $group): array => [$group['module']->title, $group['suggested']])->all())->toBe([
            ['Code Module', true],
            ['Own Module', true],
            ['First Quarter', false],
            ['No Quarter', false],
        ])->and($groups[0]['activities']->pluck('sort_order')->all())->toBe([1, 2]);
    });
});

describe('quizzes', function () {
    it('offers the teacher\'s own quizzes with the plan\'s subject and grade', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $lessonPlan = LessonPlan::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $draft = Quiz::factory()->for($teacher, 'teacher')->for($subject)->hasQuestions(3)->create(['grade_level' => GradeLevel::Five]);
        $published = Quiz::factory()->published()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five, 'created_at' => now()->addMinute()]);
        Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Six]);
        Quiz::factory()->for($teacher, 'teacher')->create(['grade_level' => GradeLevel::Five]);
        Quiz::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);

        $quizzes = app(LessonPlanAlignmentOptions::class)->quizzes($lessonPlan);

        expect($quizzes->modelKeys())->toBe([$published->id, $draft->id])
            ->and($quizzes->firstWhere('id', $draft->id)->questions_count)->toBe(3);
    });
});

describe('missions', function () {
    it('offers the active missions of the plan\'s subject for its grade or both grades', function () {
        $subject = Subject::factory()->create();
        $lessonPlan = LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Six]);
        $sameGrade = Mission::factory()->for($subject)->create(['grade_level' => GradeLevel::Six, 'sort_order' => 2]);
        $bothGrades = Mission::factory()->for($subject)->create(['grade_level' => null, 'sort_order' => 1]);
        $inactiveAligned = Mission::factory()->for($subject)->inactive()->create(['grade_level' => GradeLevel::Six, 'sort_order' => 3]);
        Mission::factory()->for($subject)->inactive()->create(['grade_level' => GradeLevel::Six]);
        Mission::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);
        Mission::factory()->create(['grade_level' => GradeLevel::Six]);
        $lessonPlan->missions()->attach($inactiveAligned);

        expect(app(LessonPlanAlignmentOptions::class)->missions($lessonPlan)->modelKeys())
            ->toBe([$bothGrades->id, $sameGrade->id, $inactiveAligned->id]);
    });
});

describe('isSuggestedModule', function () {
    it('suggests the plan\'s own module and modules with its competency code', function () {
        $lessonPlan = LessonPlan::factory()->for(LearningModule::factory(), 'module')->create(['competency_code' => 'H6N-IIA-1']);

        $options = app(LessonPlanAlignmentOptions::class);

        expect($options->isSuggestedModule($lessonPlan, $lessonPlan->module))->toBeTrue()
            ->and($options->isSuggestedModule($lessonPlan, LearningModule::factory()->make(['competency_code' => 'h6n-iia-1'])))->toBeTrue()
            ->and($options->isSuggestedModule($lessonPlan, LearningModule::factory()->make(['competency_code' => 'H6N-IIA-2'])))->toBeFalse()
            ->and($options->isSuggestedModule($lessonPlan, null))->toBeFalse();
    });
});

describe('prune', function () {
    it('removes the alignments that no longer match the plan\'s subject and grade', function () {
        $subject = Subject::factory()->create();
        $teacher = User::factory()->teaching($subject)->create();
        $lessonPlan = LessonPlan::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $keptActivity = LearningActivity::factory()->for(LearningModule::factory()->for($subject)->state(['grade_level' => GradeLevel::Five]), 'module')->create();
        $staleActivity = LearningActivity::factory()->for(LearningModule::factory()->for($subject)->state(['grade_level' => GradeLevel::Six]), 'module')->create();
        $keptQuiz = Quiz::factory()->for($teacher, 'teacher')->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $staleQuiz = Quiz::factory()->for($subject)->create(['grade_level' => GradeLevel::Five]);
        $keptMission = Mission::factory()->for($subject)->create(['grade_level' => null]);
        $staleMission = Mission::factory()->for($subject)->create(['grade_level' => GradeLevel::Six]);
        $lessonPlan->activities()->attach([$keptActivity->id, $staleActivity->id]);
        $lessonPlan->quizzes()->attach([$keptQuiz->id, $staleQuiz->id]);
        $lessonPlan->missions()->attach([$keptMission->id, $staleMission->id]);

        $removed = app(LessonPlanAlignmentOptions::class)->prune($lessonPlan);

        expect($removed)->toBe(3)
            ->and($lessonPlan->activities()->pluck('learning_activities.id')->all())->toBe([$keptActivity->id])
            ->and($lessonPlan->quizzes()->pluck('quizzes.id')->all())->toBe([$keptQuiz->id])
            ->and($lessonPlan->missions()->pluck('missions.id')->all())->toBe([$keptMission->id]);
    });

    it('removes nothing from a plan whose alignments still match', function () {
        $lessonPlan = LessonPlan::factory()->create(['grade_level' => GradeLevel::Five]);
        $lessonPlan->activities()->attach(LearningActivity::factory()->for(LearningModule::factory()->for($lessonPlan->subject)->state(['grade_level' => GradeLevel::Five]), 'module')->create());

        expect(app(LessonPlanAlignmentOptions::class)->prune($lessonPlan))->toBe(0)
            ->and($lessonPlan->activities()->count())->toBe(1);
    });
});
