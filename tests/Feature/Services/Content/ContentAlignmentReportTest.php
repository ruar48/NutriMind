<?php

use App\Enums\GradeLevel;
use App\Models\AcademicTerm;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use App\Services\Content\CompetencyCoverage;
use App\Services\Content\ContentAlignmentReport;
use App\Services\Content\CoverageStatus;
use App\View\Components\Icon;

describe('competencies', function () {
    it('groups coded lesson plans by subject, grade and code, ignoring the code\'s case', function () {
        $health = Subject::factory()->create(['name' => 'Health']);
        $science = Subject::factory()->create(['name' => 'Science']);
        $teacher = User::factory()->teacher()->create();
        LessonPlan::factory()->for($teacher, 'teacher')->for($health)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IA-1']);
        LessonPlan::factory()->for($teacher, 'teacher')->for($health)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'h5n-ia-1']);
        LessonPlan::factory()->for($health)->create(['grade_level' => GradeLevel::Five, 'competency_code' => ' H5N-IA-1 ']);
        LessonPlan::factory()->for($health)->create(['grade_level' => GradeLevel::Six, 'competency_code' => 'H5N-IA-1']);
        LessonPlan::factory()->for($science)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'S5LT-IIA-1']);
        LessonPlan::factory()->for($health)->create(['grade_level' => GradeLevel::Five, 'competency_code' => null]);

        $competencies = app(ContentAlignmentReport::class)->competencies();

        expect($competencies->map(fn (CompetencyCoverage $coverage): array => [
            $coverage->subject->name, $coverage->grade->value, $coverage->code, $coverage->plansCount, $coverage->teachersCount,
        ])->all())->toBe([
            ['Health', 5, 'H5N-IA-1', 3, 2],
            ['Health', 6, 'H5N-IA-1', 1, 1],
            ['Science', 5, 'S5LT-IIA-1', 1, 1],
        ]);
    });

    it('matches modules of the same subject, grade and code and counts their content', function () {
        $subject = Subject::factory()->create();
        LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IB-2']);
        $published = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'h5n-ib-2', 'title' => 'The Balanced Plate']);
        LearningActivity::factory()->for($published, 'module')->count(2)->create();
        LearningActivity::factory()->for($published, 'module')->unpublished()->create();
        Mission::factory()->for($published, 'module')->count(2)->create();
        $draft = LearningModule::factory()->for($subject)->unpublished()->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IB-2', 'title' => 'Plate Planner']);
        LearningActivity::factory()->for($draft, 'module')->create();
        LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Six, 'competency_code' => 'H5N-IB-2']);
        LearningModule::factory()->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IB-2']);

        $coverage = app(ContentAlignmentReport::class)->competencies()->sole();

        expect($coverage->modules->pluck('title')->sort()->values()->all())->toBe(['Plate Planner', 'The Balanced Plate'])
            ->and($coverage->publishedActivitiesCount)->toBe(2)
            ->and($coverage->missionsCount)->toBe(2)
            ->and($coverage->status)->toBe(CoverageStatus::Covered);
    });

    it('counts the distinct quizzes aligned to the group\'s lesson plans', function () {
        $subject = Subject::factory()->create();
        $quiz = Quiz::factory()->for($subject)->create();
        $first = LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Six, 'competency_code' => 'H6N-IIC-4']);
        $second = LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Six, 'competency_code' => 'H6N-IIC-4']);
        $first->quizzes()->attach([$quiz->id, Quiz::factory()->for($subject)->create()->id]);
        $second->quizzes()->attach($quiz);
        LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Six, 'competency_code' => 'H6N-IIIA-1'])
            ->quizzes()->attach(Quiz::factory()->for($subject)->create());

        $competencies = app(ContentAlignmentReport::class)->competencies()->keyBy('code');

        expect($competencies['H6N-IIC-4'])
            ->quizzesCount->toBe(2)
            ->status->toBe(CoverageStatus::Partial)
            ->and($competencies['H6N-IIIA-1']->quizzesCount)->toBe(1);
    });

    it('marks a competency without modules or quizzes as not covered', function () {
        LessonPlan::factory()->create(['competency_code' => 'EPP5HE-IIB-3']);

        $coverage = app(ContentAlignmentReport::class)->competencies()->sole();

        expect($coverage)
            ->modules->toBeEmpty()
            ->publishedActivitiesCount->toBe(0)
            ->quizzesCount->toBe(0)
            ->status->toBe(CoverageStatus::NotCovered);
    });

    it('treats a draft module with published activities as partial coverage', function () {
        $subject = Subject::factory()->create();
        LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'PE5PF-IB-1']);
        $draft = LearningModule::factory()->for($subject)->unpublished()->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'PE5PF-IB-1']);
        LearningActivity::factory()->for($draft, 'module')->count(2)->create();

        $coverage = app(ContentAlignmentReport::class)->competencies()->sole();

        expect($coverage->publishedActivitiesCount)->toBe(0)
            ->and($coverage->status)->toBe(CoverageStatus::Partial);
    });

    it('limits the competencies to a subject and grade', function () {
        $subject = Subject::factory()->create();
        LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'A-1']);
        LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Six, 'competency_code' => 'A-2']);
        LessonPlan::factory()->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'A-3']);

        $codes = app(ContentAlignmentReport::class)->competencies($subject->id, GradeLevel::Five)->pluck('code')->all();

        expect($codes)->toBe(['A-1']);
    });

    it('takes the earliest quarter of the plans and a learning competency for the module prefill', function () {
        $subject = Subject::factory()->create();
        $secondQuarter = AcademicTerm::factory()->create(['sequence' => 2]);
        $thirdQuarter = AcademicTerm::factory()->create(['sequence' => 3]);
        LessonPlan::factory()->for($subject)->for($thirdQuarter, 'academicTerm')->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IIA-1', 'learning_competency' => 'Explains the food pyramid.']);
        LessonPlan::factory()->for($subject)->for($secondQuarter, 'academicTerm')->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IIA-1', 'learning_competency' => null]);

        $coverage = app(ContentAlignmentReport::class)->competencies()->sole();

        expect($coverage->quarter)->toBe(2)
            ->and($coverage->learningCompetency)->toBe('Explains the food pyramid.')
            ->and($coverage->modulePrefill())->toBe([
                'subject_id' => $subject->id,
                'grade_level' => 5,
                'quarter' => 2,
                'competency_code' => 'H5N-IIA-1',
                'learning_competency' => 'Explains the food pyramid.',
            ]);
    });

    it('returns nothing when no lesson plan has a code', function () {
        LessonPlan::factory()->create(['competency_code' => null]);

        expect(app(ContentAlignmentReport::class)->competencies())->toBeEmpty();
    });
});

describe('unmappedPlansCount', function () {
    it('counts the lesson plans without a competency code', function () {
        $subject = Subject::factory()->create();
        LessonPlan::factory()->for($subject)->count(2)->create(['grade_level' => GradeLevel::Five, 'competency_code' => null]);
        LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => '  ']);
        LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Six, 'competency_code' => null]);
        LessonPlan::factory()->create(['competency_code' => null]);
        LessonPlan::factory()->for($subject)->create(['competency_code' => 'H5N-IA-1']);

        $report = app(ContentAlignmentReport::class);

        expect($report->unmappedPlansCount())->toBe(5)
            ->and($report->unmappedPlansCount($subject->id))->toBe(4)
            ->and($report->unmappedPlansCount($subject->id, GradeLevel::Five))->toBe(3);
    });
});

describe('forLessonPlan', function () {
    it('returns nothing for a plan without a code', function () {
        expect(app(ContentAlignmentReport::class)->forLessonPlan(LessonPlan::factory()->create(['competency_code' => null])))->toBeNull();
    });

    it('rates the plan\'s competency with its own aligned quizzes', function () {
        $subject = Subject::factory()->create();
        $module = LearningModule::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IA-1']);
        $lessonPlan = LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'h5n-ia-1']);
        LessonPlan::factory()->for($subject)->create(['grade_level' => GradeLevel::Five, 'competency_code' => 'H5N-IA-1'])
            ->quizzes()->attach(Quiz::factory()->for($subject)->create());

        $coverage = app(ContentAlignmentReport::class)->forLessonPlan($lessonPlan);

        expect($coverage->modules->modelKeys())->toBe([$module->id])
            ->and($coverage->quizzesCount)->toBe(0)
            ->and($coverage->status)->toBe(CoverageStatus::Partial);

        LearningActivity::factory()->for($module, 'module')->create();

        expect(app(ContentAlignmentReport::class)->forLessonPlan($lessonPlan)->status)->toBe(CoverageStatus::Covered);
    });
});

describe('CoverageStatus', function () {
    it('derives the status from the matching content', function (int $modules, int $activities, int $quizzes, CoverageStatus $expected) {
        expect(CoverageStatus::fromCounts($modules, $activities, $quizzes))->toBe($expected);
    })->with([
        'nothing' => [0, 0, 0, CoverageStatus::NotCovered],
        'empty module' => [1, 0, 0, CoverageStatus::Partial],
        'quiz without module' => [0, 0, 2, CoverageStatus::Partial],
        'module with activities' => [1, 3, 0, CoverageStatus::Covered],
        'module with a quiz' => [2, 0, 1, CoverageStatus::Covered],
    ]);

    it('labels every status with a status color and a design-system icon', function (CoverageStatus $status, string $label, string $color) {
        expect($status->label())->toBe($label)
            ->and($status->color())->toBe($color)
            ->and(Icon::has($status->icon()))->toBeTrue()
            ->and($status->description())->not->toBeEmpty();
    })->with([
        [CoverageStatus::Covered, 'Covered', 'leaf'],
        [CoverageStatus::Partial, 'Partial', 'sun'],
        [CoverageStatus::NotCovered, 'Not covered', 'berry'],
    ]);
});
