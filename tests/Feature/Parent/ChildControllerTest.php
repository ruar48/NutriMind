<?php

use App\Enums\FeedbackType;
use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Enums\Permission;
use App\Enums\ProgressStatus;
use App\Enums\RewardType;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\LearnerFeedback;
use App\Models\Mission;
use App\Models\MissionProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Reward;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use App\Services\RolePermissions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Create a learner with a known profile.
 *
 * @param  array<string, mixed>  $profile
 */
function childPageLearner(string $name, GradeLevel $grade = GradeLevel::Five, array $profile = [], array $attributes = []): User
{
    $child = User::factory()->studentWithoutProfile()->create(['name' => $name, ...$attributes]);
    StudentProfile::factory()->for($child, 'user')->create(['grade_level' => $grade, ...$profile]);

    return $child;
}

describe('index', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('parent.children.index'))->assertRedirectToRoute('login');
    });

    it('forbids accounts of the other portals', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('parent.children.index'))
            ->assertForbidden();
    })->with([
        'administrator' => UserRole::Admin,
        'teacher' => UserRole::Teacher,
        'learner' => UserRole::Student,
    ]);

    it('forbids parents without the view child progress permission', function () {
        $parent = User::factory()->parent()->guardianOf(childPageLearner('Sofia Navarro'))->create();
        app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewChildProgress);

        $this->actingAs($parent)
            ->get(route('parent.children.index'))
            ->assertForbidden()
            ->assertSeeText('You do not have permission to view child progress.');
    });

    it('asks a parent without linked learners to contact the school', function () {
        $this->actingAs(User::factory()->parent()->create())
            ->get(route('parent.children.index'))
            ->assertOk()
            ->assertViewIs('parent.children.index')
            ->assertSeeText('Ask the school to link your child')
            ->assertDontSee('data-child-card', false)
            ->assertDontSee('data-auto-submit', false);
    });

    it('lists only the linked learners with relationship, classes, level and average', function () {
        $sofia = childPageLearner('Sofia Navarro', GradeLevel::Five, ['total_points' => 1240]);
        $miguel = childPageLearner('Miguel Navarro', GradeLevel::Six, ['total_points' => 2600]);
        $stranger = childPageLearner('Paolo Reyes');
        $parent = User::factory()->parent()
            ->guardianOf($sofia, GuardianRelationship::Mother)
            ->guardianOf($miguel, GuardianRelationship::Grandparent)
            ->create();
        User::factory()->parent()->guardianOf($stranger)->create();

        $health = Subject::factory()->create(['name' => 'Health']);
        $healthClass = Classroom::factory()->for($health)->create(['grade_level' => GradeLevel::Five, 'name' => 'Health 5 – Sampaguita', 'section' => 'Sampaguita']);
        $oldClass = Classroom::factory()->archived()->create(['grade_level' => GradeLevel::Five, 'name' => 'Health 5 – Last Year']);
        $sofia->enrolledClassrooms()->attach([$healthClass->id, $oldClass->id]);

        $quizA = Quiz::factory()->published()->create();
        $quizB = Quiz::factory()->published()->create();
        $quizC = Quiz::factory()->published()->create();
        QuizAttempt::factory()->for($quizA)->for($sofia, 'student')->create(['percentage' => 60, 'passed' => false]);
        QuizAttempt::factory()->for($quizA)->for($sofia, 'student')->create(['attempt_number' => 2, 'percentage' => 90, 'passed' => true]);
        QuizAttempt::factory()->for($quizB)->for($sofia, 'student')->create(['percentage' => 72.5, 'passed' => false]);
        QuizAttempt::factory()->for($quizC)->for($sofia, 'student')->create(['percentage' => 66.67, 'passed' => false]);
        QuizAttempt::factory()->inProgress()->for($quizC)->for($sofia, 'student')->create(['attempt_number' => 2]);
        QuizAttempt::factory()->for($quizA)->for($stranger, 'student')->create(['percentage' => 10]);
        MissionProgress::factory()->completed()->count(3)->for($sofia, 'student')->create();
        MissionProgress::factory()->inProgress()->for($sofia, 'student')->create();

        $response = $this->actingAs($parent)->get(route('parent.children.index'));

        $response->assertOk()
            ->assertSeeInOrder(['Miguel Navarro', 'Grandparent', 'Sofia Navarro', 'Mother', 'Health 5 – Sampaguita'])
            ->assertDontSeeText('Paolo Reyes')
            ->assertDontSeeText('Health 5 – Last Year')
            ->assertSee('href="'.route('parent.children.show', $sofia).'"', false)
            ->assertSee('href="'.route('parent.children.show', $miguel).'"', false);
        $response->assertViewHas('cards', function (array $cards) use ($sofia, $miguel): bool {
            [$first, $second] = $cards;

            return $first['student']->is($miguel)
                && $first['stats']['levelProgress']['level'] === 6
                && $first['stats']['averageScore'] === null
                && $first['stats']['quizzesTaken'] === 0
                && $second['student']->is($sofia)
                && $second['stats']['levelProgress'] === ['level' => 3, 'into' => 240, 'needed' => 500, 'percent' => 48.0]
                && $second['stats']['totalPoints'] === 1240
                && $second['stats']['averageScore'] === 76.4
                && $second['stats']['quizzesTaken'] === 3
                && $second['stats']['missionsCompleted'] === 3;
        });
    });

    it('leaves the filter bar out for parents who follow four children or fewer', function () {
        $parent = User::factory()->parent()->create();
        foreach (range(1, 4) as $number) {
            $parent->linkedStudents()->attach(childPageLearner('Learner '.$number));
        }

        $this->actingAs($parent)
            ->get(route('parent.children.index'))
            ->assertOk()
            ->assertViewHas('showFilters', false)
            ->assertDontSee('data-auto-submit', false);
    });

    it('shows the filter bar to parents who follow more than four children', function () {
        $parent = User::factory()->parent()->create();
        foreach (range(1, 5) as $number) {
            $parent->linkedStudents()->attach(childPageLearner('Learner '.$number));
        }

        $this->actingAs($parent)
            ->get(route('parent.children.index'))
            ->assertOk()
            ->assertViewHas('showFilters', true)
            ->assertSee('data-auto-submit', false);
    });

    it('filters the children by name, LRN or username and by grade', function (array $query, array $expected) {
        $parent = User::factory()->parent()->create();
        $parent->linkedStudents()->attach([
            childPageLearner('Sofia Navarro', GradeLevel::Five, ['lrn' => '136742210001'], ['username' => 'sofia_n'])->id,
            childPageLearner('Miguel Navarro', GradeLevel::Six, ['lrn' => '136742200002'], ['username' => 'miguel_n'])->id,
            childPageLearner('Lea Cruz', GradeLevel::Five, ['lrn' => '136742210003'], ['username' => 'lea_c'])->id,
        ]);

        $response = $this->actingAs($parent)->get(route('parent.children.index', $query));

        $response->assertOk()->assertViewHas('cards', fn (array $cards): bool => collect($cards)->map(fn (array $card): string => $card['student']->name)->all() === $expected);
    })->with([
        'name' => [['search' => 'navarro'], ['Miguel Navarro', 'Sofia Navarro']],
        'LRN' => [['search' => '200002'], ['Miguel Navarro']],
        'username' => [['search' => 'lea_c'], ['Lea Cruz']],
        'grade' => [['grade' => '5'], ['Lea Cruz', 'Sofia Navarro']],
        'name and grade' => [['search' => 'navarro', 'grade' => '6'], ['Miguel Navarro']],
        'unknown grade is ignored' => [['grade' => '7'], ['Lea Cruz', 'Miguel Navarro', 'Sofia Navarro']],
        'grade array is ignored' => [['grade' => ['5']], ['Lea Cruz', 'Miguel Navarro', 'Sofia Navarro']],
        'overlong search is ignored' => [['search' => str_repeat('a', 101)], ['Lea Cruz', 'Miguel Navarro', 'Sofia Navarro']],
    ]);

    it('explains when no child matches the filters', function () {
        $parent = User::factory()->parent()->guardianOf(childPageLearner('Sofia Navarro'))->create();

        $this->actingAs($parent)
            ->get(route('parent.children.index', ['search' => 'nobody']))
            ->assertOk()
            ->assertSeeText('No children match your filters')
            ->assertSee('data-auto-submit', false)
            ->assertSee('href="'.route('parent.children.index').'"', false);
    });

    it('paginates twelve children per page and keeps the filters', function () {
        $parent = User::factory()->parent()->create();
        foreach (range(1, 13) as $number) {
            $parent->linkedStudents()->attach(childPageLearner(sprintf('Learner %02d', $number)));
        }

        $this->actingAs($parent)
            ->get(route('parent.children.index', ['search' => 'Learner', 'page' => 2]))
            ->assertOk()
            ->assertViewHas('cards', fn (array $cards): bool => count($cards) === 1 && $cards[0]['student']->name === 'Learner 13')
            ->assertSee('search=Learner', false);
    });

    it('runs the same number of queries however many children are listed', function () {
        $parent = User::factory()->parent()->create();
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
        $addChild = function () use ($parent, $classroom): void {
            $child = childPageLearner('Learner '.fake()->unique()->numberBetween(1, 999));
            $parent->linkedStudents()->attach($child);
            QuizAttempt::factory()->for($child, 'student')->for($classroom)->create();
            MissionProgress::factory()->completed()->for($child, 'student')->create();
        };
        $countQueries = function () use ($parent): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($parent)->get(route('parent.children.index'))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $addChild();
        $before = $countQueries();
        foreach (range(1, 5) as $ignored) {
            $addChild();
        }

        expect($countQueries())->toBe($before);
    });
});

describe('show', function () {
    it('sends guests to the sign-in page', function () {
        $this->get(route('parent.children.show', childPageLearner('Sofia Navarro')))->assertRedirectToRoute('login');
    });

    it('forbids accounts of the other portals', function (UserRole $role) {
        $child = childPageLearner('Sofia Navarro');

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('parent.children.show', $child))
            ->assertForbidden();
    })->with([
        'administrator' => UserRole::Admin,
        'teacher' => UserRole::Teacher,
        'learner' => UserRole::Student,
    ]);

    it('reports a learner who is not linked to the parent as missing', function () {
        $parent = User::factory()->parent()->guardianOf(childPageLearner('Sofia Navarro'))->create();
        $stranger = childPageLearner('Paolo Reyes');
        User::factory()->parent()->guardianOf($stranger)->create();

        $this->actingAs($parent)
            ->get(route('parent.children.show', $stranger))
            ->assertNotFound();
    });

    it('reports an account that is not a learner as missing', function () {
        $parent = User::factory()->parent()->create();

        $this->actingAs($parent)
            ->get(route('parent.children.show', $parent))
            ->assertNotFound();
    });

    it('forbids a linked learner when the school turned child progress off', function () {
        $child = childPageLearner('Sofia Navarro');
        $parent = User::factory()->parent()->guardianOf($child)->create();
        app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewChildProgress);

        $this->actingAs($parent)
            ->get(route('parent.children.show', $child))
            ->assertForbidden()
            ->assertSeeText('You do not have permission to view child progress.');
    });

    it('shows the overview with the learner progress components', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
        $child = childPageLearner('Sofia Navarro', GradeLevel::Five, ['total_points' => 1240, 'lrn' => '136742210001']);
        $parent = User::factory()->parent()->guardianOf($child, GuardianRelationship::Mother)->create();
        $quiz = Quiz::factory()->published()->create(['title' => 'Healthy Plate Quiz']);
        $attempt = QuizAttempt::factory()->for($quiz)->for($child, 'student')->create(['percentage' => 80, 'passed' => true, 'submitted_at' => now()->subDay()]);
        MissionProgress::factory()->completed()->for($child, 'student')->create();
        $child->rewards()->attach(Reward::factory()->create(['name' => 'Hydration Hero']));

        $response = $this->actingAs($parent)->get(route('parent.children.show', $child));

        $response->assertOk()
            ->assertViewIs('parent.children.show')
            ->assertViewHas('tab', 'overview')
            ->assertSeeText('LRN 136742210001')
            ->assertSee('aria-label="Level 3"', false)
            ->assertSeeText('Mother')
            ->assertSeeText('Healthy Plate Quiz')
            ->assertSeeText('Hydration Hero')
            ->assertSee('data-chart="line"', false)
            ->assertSee('href="'.route('parent.children.attempts.show', [$child, $attempt]).'"', false)
            ->assertSee('aria-current="page"', false);
        $response->assertViewHas('summary', fn ($summary): bool => $summary->averageScore === 80.0 && $summary->missionsCompleted === 1);
    });

    it('does not link attempts without the view quiz details permission', function () {
        $child = childPageLearner('Sofia Navarro');
        $parent = User::factory()->parent()->guardianOf($child)->create();
        $attempt = QuizAttempt::factory()->for($child, 'student')->create();
        app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewQuizDetails);

        $this->actingAs($parent)
            ->get(route('parent.children.show', $child))
            ->assertOk()
            ->assertSeeText($attempt->quiz->title)
            ->assertDontSee(route('parent.children.attempts.show', [$child, $attempt]), false)
            ->assertViewHas('attemptUrl', null);
    });

    it('falls back to the overview for an unknown tab', function (mixed $tab) {
        $child = childPageLearner('Sofia Navarro');
        $parent = User::factory()->parent()->guardianOf($child)->create();

        $this->actingAs($parent)
            ->get(route('parent.children.show', [$child, 'tab' => $tab]))
            ->assertOk()
            ->assertViewHas('tab', 'overview');
    })->with([
        'unknown name' => 'grades',
        'array' => [['quizzes']],
    ]);

    it('offers the feedback button with the number of unread messages', function () {
        $child = childPageLearner('Sofia Navarro');
        $parent = User::factory()->parent()->guardianOf($child)->create();
        LearnerFeedback::factory()->count(2)->for($child, 'student')->create();
        LearnerFeedback::factory()->read()->for($child, 'student')->create();
        LearnerFeedback::factory()->hiddenFromGuardians()->for($child, 'student')->create();

        $this->actingAs($parent)
            ->get(route('parent.children.show', $child))
            ->assertOk()
            ->assertSeeText('Read 2 new messages')
            ->assertSee('href="'.route('parent.children.show', [$child, 'tab' => 'feedback']).'"', false);
    });

    describe('quizzes tab', function () {
        it('lists the child’s submitted attempts newest first', function () {
            $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            $older = QuizAttempt::factory()->for(Quiz::factory()->published()->create(['title' => 'Go Grow Glow Quiz']))->for($child, 'student')
                ->create(['submitted_at' => now()->subDays(3)]);
            $newer = QuizAttempt::factory()->offline()->for(Quiz::factory()->published()->create(['title' => 'Snack Swap Quiz']))->for($child, 'student')
                ->create(['submitted_at' => now()->subDay()]);
            QuizAttempt::factory()->inProgress()->for(Quiz::factory()->published()->create(['title' => 'Unfinished Quiz']))->for($child, 'student')->create();
            QuizAttempt::factory()->for(Quiz::factory()->published()->create(['title' => 'Another Family Quiz']))->create();

            $response = $this->actingAs($parent)->get(route('parent.children.show', [$child, 'tab' => 'quizzes']));

            $response->assertOk()
                ->assertSeeInOrder(['Snack Swap Quiz', 'Played offline', 'Go Grow Glow Quiz'])
                ->assertDontSeeText('Unfinished Quiz')
                ->assertDontSeeText('Another Family Quiz')
                ->assertSee('href="'.route('parent.children.attempts.show', [$child, $newer]).'"', false)
                ->assertSee('href="'.route('parent.children.attempts.show', [$child, $older]).'"', false);
            $response->assertViewHas('attempts', fn ($attempts): bool => $attempts->total() === 2)
                ->assertViewHas('attemptCount', 2);
        });

        it('filters attempts by subject and result', function (array $query, array $expected) {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            $health = Subject::factory()->create(['name' => 'Health']);
            $science = Subject::factory()->create(['name' => 'Science']);
            QuizAttempt::factory()->for(Quiz::factory()->published()->for($health)->create(['title' => 'Health Passed']))->for($child, 'student')
                ->create(['percentage' => 90, 'passed' => true, 'submitted_at' => now()->subDays(3)]);
            QuizAttempt::factory()->for(Quiz::factory()->published()->for($health)->create(['title' => 'Health Failed']))->for($child, 'student')
                ->create(['percentage' => 50, 'passed' => false, 'submitted_at' => now()->subDays(2)]);
            QuizAttempt::factory()->for(Quiz::factory()->published()->for($science)->create(['title' => 'Science Passed']))->for($child, 'student')
                ->create(['percentage' => 80, 'passed' => true, 'submitted_at' => now()->subDay()]);
            $query = array_map(fn (mixed $value): mixed => match ($value) {
                'health' => $health->id,
                'science' => $science->id,
                default => $value,
            }, $query);

            $response = $this->actingAs($parent)->get(route('parent.children.show', [$child, 'tab' => 'quizzes', ...$query]));

            $response->assertOk()->assertViewHas('attempts', fn ($attempts): bool => $attempts->getCollection()->map(fn ($attempt) => $attempt->quiz->title)->all() === $expected);
        })->with([
            'subject' => [['subject' => 'health'], ['Health Failed', 'Health Passed']],
            'passed' => [['result' => 'passed'], ['Science Passed', 'Health Passed']],
            'not passed' => [['result' => 'not_passed'], ['Health Failed']],
            'subject and result' => [['subject' => 'health', 'result' => 'passed'], ['Health Passed']],
            'unknown result is ignored' => [['result' => 'perfect'], ['Science Passed', 'Health Failed', 'Health Passed']],
        ]);

        it('ignores a subject the child has no attempts in', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            QuizAttempt::factory()->for($child, 'student')->create();
            $otherSubject = Subject::factory()->create();

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'quizzes', 'subject' => $otherSubject->id]))
                ->assertOk()
                ->assertViewHas('isFiltered', false)
                ->assertViewHas('filters', ['subject' => null, 'result' => null])
                ->assertViewHas('attempts', fn ($attempts): bool => $attempts->total() === 1)
                ->assertViewHas('subjectOptions', fn (array $options): bool => ! array_key_exists($otherSubject->id, $options));
        });

        it('paginates fifteen attempts per page', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            $quiz = Quiz::factory()->published()->create();
            QuizAttempt::factory()->count(16)->for($quiz)->for($child, 'student')->sequence(fn ($sequence) => [
                'attempt_number' => $sequence->index + 1,
                'submitted_at' => now()->subMinutes(100 - $sequence->index),
            ])->create();

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'quizzes', 'page' => 2]))
                ->assertOk()
                ->assertViewHas('attempts', fn ($attempts): bool => $attempts->count() === 1 && $attempts->first()->attempt_number === 1)
                ->assertSee('tab=quizzes&amp;page=1', false);
        });

        it('shows the empty states', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'quizzes']))
                ->assertOk()
                ->assertSeeText('No quiz attempts yet');

            QuizAttempt::factory()->failed()->for($child, 'student')->create();

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'quizzes', 'result' => 'passed']))
                ->assertOk()
                ->assertSeeText('No attempts match your filters');
        });
    });

    describe('missions tab', function () {
        it('lists missions in progress and completed with the totals', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            MissionProgress::factory()->completed()->for(Mission::factory()->create(['title' => 'Rainbow Plate', 'points_reward' => 100]))->for($child, 'student')->create();
            MissionProgress::factory()->completed()->for(Mission::factory()->create(['title' => 'Water Warrior', 'points_reward' => 50]))->for($child, 'student')->create();
            MissionProgress::factory()->inProgress()->for(Mission::factory()->create(['title' => 'Snack Swap Hero']))->for($child, 'student')->create();
            MissionProgress::factory()->for(Mission::factory()->create(['title' => 'Label Reader']))->for($child, 'student')->create(['status' => ProgressStatus::NotStarted, 'progress_percent' => 0]);
            MissionProgress::factory()->completed()->for(Mission::factory()->create(['title' => 'Another Family Mission']))->create();

            $response = $this->actingAs($parent)->get(route('parent.children.show', [$child, 'tab' => 'missions']));

            $response->assertOk()
                ->assertSeeText('Rainbow Plate')
                ->assertSeeText('Water Warrior')
                ->assertSeeText('Snack Swap Hero')
                ->assertDontSeeText('Label Reader')
                ->assertDontSeeText('Another Family Mission')
                ->assertSeeText('150 XP')
                ->assertViewHas('missionPoints', 150)
                ->assertViewHas('missions', fn ($missions): bool => $missions->total() === 3);
        });

        it('filters missions by status and subject', function (array $query, array $expected) {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            $health = Subject::factory()->create();
            $science = Subject::factory()->create();
            MissionProgress::factory()->completed()->for(Mission::factory()->for($health)->create(['title' => 'Health Done']))->for($child, 'student')->create(['updated_at' => now()->subDays(3)]);
            MissionProgress::factory()->inProgress()->for(Mission::factory()->for($health)->create(['title' => 'Health Going']))->for($child, 'student')->create(['updated_at' => now()->subDays(2)]);
            MissionProgress::factory()->completed()->for(Mission::factory()->for($science)->create(['title' => 'Science Done']))->for($child, 'student')->create(['updated_at' => now()->subDay()]);
            $query = array_map(fn (mixed $value): mixed => $value === 'health' ? $health->id : $value, $query);

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'missions', ...$query]))
                ->assertOk()
                ->assertViewHas('missions', fn ($missions): bool => $missions->getCollection()->map(fn ($progress) => $progress->mission->title)->all() === $expected);
        })->with([
            'completed' => [['status' => 'completed'], ['Science Done', 'Health Done']],
            'in progress' => [['status' => 'in_progress'], ['Health Going']],
            'subject' => [['subject' => 'health'], ['Health Going', 'Health Done']],
            'not started is not a filter' => [['status' => 'not_started'], ['Science Done', 'Health Going', 'Health Done']],
        ]);

        it('shows the empty states', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'missions']))
                ->assertOk()
                ->assertSeeText('No missions started yet');

            MissionProgress::factory()->inProgress()->for($child, 'student')->create();

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'missions', 'status' => 'completed']))
                ->assertOk()
                ->assertSeeText('No missions match your filters');
        });
    });

    describe('feedback tab', function () {
        it('shows guardian-visible feedback and marks the unread messages as read', function () {
            $this->freezeSecond();
            $child = childPageLearner('Sofia Navarro');
            $sibling = childPageLearner('Miguel Navarro', GradeLevel::Six);
            $parent = User::factory()->parent()->guardianOf($child)->guardianOf($sibling)->create();
            $teacher = User::factory()->teacher()->create(['name' => 'Maricel Santos']);
            $unread = LearnerFeedback::factory()->for($teacher, 'teacher')->for($child, 'student')
                ->create(['type' => FeedbackType::Praise, 'message' => "Great plate today!\nKeep it up.", 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
            $alreadyRead = LearnerFeedback::factory()->for($teacher, 'teacher')->for($child, 'student')
                ->create(['message' => 'An older note.', 'read_at' => now()->subWeek(), 'created_at' => now()->subWeeks(2)]);
            $hidden = LearnerFeedback::factory()->hiddenFromGuardians()->for($teacher, 'teacher')->for($child, 'student')
                ->create(['message' => 'Adviser-only note.']);
            $siblingNote = LearnerFeedback::factory()->for($teacher, 'teacher')->for($sibling, 'student')->create();

            $response = $this->actingAs($parent)->get(route('parent.children.show', [$child, 'tab' => 'feedback']));

            $response->assertOk()
                ->assertSeeInOrder(['Maricel Santos', 'New', 'Praise', 'Great plate today!', 'An older note.'])
                ->assertSee('data-feedback-new="true"', false)
                ->assertDontSeeText('Adviser-only note.')
                ->assertViewHas('newFeedbackIds', [$unread->id])
                ->assertViewHas('unreadFeedbackCount', 0);
            expect($unread->refresh()->read_at?->toDateTimeString())->toBe(now()->toDateTimeString())
                ->and($unread->updated_at->toDateTimeString())->toBe(now()->subDay()->toDateTimeString())
                ->and($alreadyRead->refresh()->read_at->toDateTimeString())->toBe(now()->subWeek()->toDateTimeString())
                ->and($hidden->refresh()->read_at)->toBeNull()
                ->and($siblingNote->refresh()->read_at)->toBeNull();
        });

        it('marks only the messages shown on the page as read', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            $messages = LearnerFeedback::factory()->count(11)->for($child, 'student')
                ->sequence(fn ($sequence) => ['created_at' => now()->subHours($sequence->index)])
                ->create();

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'feedback']))
                ->assertOk()
                ->assertViewHas('unreadFeedbackCount', 1);

            expect($messages->last()->refresh()->read_at)->toBeNull()
                ->and($messages->first()->refresh()->read_at)->not->toBeNull();
        });

        it('filters feedback by type', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            LearnerFeedback::factory()->for($child, 'student')->create(['type' => FeedbackType::Praise, 'message' => 'Wonderful work.']);
            $concern = LearnerFeedback::factory()->for($child, 'student')->create(['type' => FeedbackType::Concern, 'message' => 'We miss you in class.']);

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'feedback', 'type' => 'concern']))
                ->assertOk()
                ->assertSeeText('We miss you in class.')
                ->assertDontSeeText('Wonderful work.')
                ->assertViewHas('newFeedbackIds', [$concern->id]);
        });

        it('links the quiz attempt a message is about when the parent may view quiz details', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            $classroom = Classroom::factory()->create(['name' => 'Health 5 – Sampaguita']);
            $attempt = QuizAttempt::factory()->for(Quiz::factory()->published()->create(['title' => 'Healthy Plate Quiz']))->for($child, 'student')->create();
            $otherAttempt = QuizAttempt::factory()->for(Quiz::factory()->published()->create(['title' => 'Someone Else’s Quiz']))->create();
            LearnerFeedback::factory()->for($child, 'student')->for($classroom)->for($attempt, 'quizAttempt')->create();
            LearnerFeedback::factory()->for($child, 'student')->for($otherAttempt, 'quizAttempt')->create();

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'feedback']))
                ->assertOk()
                ->assertSeeText('Health 5 – Sampaguita')
                ->assertSee('href="'.route('parent.children.attempts.show', [$child, $attempt]).'"', false)
                ->assertDontSee(route('parent.children.attempts.show', [$child, $otherAttempt]), false);
        });

        it('does not link attempts without the view quiz details permission', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            $attempt = QuizAttempt::factory()->for(Quiz::factory()->published()->create(['title' => 'Healthy Plate Quiz']))->for($child, 'student')->create();
            LearnerFeedback::factory()->for($child, 'student')->for($attempt, 'quizAttempt')->create();
            app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewQuizDetails);

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'feedback']))
                ->assertOk()
                ->assertSeeText('Healthy Plate Quiz')
                ->assertDontSee(route('parent.children.attempts.show', [$child, $attempt]), false);
        });

        it('forbids the tab without the view teacher feedback permission and leaves messages unread', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            $message = LearnerFeedback::factory()->for($child, 'student')->create();
            app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewTeacherFeedback);

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'feedback']))
                ->assertForbidden()
                ->assertSeeText('You do not have permission to view teacher feedback.');

            expect($message->refresh()->read_at)->toBeNull();
        });

        it('leaves the feedback tab out of the page without the permission', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            LearnerFeedback::factory()->for($child, 'student')->create();
            app(RolePermissions::class)->revoke(UserRole::Parent, Permission::ViewTeacherFeedback);

            $this->actingAs($parent)
                ->get(route('parent.children.show', $child))
                ->assertOk()
                ->assertDontSee(route('parent.children.show', [$child, 'tab' => 'feedback']), false)
                ->assertDontSeeText('new message');
        });

        it('escapes the teacher’s message', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            LearnerFeedback::factory()->for($child, 'student')->create(['message' => '<script>alert("x")</script>']);

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'feedback']))
                ->assertOk()
                ->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', false)
                ->assertDontSee('<script>alert("x")</script>', false);
        });

        it('shows the empty states', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'feedback']))
                ->assertOk()
                ->assertSeeText('No messages yet');

            LearnerFeedback::factory()->for($child, 'student')->create(['type' => FeedbackType::Praise]);

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'feedback', 'type' => 'concern']))
                ->assertOk()
                ->assertSeeText('No messages of this type');
        });
    });

    describe('rewards tab', function () {
        it('shows the earned rewards and the active rewards still to earn', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            $earned = Reward::factory()->create(['name' => 'Hydration Hero', 'type' => RewardType::Badge]);
            $child->rewards()->attach($earned, ['awarded_at' => '2026-11-10 09:00:00']);
            $locked = Reward::factory()->create(['name' => 'Veggie Valley Trophy', 'type' => RewardType::Trophy, 'criteria' => 'Finish every Veggie Valley mission', 'points_required' => 1000]);
            Reward::factory()->inactive()->create(['name' => 'Retired Badge']);

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'rewards']))
                ->assertOk()
                ->assertSeeInOrder(['Rewards earned', 'Hydration Hero', 'Nov 10, 2026', 'Still to earn', 'Veggie Valley Trophy', 'Finish every Veggie Valley mission', 'Unlocks at 1,000 XP'])
                ->assertSee('data-locked-reward="'.$locked->id.'"', false)
                ->assertDontSee('data-locked-reward="'.$earned->id.'"', false)
                ->assertDontSeeText('Retired Badge');
        });

        it('filters both lists by reward type', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            $child->rewards()->attach(Reward::factory()->create(['name' => 'Hydration Hero', 'type' => RewardType::Badge]));
            $child->rewards()->attach(Reward::factory()->create(['name' => 'Golden Spoon', 'type' => RewardType::Trophy]));
            Reward::factory()->create(['name' => 'Chef Hat', 'type' => RewardType::AvatarItem]);
            Reward::factory()->create(['name' => 'Fruit Trophy', 'type' => RewardType::Trophy]);

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'rewards', 'reward_type' => 'trophy']))
                ->assertOk()
                ->assertSeeText('Golden Spoon')
                ->assertSeeText('Fruit Trophy')
                ->assertDontSeeText('Hydration Hero')
                ->assertDontSeeText('Chef Hat');
        });

        it('celebrates when every reward is collected', function () {
            $child = childPageLearner('Sofia Navarro');
            $parent = User::factory()->parent()->guardianOf($child)->create();
            $child->rewards()->attach(Reward::factory()->create());

            $this->actingAs($parent)
                ->get(route('parent.children.show', [$child, 'tab' => 'rewards']))
                ->assertOk()
                ->assertSeeText('Every reward collected');
        });
    });

    it('renders every tab with a long history in a bounded number of queries', function (string $tab) {
        $child = childPageLearner('Sofia Navarro');
        $parent = User::factory()->parent()->guardianOf($child)->create();
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
        $addHistory = function (int $times) use ($child, $classroom): void {
            QuizAttempt::factory()->count($times)->for($child, 'student')->for($classroom)->create();
            MissionProgress::factory()->completed()->count($times)->for($child, 'student')->create();
            $attempt = QuizAttempt::factory()->for($child, 'student')->create();
            LearnerFeedback::factory()->count($times)->for($child, 'student')->for($classroom)->for($attempt, 'quizAttempt')->create();
            $child->rewards()->attach(Reward::factory()->count($times)->create());
            Reward::factory()->count($times)->create();
        };
        $countQueries = function () use ($parent, $child, $tab): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($parent)->get(route('parent.children.show', [$child, 'tab' => $tab]))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $addHistory(1);
        $countQueries();
        $addHistory(1);
        $before = $countQueries();
        $addHistory(5);

        expect($countQueries())->toBe($before);
    })->with(['overview', 'quizzes', 'missions', 'feedback', 'rewards']);
});
