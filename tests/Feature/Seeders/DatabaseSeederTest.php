<?php

use App\Enums\FeedbackType;
use App\Enums\ForumContentStatus;
use App\Enums\GradeLevel;
use App\Enums\LessonPlanStatus;
use App\Enums\PerformanceBand;
use App\Enums\ProgressStatus;
use App\Enums\QuestionType;
use App\Enums\QuizStatus;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\ActivityProgress;
use App\Models\Classroom;
use App\Models\ForumCategory;
use App\Models\ForumReply;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\LearnerFeedback;
use App\Models\LearningActivity;
use App\Models\LearningModule;
use App\Models\LessonPlan;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Reward;
use App\Models\SchoolYear;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Settings;
use Database\Seeders\Support\Catalog\ContentCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(now()->setDate(2026, 11, 13)->setTime(10, 0));
});

/**
 * Get each learner's mean of their best percentage per quiz in this school year's classrooms.
 *
 * @return Collection<int, float>
 */
function demoLearnerAverages(): Collection
{
    return QuizAttempt::query()
        ->whereIn('classroom_id', Classroom::query()->active()->select('id'))
        ->toBase()
        ->get(['student_id', 'quiz_id', 'percentage'])
        ->groupBy(fn (object $attempt): string => $attempt->student_id.'|'.$attempt->quiz_id)
        ->map(fn (Collection $attempts): array => ['student_id' => (int) $attempts->first()->student_id, 'best' => (float) $attempts->max('percentage')])
        ->groupBy('student_id')
        ->map(fn (Collection $best): float => $best->avg('best'));
}

it('creates the documented demo accounts, all sharing the password "password"', function () {
    $this->seed();

    $accounts = [
        'admin@nutrimind.test' => UserRole::Admin,
        'teacher@nutrimind.test' => UserRole::Teacher,
        'parent@nutrimind.test' => UserRole::Parent,
    ];

    foreach ($accounts as $email => $role) {
        $user = User::query()->where('email', $email)->sole();

        expect([$user->role, $user->is_active])->toBe([$role, true]);
        expect(Hash::check('password', $user->password))->toBeTrue();
    }

    expect(User::query()->distinct()->count('password'))->toBe(1);
});

it('seeds the people of a Philippine public elementary school', function () {
    $this->seed();

    expect(User::query()->ofRole(UserRole::Admin)->count())->toBe(1);
    expect(User::query()->ofRole(UserRole::Teacher)->count())->toBe(7);
    expect(User::query()->ofRole(UserRole::Student)->count())->toBe(140);
    expect(User::query()->ofRole(UserRole::Parent)->count())->toBe(91);
    expect(User::query()->where('is_active', false)->orderBy('id')->get()->map(fn (User $user): string => $user->role->value)->all())
        ->toBe(['teacher', 'parent']);

    $learners = User::query()->ofRole(UserRole::Student)->with('studentProfile')->get();

    expect($learners->pluck('username')->unique())->toHaveCount(140);
    expect($learners->pluck('studentProfile.lrn')->unique())->toHaveCount(140);

    foreach ($learners as $learner) {
        expect($learner->username)->toMatch('/^[a-z0-9_]{3,30}$/');
        expect($learner->studentProfile->lrn)->toMatch('/^\d{12}$/');
        expect([$learner->email, $learner->phone, $learner->is_active])->toBe([null, null, true]);
        expect($learner->studentProfile->age)->toBe($learner->studentProfile->grade_level === GradeLevel::Five ? 10 : 11);
    }
});

it('stores the school profile, academic defaults and announcement as settings', function () {
    $this->seed();

    $settings = app(Settings::class);

    expect($settings->get('school_name'))->toBe('Mabini Elementary School');
    expect($settings->get('school_id'))->toBe('136742');
    expect($settings->get('school_head'))->toBe('Dr. Erlinda M. Castillo');
    expect($settings->get('announcement'))->toStartWith('Healthy Baon Week');
    expect([$settings->get('default_passing_score'), $settings->get('points_per_level'), $settings->get('forum_enabled')])->toBe([75, 500, true]);
    $this->assertDatabaseHas('settings', ['key' => 'school_email']);
    $this->assertDatabaseMissing('settings', ['key' => 'role_permissions']);
});

it('runs the 2026–2027 school year in its 2nd Quarter and keeps last year for history', function () {
    $this->seed();

    $previous = SchoolYear::query()->where('name', '2025–2026')->sole();

    expect(SchoolYear::current()->name)->toBe('2026–2027');
    expect(SchoolYear::query()->active()->count())->toBe(1);
    expect(AcademicTerm::current()->name)->toBe('2nd Quarter');
    expect(AcademicTerm::query()->active()->count())->toBe(1);
    expect($previous->is_active)->toBeFalse();
    expect($previous->terms()->count())->toBe(4);

    foreach (AcademicTerm::query()->with('schoolYear')->get() as $term) {
        expect($term->starts_on->gte($term->schoolYear->starts_on))->toBeTrue();
        expect($term->ends_on->lte($term->schoolYear->ends_on))->toBeTrue();
    }
});

it('assigns one or two subjects to every working teacher except the newly hired one', function () {
    $this->seed();

    $admin = User::query()->ofRole(UserRole::Admin)->sole();
    $teachers = User::query()->ofRole(UserRole::Teacher)->active()->withCount('assignedSubjects')->get()->keyBy('email');

    expect($teachers['teacher@nutrimind.test']->assignedSubjects()->pluck('code')->sort()->values()->all())->toBe(['HEALTH', 'SCIENCE']);
    expect($teachers->where('assigned_subjects_count', 0)->keys()->all())->toBe(['dennis.aquino@nutrimind.test']);
    expect($teachers->max('assigned_subjects_count'))->toBe(2);
    expect(DB::table('subject_teacher')->where('assigned_by', '!=', $admin->id)->count())->toBe(0);
});

it('enrolls every learner in every classroom of exactly one section of their grade', function () {
    $this->seed();

    $activeYear = SchoolYear::current();
    $classrooms = Classroom::query()->active()->whereBelongsTo($activeYear)->with('teacher')->get();
    $learners = User::query()->ofRole(UserRole::Student)->with(['studentProfile', 'enrolledClassrooms'])->get();

    expect($classrooms)->toHaveCount(17);

    foreach ($classrooms as $classroom) {
        expect(Classroom::query()->accessibleBy($classroom->teacher)->whereKey($classroom)->exists())->toBeTrue();
        expect($classroom->class_code)->toMatch('/^['.Classroom::CLASS_CODE_ALPHABET.']{6}$/');
    }

    foreach ($learners as $learner) {
        $thisYear = $learner->enrolledClassrooms->where('school_year_id', $activeYear->id);
        $section = $thisYear->pluck('section')->unique();

        expect($section)->toHaveCount(1);
        expect($thisYear->pluck('id')->sort()->values()->all())
            ->toBe($classrooms->where('section', $section->first())->pluck('id')->sort()->values()->all());
        expect($thisYear->every(fn (Classroom $classroom): bool => $classroom->grade_level === $learner->studentProfile->grade_level))->toBeTrue();
    }

    $archived = Classroom::query()->archived()->with(['schoolYear', 'students'])->sole();
    $narra = Classroom::query()->active()->where('section', 'Narra')->with('students')->first();

    expect([$archived->name, $archived->schoolYear->name, $archived->teacher_id])
        ->toBe(['Health 5 – Sampaguita', '2025–2026', User::query()->where('email', 'teacher@nutrimind.test')->value('id')]);
    expect($archived->students->modelKeys())->toEqualCanonicalizing($narra->students->modelKeys());
});

it('links the demo parent to two children in different grades and most learners to a guardian', function () {
    $this->seed();

    $demoParent = User::query()->where('email', 'parent@nutrimind.test')->with('linkedStudents.studentProfile')->sole();
    $parents = User::query()->ofRole(UserRole::Parent)->withCount('linkedStudents')->get();
    $learnersWithGuardian = User::query()->ofRole(UserRole::Student)->has('guardians')->count();

    expect($demoParent->linkedStudents->map(fn (User $child): int => $child->studentProfile->grade_level->value)->sort()->values()->all())->toBe([5, 6]);
    expect($demoParent->linkedStudents->every(fn (User $child): bool => $child->pivot->relationship->value === 'mother'))->toBeTrue();
    expect($parents->every(fn (User $parent): bool => in_array($parent->linked_students_count, [1, 2], true)))->toBeTrue();
    expect($learnersWithGuardian)->toBeGreaterThanOrEqual(105)->toBeLessThan(140);
    expect(DB::table('guardian_student')->distinct()->pluck('relationship')->count())->toBeGreaterThanOrEqual(5);
});

it('publishes the game content: modules with activities, missions and rewards', function () {
    $this->seed();

    $modules = LearningModule::query()->withCount('activities')->get();

    expect($modules)->toHaveCount(24);
    expect($modules->where('is_published', false)->pluck('competency_code')->all())->toBe(['H6N-IIIa-1']);
    expect($modules->every(fn (LearningModule $module): bool => $module->activities_count >= 3 && $module->activities_count <= 6))->toBeTrue();
    expect(LearningActivity::query()->count())->toBe(99);
    expect(Mission::query()->count())->toBe(25);
    expect(Reward::query()->count())->toBe(14);

    foreach (LearningActivity::query()->get() as $activity) {
        expect($activity->points)->toBe(ContentCatalog::ACTIVITY_POINTS[$activity->type->value]);
    }

    foreach (Mission::query()->whereNotNull('learning_module_id')->with('module')->get() as $mission) {
        expect([$mission->subject_id, $mission->grade_level])->toBe([$mission->module->subject_id, $mission->module->grade_level]);
    }
});

it('fills the question bank with answerable items whose usage matches their quiz copies', function () {
    $this->seed();

    $items = QuestionBankItem::query()->with(['teacher.assignedSubjects', 'quizQuestions'])->get();

    expect($items)->toHaveCount(80);
    expect($items->whereNotNull('reviewed_at'))->toHaveCount(73);
    expect($items->where('is_shared', true))->toHaveCount(46);

    foreach ($items as $item) {
        expect($item->teacher->teachesSubject($item->subject_id))->toBeTrue();
        expect($item->times_used)->toBe($item->quizQuestions->count());
        expect($item->acceptedAnswers())->not->toBeEmpty();
        expect($item->choices)->toBe(match ($item->type) {
            QuestionType::TrueFalse => ['True', 'False'],
            QuestionType::Identification => null,
            QuestionType::MultipleChoice => $item->choices,
        });
    }
});

it('assigns three to five quizzes of eight to twelve questions to every active classroom', function () {
    $this->seed();

    $now = now();
    $classrooms = Classroom::query()->active()->with('quizzes')->get();
    $quizzes = Quiz::query()->with(['questions.bankItem', 'academicTerm'])->get();

    foreach ($classrooms as $classroom) {
        expect($classroom->quizzes->count())->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(5);

        foreach ($classroom->quizzes as $quiz) {
            expect($quiz->pivot->due_at->between($now->copy()->subWeeks(8), $now->copy()->addWeeks(2)))->toBeTrue();
            expect($quiz->pivot->available_from->lt($quiz->pivot->due_at))->toBeTrue();
            expect([$quiz->subject_id, $quiz->grade_level, $quiz->teacher_id])->toBe([$classroom->subject_id, $classroom->grade_level, $classroom->teacher_id]);
        }
    }

    foreach ($quizzes as $quiz) {
        expect($quiz->questions->count())->toBeGreaterThanOrEqual(8)->toBeLessThanOrEqual(12);

        if (! $quiz->isDraft()) {
            expect($quiz->reviewed_at->lte($quiz->published_at))->toBeTrue();
            expect($quiz->questions->every(fn ($question): bool => $question->updated_at->lte($quiz->reviewed_at)))->toBeTrue();
        }

        foreach ($quiz->questions->whereNotNull('question_bank_item_id') as $question) {
            expect([$question->prompt, $question->choices, $question->correct_answer])
                ->toBe([$question->bankItem->prompt, $question->bankItem->choices, $question->bankItem->correct_answer]);
        }
    }

    expect($quizzes->countBy(fn (Quiz $quiz): string => $quiz->status->value)->sortKeys()->all())->toBe(['closed' => 16, 'draft' => 5, 'published' => 30]);
    expect($quizzes->where('status', '!=', QuizStatus::Draft)->whereNull('academic_term_id'))->toBeEmpty();
    expect(DB::table('quiz_questions')->whereNotNull('question_bank_item_id')->count() / DB::table('quiz_questions')->count())->toBeGreaterThan(0.8);
});

it('records quiz attempts whose answers add up to their scores', function () {
    $this->seed();

    $questions = QuizQuestion::query()->get()->keyBy('id');
    $questionIdsByQuiz = $questions->groupBy('quiz_id')->map(fn (Collection $quizQuestions): array => collect($quizQuestions->modelKeys())->sort()->values()->all());
    $passingScores = Quiz::query()->pluck('passing_score', 'id');

    expect(DB::table('quiz_attempts')->count())->toBeGreaterThan(1000);

    DB::table('quiz_attempts')->orderBy('id')->chunk(200, function (Collection $attempts) use ($questions, $questionIdsByQuiz, $passingScores): void {
        $answers = DB::table('quiz_attempt_answers')->whereIn('quiz_attempt_id', $attempts->pluck('id'))->get()->groupBy('quiz_attempt_id');

        foreach ($attempts as $attempt) {
            $attemptAnswers = $answers[$attempt->id];
            $score = 0;

            expect($attemptAnswers->pluck('quiz_question_id')->sort()->values()->all())->toBe($questionIdsByQuiz[$attempt->quiz_id]);

            foreach ($attemptAnswers as $answer) {
                $question = $questions[$answer->quiz_question_id];
                $isCorrect = $question->isCorrectAnswer($answer->answer);

                expect((bool) $answer->is_correct)->toBe($isCorrect);
                expect((int) $answer->points_awarded)->toBe($isCorrect ? $question->points : 0);

                $score += (int) $answer->points_awarded;
            }

            $maxScore = $questions->only($questionIdsByQuiz[$attempt->quiz_id])->sum('points');
            $percentage = round($score / $maxScore * 100, 2);

            expect([(int) $attempt->score, (int) $attempt->max_score, (float) $attempt->percentage])->toBe([$score, $maxScore, $percentage]);
            expect((bool) $attempt->passed)->toBe($percentage >= $passingScores[$attempt->quiz_id]);
        }
    });
});

it('keeps attempts inside their assignment window and within the rules of the quiz', function () {
    $this->seed();

    $assignments = DB::table('classroom_quiz')->get()->keyBy(fn (object $row): string => $row->classroom_id.'|'.$row->quiz_id);
    $enrollments = DB::table('classroom_student')->get()->keyBy(fn (object $row): string => $row->classroom_id.'|'.$row->student_id);
    $quizzes = Quiz::query()->get(['id', 'status', 'max_attempts', 'time_limit_minutes'])->keyBy('id');
    $attempts = DB::table('quiz_attempts')->orderBy('attempt_number')->orderBy('id')->get();
    $now = now()->toDateTimeString();

    foreach ($attempts as $attempt) {
        $assignment = $assignments[$attempt->classroom_id.'|'.$attempt->quiz_id];
        $enrollment = $enrollments[$attempt->classroom_id.'|'.$attempt->student_id];
        $quiz = $quizzes[$attempt->quiz_id];

        expect($attempt->started_at)->toBeGreaterThanOrEqual($assignment->available_from)->toBeGreaterThanOrEqual($enrollment->enrolled_at);
        expect($attempt->submitted_at)->toBeLessThanOrEqual($assignment->due_at)->toBeLessThanOrEqual($now);
        expect(strtotime($attempt->submitted_at) - strtotime($attempt->started_at))->toBe((int) $attempt->duration_seconds);
        expect((int) $attempt->duration_seconds)->toBeLessThanOrEqual($quiz->time_limit_minutes * 60);
        expect($attempt->is_offline ? $attempt->synced_at > $attempt->submitted_at : $attempt->synced_at === $attempt->submitted_at)->toBeTrue();
        expect($quiz->max_attempts === null || $attempt->attempt_number <= $quiz->max_attempts)->toBeTrue();
        expect($quiz->status)->not->toBe(QuizStatus::Draft);
    }

    foreach ($attempts->groupBy(fn (object $attempt): string => $attempt->quiz_id.'|'.$attempt->student_id) as $series) {
        $submittedAt = $series->pluck('submitted_at')->all();

        expect($series->pluck('attempt_number')->map(fn (mixed $number): int => (int) $number)->all())->toBe(range(1, $series->count()));
        expect($submittedAt)->toBe(collect($submittedAt)->sort()->values()->all());
    }
});

it('has about 85% of the assigned learners take each past-due quiz, a tenth of them offline, improving over the weeks', function () {
    $this->seed();

    $assigned = DB::table('classroom_quiz')
        ->join('classroom_student', 'classroom_student.classroom_id', '=', 'classroom_quiz.classroom_id')
        ->join('quizzes', 'quizzes.id', '=', 'classroom_quiz.quiz_id')
        ->where('quizzes.status', '!=', QuizStatus::Draft->value)
        ->where('classroom_quiz.due_at', '<', now())
        ->get(['classroom_quiz.quiz_id', 'classroom_student.student_id']);
    $attempted = QuizAttempt::query()->toBase()->get(['quiz_id', 'student_id'])->map(fn (object $row): string => $row->quiz_id.'|'.$row->student_id)->flip();
    $participation = $assigned->filter(fn (object $row): bool => isset($attempted[$row->quiz_id.'|'.$row->student_id]))->count() / $assigned->count();

    $attempts = DB::table('quiz_attempts')->get(['percentage', 'submitted_at', 'is_offline', 'attempt_number']);
    $average = fn (string $from, string $until): float => $attempts
        ->filter(fn (object $attempt): bool => $attempt->submitted_at >= $from && $attempt->submitted_at < $until)
        ->avg(fn (object $attempt): float => (float) $attempt->percentage);

    expect($participation)->toBeGreaterThan(0.8)->toBeLessThan(0.9);
    expect($attempts->filter(fn (object $attempt): bool => (bool) $attempt->is_offline)->count() / $attempts->count())->toBeGreaterThan(0.07)->toBeLessThan(0.14);
    expect($attempts->filter(fn (object $attempt): bool => $attempt->attempt_number > 1)->count() / $attempts->count())->toBeGreaterThan(0.1)->toBeLessThan(0.3);
    expect($average(now()->subWeeks(2)->toDateTimeString(), now()->toDateTimeString()) - $average(now()->subWeeks(8)->toDateTimeString(), now()->subWeeks(5)->toDateTimeString()))
        ->toBeGreaterThan(2.0)->toBeLessThan(12.0);
});

it('spreads the learners across every DepEd performance band', function () {
    $this->seed();

    $bands = demoLearnerAverages()->map(fn (float $average): string => PerformanceBand::fromScore($average)->value)->countBy();

    foreach ([PerformanceBand::Outstanding, PerformanceBand::VerySatisfactory, PerformanceBand::Satisfactory, PerformanceBand::FairlySatisfactory, PerformanceBand::DidNotMeetExpectations] as $band) {
        expect($bands[$band->value] ?? 0)->toBeGreaterThanOrEqual(10);
    }

    expect(demoLearnerAverages()->avg())->toBeGreaterThan(78.0)->toBeLessThan(88.0);
});

it('keeps learners\' points, levels and sync times consistent with their game progress', function () {
    $this->seed();

    $profiles = StudentProfile::query()->get()->keyBy('user_id');
    $activityPoints = ActivityProgress::query()->toBase()->selectRaw('student_id, SUM(points_earned) AS points, MAX(synced_at) AS synced')->groupBy('student_id')->get()->keyBy('student_id');
    $missionPoints = MissionProgress::query()->toBase()->selectRaw('student_id, SUM(points_earned) AS points, MAX(synced_at) AS synced')->groupBy('student_id')->get()->keyBy('student_id');

    foreach ($profiles as $studentId => $profile) {
        $points = (int) ($activityPoints[$studentId]->points ?? 0) + (int) ($missionPoints[$studentId]->points ?? 0);

        expect($profile->total_points)->toBe($points);
        expect($profile->level)->toBe(min(50, intdiv($points, 500) + 1));

        if ($profile->last_synced_at !== null) {
            expect($profile->last_synced_at->gte($profile->last_active_at))->toBeTrue();
            expect($profile->last_synced_at->lte(now()))->toBeTrue();
            expect($profile->last_synced_at->toDateTimeString() >= ($activityPoints[$studentId]->synced ?? ''))->toBeTrue();
            expect($profile->last_synced_at->toDateTimeString() >= ($missionPoints[$studentId]->synced ?? ''))->toBeTrue();
        }
    }

    $neverSynced = $profiles->whereNull('last_synced_at');

    expect($profiles->filter(fn (StudentProfile $profile): bool => $profile->last_synced_at?->lt(now()->subDays(7)) ?? false))->toHaveCount(7);
    expect($neverSynced)->toHaveCount(2);
    expect(QuizAttempt::query()->whereIn('student_id', $neverSynced->keys())->exists())->toBeFalse();
    expect(ActivityProgress::query()->where('status', ProgressStatus::Completed)->where(fn ($query) => $query->whereNull('completed_at')->orWhereNull('score'))->exists())->toBeFalse();
    expect(ActivityProgress::query()->where('status', ProgressStatus::InProgress)->where(fn ($query) => $query->whereNotNull('completed_at')->orWhere('points_earned', '>', 0))->exists())->toBeFalse();
    expect(MissionProgress::query()->where('status', ProgressStatus::Completed)->where('progress_percent', '<', 100)->exists())->toBeFalse();
    expect(ActivityProgress::query()->whereHas('activity', fn ($query) => $query->where('is_published', false))->exists())->toBeFalse();
    expect(StudentProfile::query()->max('level'))->toBeGreaterThanOrEqual(6);
});

it('records game progress only in the learner\'s own subjects and grade', function () {
    $this->seed();

    $subjectsByLearner = DB::table('classroom_student')
        ->join('classrooms', 'classrooms.id', '=', 'classroom_student.classroom_id')
        ->whereNull('classrooms.archived_at')
        ->get(['classroom_student.student_id', 'classrooms.subject_id'])
        ->groupBy('student_id')
        ->map(fn (Collection $rows): array => $rows->pluck('subject_id')->all());
    $grades = DB::table('student_profiles')->pluck('grade_level', 'user_id');
    $activityProgress = DB::table('activity_progress')
        ->join('learning_activities', 'learning_activities.id', '=', 'activity_progress.learning_activity_id')
        ->join('learning_modules', 'learning_modules.id', '=', 'learning_activities.learning_module_id')
        ->get(['activity_progress.student_id', 'activity_progress.status', 'activity_progress.points_earned', 'learning_activities.points', 'learning_modules.subject_id', 'learning_modules.grade_level']);
    $missionProgress = DB::table('mission_progress')
        ->join('missions', 'missions.id', '=', 'mission_progress.mission_id')
        ->get(['mission_progress.student_id', 'mission_progress.status', 'mission_progress.points_earned', 'mission_progress.started_at', 'missions.subject_id', 'missions.grade_level', 'missions.points_reward', 'missions.starts_on']);

    foreach ($activityProgress as $progress) {
        expect($subjectsByLearner[$progress->student_id])->toContain($progress->subject_id);
        expect((int) $progress->grade_level)->toBe((int) $grades[$progress->student_id]);
        expect((int) $progress->points_earned)->toBe($progress->status === ProgressStatus::Completed->value ? (int) $progress->points : 0);
    }

    foreach ($missionProgress as $progress) {
        expect($subjectsByLearner[$progress->student_id])->toContain($progress->subject_id);
        expect($progress->grade_level === null || (int) $progress->grade_level === (int) $grades[$progress->student_id])->toBeTrue();
        expect((int) $progress->points_earned)->toBe($progress->status === ProgressStatus::Completed->value ? (int) $progress->points_reward : 0);
        expect($progress->starts_on === null || $progress->started_at >= $progress->starts_on)->toBeTrue();
    }
});

it('awards rewards only to learners who met their criteria', function () {
    $this->seed();

    $awards = DB::table('reward_student')
        ->join('rewards', 'rewards.id', '=', 'reward_student.reward_id')
        ->join('student_profiles', 'student_profiles.user_id', '=', 'reward_student.student_id')
        ->get(['rewards.name', 'rewards.points_required', 'rewards.created_at AS reward_created_at', 'reward_student.student_id', 'reward_student.awarded_at', 'student_profiles.total_points'])
        ->groupBy('name');
    $now = now()->toDateTimeString();

    foreach ($awards->flatten(1) as $award) {
        expect($award->awarded_at)->toBeGreaterThanOrEqual($award->reward_created_at)->toBeLessThanOrEqual($now);

        if ($award->points_required !== null) {
            expect((int) $award->total_points)->toBeGreaterThanOrEqual((int) $award->points_required);
        }
    }

    $labelMission = Mission::query()->where('title', 'Crack the Label Code')->sole();
    $labelDetectives = $awards['Label Detective']->pluck('student_id')->sort()->values()->all();

    expect($labelDetectives)->not->toBeEmpty();
    expect(MissionProgress::query()->whereBelongsTo($labelMission)->completed()->pluck('student_id')->sort()->values()->all())->toBe($labelDetectives);
    expect(Reward::query()->where('name', 'Nutrition Month Star 2026')->value('is_active'))->toBeFalse();
    expect($awards['Nutrition Month Star 2026']->every(fn (object $award): bool => str_starts_with($award->awarded_at, '2026-07-')))->toBeTrue();
    expect($awards->keys()->sort()->values()->all())->toBe(Reward::query()->orderBy('name')->pluck('name')->sort()->values()->all());
});

it('writes weekly lesson plans with every content coverage status represented', function () {
    $this->seed();

    $plans = LessonPlan::query()->with(['activities.module', 'missions', 'quizzes', 'academicTerm'])->get();
    $moduleCodes = LearningModule::query()->withCount(['activities' => fn ($query) => $query->published()])->get()->keyBy('competency_code');
    $today = now()->toDateString();

    expect($plans)->toHaveCount(40);

    foreach ($plans as $plan) {
        $expectedStatus = match (true) {
            $plan->ends_on->toDateString() < $today => LessonPlanStatus::Completed,
            $plan->starts_on->toDateString() <= $today => LessonPlanStatus::Ongoing,
            default => LessonPlanStatus::Planned,
        };

        expect($plan->status)->toBe($expectedStatus);
        expect($plan->starts_on->between($plan->academicTerm->starts_on, $plan->academicTerm->ends_on))->toBeTrue();

        foreach ($plan->activities as $activity) {
            expect([$activity->module->subject_id, $activity->module->grade_level])->toBe([$plan->subject_id, $plan->grade_level]);
        }

        foreach ($plan->quizzes as $quiz) {
            expect([$quiz->teacher_id, $quiz->subject_id, $quiz->grade_level])->toBe([$plan->teacher_id, $plan->subject_id, $plan->grade_level]);
        }
    }

    $covered = $plans->filter(fn (LessonPlan $plan): bool => $plan->activities->isNotEmpty() && ($moduleCodes[$plan->competency_code]->activities_count ?? 0) > 0);
    $partial = $plans->filter(fn (LessonPlan $plan): bool => isset($moduleCodes[$plan->competency_code]) && $moduleCodes[$plan->competency_code]->activities_count === 0);
    $notCovered = $plans->filter(fn (LessonPlan $plan): bool => $plan->competency_code !== null && ! isset($moduleCodes[$plan->competency_code]));

    expect($covered->count())->toBe(30);
    expect($partial->pluck('competency_code')->unique()->all())->toBe(['H6N-IIIa-1']);
    expect($notCovered->pluck('competency_code')->sort()->values()->all())->toBe(['H6N-IIc-4', 'S5LT-IIc-3']);
    expect($plans->whereNull('competency_code'))->toHaveCount(2);
    expect($plans->filter(fn (LessonPlan $plan): bool => $plan->quizzes->isNotEmpty())->count())->toBeGreaterThanOrEqual(25);
});

it('writes 150 feedback messages from teachers to learners in their classrooms', function () {
    $this->seed();

    $feedback = LearnerFeedback::query()->with(['classroom', 'quizAttempt.quiz', 'student.guardians'])->get();
    $enrollments = DB::table('classroom_student')->get()->map(fn (object $row): string => $row->classroom_id.'|'.$row->student_id)->flip();
    $demoChildren = User::query()->where('email', 'parent@nutrimind.test')->sole()->linkedStudents()->pluck('users.id');

    expect($feedback)->toHaveCount(150);
    expect($feedback->pluck('type')->unique()->count())->toBe(count(FeedbackType::cases()));
    expect($feedback->where('is_visible_to_guardians', true)->count() / 150)->toBeGreaterThan(0.85);
    expect($feedback->whereNotNull('read_at')->count())->toBeGreaterThan(30);

    foreach ($feedback as $message) {
        expect($message->classroom->teacher_id)->toBe($message->teacher_id);
        expect(isset($enrollments[$message->classroom_id.'|'.$message->student_id]))->toBeTrue();
        expect($message->created_at->lte(now()))->toBeTrue();

        if ($message->quizAttempt !== null) {
            expect([$message->quizAttempt->student_id, $message->quizAttempt->quiz->teacher_id])->toBe([$message->student_id, $message->teacher_id]);
            expect($message->created_at->gt($message->quizAttempt->synced_at))->toBeTrue();
        }

        if ($message->read_at !== null) {
            expect($message->is_visible_to_guardians)->toBeTrue();
            expect($message->read_at->gt($message->created_at))->toBeTrue();
            expect($message->student->guardians->contains('is_active', true))->toBeTrue();
        }
    }

    foreach ($demoChildren as $childId) {
        expect($feedback->where('student_id', $childId)->where('is_visible_to_guardians', true)->count())->toBeGreaterThanOrEqual(3);
    }

    expect($feedback->whereIn('student_id', $demoChildren)->where('is_visible_to_guardians', true)->whereNull('read_at'))->not->toBeEmpty();
});

it('seeds a moderated parent forum', function () {
    $this->seed();

    $threads = ForumThread::query()->with(['replies.author', 'author', 'category'])->get();
    $reports = ForumReport::query()->with(['thread', 'reply'])->get();

    expect(ForumCategory::query()->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['School Announcements', 'General Discussion', 'Healthy Meals at Home', 'Homework & Learning Help', 'Healthy Baon Ideas']);
    expect($threads)->toHaveCount(27);
    expect(ForumReply::query()->count())->toBe(122);
    expect([$threads->where('is_pinned', true)->count(), $threads->where('is_locked', true)->count()])->toBe([1, 1]);
    expect($threads->countBy(fn (ForumThread $thread): string => $thread->status->value)->sortKeys()->all())->toBe(['hidden' => 1, 'pending' => 2, 'published' => 24]);
    expect(ForumReply::query()->where('status', ForumContentStatus::Hidden)->count())->toBe(1);

    foreach ($threads as $thread) {
        $published = $thread->replies->where('status', ForumContentStatus::Published);

        expect($thread->last_activity_at->toDateTimeString())->toBe(($published->max('created_at') ?? $thread->created_at)->toDateTimeString());
        expect($thread->author->created_at->lt($thread->created_at))->toBeTrue();

        foreach ($thread->replies as $reply) {
            expect($reply->created_at->gt($thread->created_at))->toBeTrue();
            expect($reply->author->created_at->lt($reply->created_at))->toBeTrue();
            expect($reply->created_at->lte(now()))->toBeTrue();
        }
    }

    $unresolved = $reports->whereNull('resolved_at');

    expect($unresolved)->toHaveCount(3);
    expect($unresolved->every(fn (ForumReport $report): bool => $report->reply?->status === ForumContentStatus::Published))->toBeTrue();

    foreach ($reports as $report) {
        $target = $report->reply ?? $report->thread;

        expect($report->reporter_id)->not->toBe($target->author_id);
        expect($report->reply === null || $report->reply->forum_thread_id === $report->forum_thread_id)->toBeTrue();
    }
});

it('produces the same school on every run and leaves the global random generator unseeded afterwards', function () {
    $digest = function (string $table, array $columns): string {
        $query = DB::table($table)->select($columns);
        $hash = hash_init('sha256');

        foreach ($columns as $column) {
            $query->orderBy($column);
        }

        foreach ($query->cursor() as $row) {
            hash_update($hash, json_encode($row));
        }

        return hash_final($hash);
    };

    $fingerprint = fn (): array => [
        'users' => $digest('users', ['email', 'username', 'name', 'role', 'created_at', 'last_login_at']),
        'profiles' => $digest('student_profiles', ['lrn', 'birthdate', 'total_points', 'level', 'last_active_at', 'last_synced_at']),
        'attempts' => $digest('quiz_attempts', ['submitted_at', 'score', 'max_score', 'attempt_number', 'synced_at', 'is_offline']),
        'answers' => $digest('quiz_attempt_answers', ['answer', 'is_correct', 'points_awarded', 'created_at']),
        'progress' => $digest('activity_progress', ['completed_at', 'score', 'points_earned', 'time_spent_seconds', 'synced_at']),
        'feedback' => $digest('learner_feedback', ['created_at', 'message', 'type', 'read_at']),
        'replies' => $digest('forum_replies', ['created_at', 'body']),
    ];

    $this->seed();
    $first = $fingerprint();
    $afterFirstRun = mt_rand();

    DB::statement('PRAGMA defer_foreign_keys = ON');

    foreach (Schema::getTableListing(schemaQualified: false) as $table) {
        if ($table !== 'migrations') {
            DB::table($table)->delete();
        }
    }

    $this->seed();

    expect($fingerprint())->toBe($first);
    expect(DB::table('quiz_attempt_answers')->count())->toBeGreaterThan(10000);
    expect(mt_rand())->not->toBe($afterFirstRun);
});
