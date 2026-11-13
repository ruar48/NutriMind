<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\CsvExport;
use App\Services\LearnerProgressService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\LearnerProgressFixture;

beforeEach(fn () => Model::preventLazyLoading());
afterEach(fn () => Model::preventLazyLoading(false));

it('renders the admin recipe', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $student = LearnerProgressFixture::create()->student;
    $student->load(['guardians' => fn ($query) => $query->orderBy('name')->orderBy('users.id')]);
    $summary = app(LearnerProgressService::class)->summarize($student);
    $this->actingAs(User::factory()->admin()->create());
    $html = (string) $this->blade(<<<'BLADE'
<x-layouts.app :title="$student->name">
    <x-page-header :title="$student->name" description="Learner profile and game progress" :breadcrumbs="[['label' => 'Learners', 'url' => route('admin.students.index')], ['label' => $student->name]]">
        <x-slot:actions><x-button variant="secondary" icon="pencil" :href="route('admin.students.edit', $student)">Edit learner</x-button></x-slot:actions>
    </x-page-header>
    <x-learner.profile-header :summary="$summary" />
    <x-learner.progress-overview :summary="$summary" />
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-learner.weekly-trend :weeks="$summary->weeklyScores" class="xl:col-span-2" />
        <x-learner.rewards-shelf :rewards="$summary->rewards" />
    </div>
    <x-learner.subject-breakdown :subjects="$summary->subjectBreakdown" />
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-learner.recent-attempts :attempts="$summary->recentAttempts" />
        <x-learner.missions-list :missions="$summary->recentMissions" />
    </div>
</x-layouts.app>
BLADE, ['student' => $student, 'summary' => $summary]);
    expect($html)->toContain('Juan Dela Cruz')->toContain('Edit learner')->not->toContain('/teacher/attempts');
});

it('renders the teacher recipe with links only to own quizzes', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $fixture = LearnerProgressFixture::create();
    $teacher = $fixture->healthClassroom->teacher()->first();
    $student = $fixture->student;
    $progress = app(LearnerProgressService::class);
    $subjectIds = $progress->teacherSubjectIds($teacher, $student);
    $summary = $progress->summarize($student, $subjectIds);
    App\Models\Quiz::query()->whereKey($fixture->healthyPlateQuiz->id)->update(["teacher_id" => $teacher->id]);
    $summary = $progress->summarize($student, $subjectIds);
    $attemptUrl = fn (QuizAttempt $attempt): ?string => $attempt->quiz->teacher()->is($teacher) ? route('teacher.attempts.show', $attempt) : null;
    $this->actingAs($teacher);
    $html = (string) $this->blade(<<<'BLADE'
<x-layouts.app :title="$student->name">
    <x-page-header :title="$student->name" description="Progress in your subjects" :breadcrumbs="[['label' => 'Learners', 'url' => route('teacher.students.index')], ['label' => $student->name]]">
        <x-slot:actions><x-button icon="message-square" :href="route('teacher.feedback.create', ['student' => $student->id])">Send feedback</x-button></x-slot:actions>
    </x-page-header>
    <x-learner.profile-header :summary="$summary" />
    <x-learner.progress-overview :summary="$summary" />
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-learner.weekly-trend :weeks="$summary->weeklyScores" description="Your subjects · average of the attempts submitted each week" class="xl:col-span-2" />
        <x-learner.subject-breakdown :subjects="$summary->subjectBreakdown" title="Your subjects" />
    </div>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-learner.recent-attempts :attempts="$summary->recentAttempts" :attempt-url="$attemptUrl" />
        <x-learner.missions-list :missions="$summary->recentMissions" />
    </div>
    <x-learner.rewards-shelf :rewards="$summary->rewards" />
</x-layouts.app>
BLADE, ['student' => $student, 'summary' => $summary, 'attemptUrl' => $attemptUrl]);
    $own = $summary->recentAttempts->filter(fn (QuizAttempt $attempt): bool => $attempt->quiz_id === $fixture->healthyPlateQuiz->id);
    expect($html)->not->toContain(route("teacher.attempts.show", $fixture->attempts["healthySnacks"]));
    fwrite(STDERR, 'teacher subjects='.json_encode($subjectIds).' health='.$fixture->health->id.' recent='.$summary->recentAttempts->count().' own='.$own->count().PHP_EOL);
    foreach ($own as $attempt) {
        expect($html)->toContain('href="'.route('teacher.attempts.show', $attempt).'"');
    }
    expect($html)->toContain('Your subjects')->toContain('Send feedback')->not->toContain('Digestion');
});

it('renders the parent recipe', function () {
    $this->travelTo(CarbonImmutable::parse(LearnerProgressFixture::NOW));
    $fixture = LearnerProgressFixture::create();
    $student = $fixture->student;
    $parent = User::factory()->parent()->guardianOf($student)->create();
    $canViewQuizDetails = $parent->hasPermission(Permission::ViewQuizDetails);
    $summary = app(LearnerProgressService::class)->summarize($student);
    $attemptUrl = $canViewQuizDetails ? fn (QuizAttempt $attempt): string => route('parent.children.attempts.show', [$student, $attempt]) : null;
    $this->actingAs($parent);
    $html = (string) $this->blade(<<<'BLADE'
<x-layouts.app :title="$student->name">
    <x-page-header :title="$student->name" description="Your child’s learning progress" :breadcrumbs="[['label' => 'My children', 'url' => route('parent.children.index')], ['label' => $student->name]]" />
    <x-learner.profile-header :summary="$summary" />
    @if ($tab === 'overview')
        <x-learner.progress-overview :summary="$summary" />
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <x-learner.weekly-trend :weeks="$summary->weeklyScores" class="xl:col-span-2" />
            <x-learner.rewards-shelf :rewards="$summary->rewards->take(6)" description="Latest rewards">
                <x-slot:actions>
                    <x-button variant="ghost" size="sm" icon-trailing="arrow-right" :href="route('parent.children.show', [$student, 'tab' => 'rewards'])">All rewards</x-button>
                </x-slot:actions>
            </x-learner.rewards-shelf>
        </div>
        <x-learner.subject-breakdown :subjects="$summary->subjectBreakdown" />
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-learner.recent-attempts :attempts="$summary->recentAttempts" :attempt-url="$attemptUrl" />
            <x-learner.missions-list :missions="$summary->recentMissions" />
        </div>
    @endif
</x-layouts.app>
BLADE, ['student' => $student, 'summary' => $summary, 'attemptUrl' => $attemptUrl, 'tab' => 'overview']);
    expect($canViewQuizDetails)->toBeTrue();
    expect($html)->toContain(route('parent.children.attempts.show', [$student, $fixture->attempts['digestion']]))
        ->toContain('All rewards')
        ->toContain('Latest rewards');
});

it('runs the csv recipe', function () {
    User::factory()->student()->count(3)->create();
    $students = User::query()->ofRole(UserRole::Student)->with('studentProfile')->orderBy('name')->orderBy('id');

    $response = app(CsvExport::class)->download('learners-'.now()->format('Y-m-d'), ['Name', 'Username', 'LRN', 'Grade', 'Points', 'Status'],
        $students->lazy()->map(fn (User $student): array => [
            $student->name,
            $student->username,
            $student->studentProfile?->lrn,
            $student->studentProfile?->grade_level,
            $student->studentProfile?->total_points ?? 0,
            $student->is_active ? 'Active' : 'Inactive',
        ]));

    $body = TestResponse::fromBaseResponse($response)->streamedContent();
    fwrite(STDERR, $body);
    expect(substr_count($body, "\r\n"))->toBe(4)->and($body)->toContain('Grade ');
});
