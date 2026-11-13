<?php

namespace Database\Seeders;

use App\Enums\QuizStatus;
use App\Models\AcademicTerm;
use App\Models\Classroom;
use App\Models\LearningModule;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\Support\BulkInsert;
use Database\Seeders\Support\Catalog\ContentCatalog;
use Database\Seeders\Support\Catalog\PeopleCatalog;
use Database\Seeders\Support\Catalog\QuestionCatalog;
use Database\Seeders\Support\Catalog\QuizCatalog;
use Database\Seeders\Support\Catalog\SchoolCatalog;
use Database\Seeders\Support\DemoClock;
use Database\Seeders\Support\DemoRandom;
use Illuminate\Database\Seeder;

/**
 * Seeds the quizzes, their questions and their classroom assignments.
 *
 * Questions are mostly copies of question bank items the teacher can see (own or shared, written before the quiz),
 * plus a few the teacher wrote directly in the quiz. Each bank item's usage count matches its copies.
 */
class QuizSeeder extends Seeder
{
    /**
     * The order questions appear in: easier questions first.
     */
    private const DIFFICULTY_ORDER = ['easy' => 0, 'medium' => 1, 'hard' => 2];

    /**
     * The seeded random stream of this seeder.
     */
    private DemoRandom $random;

    /**
     * The anchored clock of this run.
     */
    private DemoClock $clock;

    /**
     * The learning modules keyed by competency code.
     *
     * @var array<string, array{id: int, published: bool}>
     */
    private array $modules = [];

    /**
     * The bank items keyed by ID, with the catalog entry they were written from.
     *
     * @var array<int, array{row: object, question: array<string, mixed>}>
     */
    private array $bankItems = [];

    /**
     * How many quiz questions were copied from each bank item.
     *
     * @var array<int, int>
     */
    private array $bankUsage = [];

    /**
     * How many quizzes each teacher used each of their own quiz-only questions in, keyed by teacher and prompt.
     *
     * @var array<string, array<string, int>>
     */
    private array $extraUsage = [];

    /**
     * The questions of each planned quiz, keyed by "teacher ID|title".
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private array $questions = [];

    /**
     * The classroom assignments of each planned quiz, keyed by "teacher ID|title".
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private array $assignments = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->random = DemoRandom::stream('quizzes');
        $this->clock = DemoClock::start();
        $this->modules = LearningModule::query()->get(['id', 'competency_code', 'is_published'])
            ->mapWithKeys(fn (LearningModule $module): array => [$module->competency_code => ['id' => $module->id, 'published' => $module->is_published]])
            ->all();

        $catalog = QuestionCatalog::byPrompt();

        foreach (QuestionBankItem::query()->orderBy('id')->toBase()->get() as $row) {
            $this->bankItems[(int) $row->id] = ['row' => $row, 'question' => $catalog[$row->prompt]];
        }

        $teacherIds = User::query()->toBase()->pluck('id', 'email');
        $subjectIds = Subject::query()->pluck('id', 'code');
        $activeYear = SchoolYear::query()->where('name', SchoolCatalog::ACTIVE_SCHOOL_YEAR)->sole();
        $terms = AcademicTerm::query()->whereBelongsTo($activeYear)->orderBy('sequence')->get(['id', 'starts_on']);

        $classroomIds = [];

        foreach (Classroom::query()->active()->whereBelongsTo($activeYear)->get(['id', 'section', 'subject_id']) as $classroom) {
            $classroomIds[$classroom->section][$classroom->subject_id] = $classroom->id;
        }

        $quizzes = [];

        foreach (QuizCatalog::groups() as $group) {
            $teacherId = $teacherIds[PeopleCatalog::teacherEmail($group['teacher'])];
            $subjectId = $subjectIds[$group['subject']];
            $classrooms = array_map(fn (string $section): int => $classroomIds[$section][$subjectId], $group['sections']);

            foreach ($group['quizzes'] as $spec) {
                $quizzes[] = $this->plan($group, $spec, $teacherId, $subjectId, $classrooms, $terms->all());
            }
        }

        $quizzes[] = $this->planArchivedQuiz($teacherIds->all(), $subjectIds->all());

        BulkInsert::models(Quiz::class, $quizzes);

        $this->seedQuestionsAndAssignments();
    }

    /**
     * Plan a quiz of the active school year: its row, questions and classroom assignments.
     *
     * @param  array{teacher: string, subject: string, grade: int}  $group
     * @param  array{title: string, focus: list<string>, due: int, status: string, questions: int, minutes: int, attempts: int|null, passing: int, shuffle: bool, show_answers: bool, assigned: bool, reviewed: bool}  $spec
     * @param  list<int>  $classroomIds
     * @param  list<AcademicTerm>  $terms
     * @return array<string, mixed>
     */
    private function plan(array $group, array $spec, int $teacherId, int $subjectId, array $classroomIds, array $terms): array
    {
        $dueDay = $this->clock->startOfToday() + $spec['due'] * DemoClock::DAY;
        $dueAt = $dueDay + 23 * DemoClock::HOUR + 59 * 60;
        $availableFrom = $dueDay - $this->random->int(6, 9) * DemoClock::DAY + 7 * DemoClock::HOUR;
        $status = QuizStatus::from($spec['status']);

        if ($status === QuizStatus::Draft) {
            $createdAt = min(DemoClock::workTime($this->random, $this->clock->startOfToday() - $this->random->int(1, 5) * DemoClock::DAY), $this->clock->now - 2 * DemoClock::HOUR);
            $reviewedAt = $spec['reviewed'] ? min($createdAt + $this->random->int(2, 20) * DemoClock::HOUR, $this->clock->now - 30 * 60) : null;
            $assignedAt = $createdAt + $this->random->int(10, 60) * 60;
            $publishedAt = null;
            $updatedAt = $reviewedAt ?? $createdAt + 30 * 60;
        } else {
            $assignedAt = min(
                DemoClock::workTime($this->random, DemoClock::startOfDay($availableFrom) - $this->random->int(1, 3) * DemoClock::DAY),
                $this->clock->now - $this->random->int(1, 30) * DemoClock::HOUR,
            );
            $publishedAt = $assignedAt;
            $reviewedAt = $publishedAt - $this->random->int(1, 20) * DemoClock::HOUR;
            $createdAt = $reviewedAt - $this->random->int(6, 72) * DemoClock::HOUR;
            $updatedAt = $status === QuizStatus::Closed
                ? min(DemoClock::workTime($this->random, DemoClock::startOfDay($dueAt) + $this->random->int(1, 3) * DemoClock::DAY), $this->clock->now - DemoClock::HOUR)
                : $publishedAt;
        }

        $key = $teacherId.'|'.$spec['title'];
        $questions = $this->pickQuestions($group, $teacherId, $spec['focus'], $spec['questions'], $createdAt);
        $this->questions[$key] = $this->questionRows($questions, $createdAt);

        if ($spec['assigned']) {
            $this->assignments[$key] = array_map(fn (int $classroomId): array => [
                'classroom_id' => $classroomId,
                'available_from' => DemoClock::format($availableFrom),
                'due_at' => DemoClock::format($dueAt),
                'assigned_at' => DemoClock::format($assignedAt),
                'created_at' => DemoClock::format($assignedAt),
                'updated_at' => DemoClock::format($assignedAt),
            ], $classroomIds);
        }

        $focusModule = count($spec['focus']) === 1 ? ContentCatalog::moduleByCode($spec['focus'][0]) : null;

        return [
            'teacher_id' => $teacherId,
            'subject_id' => $subjectId,
            'learning_module_id' => $focusModule === null ? null : $this->modules[$focusModule['code']]['id'],
            'academic_term_id' => $this->termOf($terms, DemoClock::date($dueDay)),
            'grade_level' => $group['grade'],
            'title' => $spec['title'],
            'instructions' => $this->instructions(count($questions), $spec['minutes'], $focusModule['topic'] ?? null),
            'time_limit_minutes' => $spec['minutes'],
            'passing_score' => $spec['passing'],
            'max_attempts' => $spec['attempts'],
            'shuffle_questions' => $spec['shuffle'],
            'show_correct_answers' => $spec['show_answers'],
            'status' => $status->value,
            'reviewed_at' => $reviewedAt === null ? null : DemoClock::format($reviewedAt),
            'published_at' => $publishedAt === null ? null : DemoClock::format($publishedAt),
            'created_at' => DemoClock::format($createdAt),
            'updated_at' => DemoClock::format($updatedAt),
        ];
    }

    /**
     * Plan last year's closed quiz of the archived classroom.
     *
     * @param  array<string, int>  $teacherIds
     * @param  array<string, int>  $subjectIds
     * @return array<string, mixed>
     */
    private function planArchivedQuiz(array $teacherIds, array $subjectIds): array
    {
        $spec = QuizCatalog::archivedQuiz();
        $classroom = Classroom::query()->archived()->sole(['id', 'teacher_id', 'subject_id', 'grade_level', 'school_year_id']);
        $teacherKey = PeopleCatalog::archivedClassroom()['teacher'];
        $teacherId = $teacherIds[PeopleCatalog::teacherEmail($teacherKey)];
        $createdAt = DemoClock::at($spec['created_at']);
        $key = $teacherId.'|'.$spec['title'];

        $group = ['teacher' => $teacherKey, 'subject' => PeopleCatalog::archivedClassroom()['subject'], 'grade' => $classroom->grade_level->value];
        $questions = $this->pickQuestions($group, $teacherId, $spec['focus'], $spec['questions'], $createdAt);

        $this->questions[$key] = $this->questionRows($questions, $createdAt);
        $this->assignments[$key] = [[
            'classroom_id' => $classroom->id,
            'available_from' => $spec['available_from'],
            'due_at' => $spec['due_at'],
            'assigned_at' => $spec['published_at'],
            'created_at' => $spec['published_at'],
            'updated_at' => $spec['published_at'],
        ]];

        return [
            'teacher_id' => $teacherId,
            'subject_id' => $subjectIds[$group['subject']],
            'learning_module_id' => null,
            'academic_term_id' => AcademicTerm::query()->where('school_year_id', $classroom->school_year_id)->where('sequence', 4)->value('id'),
            'grade_level' => $group['grade'],
            'title' => $spec['title'],
            'instructions' => $this->instructions(count($questions), $spec['minutes'], null),
            'time_limit_minutes' => $spec['minutes'],
            'passing_score' => $spec['passing'],
            'max_attempts' => $spec['attempts'],
            'shuffle_questions' => false,
            'show_correct_answers' => true,
            'status' => QuizStatus::Closed->value,
            'reviewed_at' => DemoClock::format(DemoClock::at($spec['published_at']) - 3 * DemoClock::HOUR),
            'published_at' => $spec['published_at'],
            'created_at' => $spec['created_at'],
            'updated_at' => $spec['closed_at'],
        ];
    }

    /**
     * Pick the questions of a quiz: bank items about the focus modules first, then other bank items of the subject
     * and grade, plus one or two questions the teacher wrote directly in the quiz.
     *
     * @param  array{teacher: string, subject: string, grade: int}  $group
     * @param  list<string>  $focus
     * @return list<array{question: array<string, mixed>, bank_item: object|null}>
     */
    private function pickQuestions(array $group, int $teacherId, array $focus, int $count, int $quizCreatedAt): array
    {
        $isFocus = fn (array $question): bool => $focus === [] || in_array($question['module'], $focus, true);

        $bank = [];

        foreach ($this->bankItems as $id => $item) {
            $question = $item['question'];
            $row = $item['row'];

            if ($this->belongsToGroup($question, $group)
                && $this->modules[$question['module']]['published']
                && ((int) $row->teacher_id === $teacherId || (bool) $row->is_shared)
                && DemoClock::at($row->created_at) < $quizCreatedAt) {
                $bank[$id] = ($isFocus($question) ? 0 : 100) + ($this->bankUsage[$id] ?? 0) * 10 + $this->random->float();
            }
        }

        $extras = [];

        foreach (QuestionCatalog::all() as $question) {
            if (! $question['bank'] && $question['owner'] === $group['teacher'] && $this->belongsToGroup($question, $group)
                && $this->modules[$question['module']]['published']) {
                $extras[$question['prompt']] = ['question' => $question, 'rank' => ($isFocus($question) ? 0 : 100)
                    + ($this->extraUsage[$group['teacher']][$question['prompt']] ?? 0) * 10 + $this->random->float()];
            }
        }

        asort($bank);
        uasort($extras, fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);

        $preferredExtraCount = $count >= 12 ? 2 : 1;
        $extraCount = min(count($extras), max($preferredExtraCount, $count - count($bank)));
        $picked = [];

        foreach (array_slice($extras, 0, $extraCount, true) as $prompt => $extra) {
            $picked[] = ['question' => $extra['question'], 'bank_item' => null];
            $this->extraUsage[$group['teacher']][$prompt] = ($this->extraUsage[$group['teacher']][$prompt] ?? 0) + 1;
        }

        foreach (array_slice(array_keys($bank), 0, $count - count($picked)) as $id) {
            $picked[] = ['question' => $this->bankItems[$id]['question'], 'bank_item' => $this->bankItems[$id]['row']];
            $this->bankUsage[$id] = ($this->bankUsage[$id] ?? 0) + 1;
        }

        $order = [];

        foreach ($picked as $index => $entry) {
            $order[$index] = self::DIFFICULTY_ORDER[$entry['question']['difficulty']] + $this->random->float();
        }

        asort($order);

        return array_map(fn (int $index): array => $picked[$index], array_keys($order));
    }

    /**
     * Determine whether a catalog question is about the subject and grade of a quiz group.
     *
     * @param  array{module: string}  $question
     * @param  array{subject: string, grade: int}  $group
     */
    private function belongsToGroup(array $question, array $group): bool
    {
        $module = ContentCatalog::moduleByCode($question['module']);

        return $module['subject'] === $group['subject'] && $module['grade'] === $group['grade'];
    }

    /**
     * Build the question rows of a quiz; bank copies keep the bank item's stored content.
     *
     * @param  list<array{question: array<string, mixed>, bank_item: object|null}>  $questions
     * @return list<array<string, mixed>>
     */
    private function questionRows(array $questions, int $quizCreatedAt): array
    {
        $rows = [];

        foreach ($questions as $position => $entry) {
            $item = $entry['bank_item'];

            if ($item === null) {
                $content = QuestionCatalog::content($entry['question']);
                $content['choices'] = $content['choices'] === null ? null : json_encode($content['choices']);
            } else {
                $content = [
                    'type' => $item->type,
                    'prompt' => $item->prompt,
                    'choices' => $item->choices,
                    'correct_answer' => $item->correct_answer,
                    'explanation' => $item->explanation,
                ];
            }

            $moment = DemoClock::format($quizCreatedAt + ($position + 1) * 60);

            $rows[] = [
                'question_bank_item_id' => $item?->id,
                'type' => $content['type'],
                'prompt' => $content['prompt'],
                'choices' => $content['choices'],
                'correct_answer' => $content['correct_answer'],
                'explanation' => $content['explanation'],
                'image_path' => null,
                'video_path' => null,
                'video_url' => null,
                'points' => QuestionCatalog::pointsFor($entry['question']),
                'sort_order' => $position + 1,
                'created_at' => $moment,
                'updated_at' => $moment,
            ];
        }

        return $rows;
    }

    /**
     * Write the quiz instructions.
     */
    private function instructions(int $questionCount, int $minutes, ?string $topic): string
    {
        $templates = $topic === null ? [0, 1, 3] : [2, 0, 1];

        return strtr(QuizCatalog::INSTRUCTIONS[$this->random->pick($templates)], [
            ':count' => (string) $questionCount,
            ':minutes' => (string) $minutes,
            ':topic' => (string) $topic,
        ]);
    }

    /**
     * Get the ID of the quarter a date belongs to: the last quarter that started on or before it (a date between two
     * quarters belongs to the one that just ended), or null before the school year.
     *
     * @param  list<AcademicTerm>  $terms
     */
    private function termOf(array $terms, string $date): ?int
    {
        $termId = null;

        foreach ($terms as $term) {
            if ($term->starts_on->toDateString() <= $date) {
                $termId = $term->id;
            }
        }

        return $termId;
    }

    /**
     * Insert the planned questions and classroom assignments, then record how often each bank item was used.
     */
    private function seedQuestionsAndAssignments(): void
    {
        $quizIds = [];

        foreach (Quiz::query()->toBase()->get(['id', 'teacher_id', 'title']) as $quiz) {
            $quizIds[$quiz->teacher_id.'|'.$quiz->title] = (int) $quiz->id;
        }

        $questions = [];

        foreach ($this->questions as $key => $rows) {
            foreach ($rows as $row) {
                $questions[] = ['quiz_id' => $quizIds[$key], ...$row];
            }
        }

        BulkInsert::models(QuizQuestion::class, $questions);

        $assignments = [];

        foreach ($this->assignments as $key => $rows) {
            foreach ($rows as $row) {
                $assignments[] = ['quiz_id' => $quizIds[$key], ...$row];
            }
        }

        BulkInsert::pivot((new Quiz)->classrooms(), $assignments);

        foreach ($this->bankUsage as $itemId => $timesUsed) {
            QuestionBankItem::query()->whereKey($itemId)->toBase()->update(['times_used' => $timesUsed]);
        }
    }
}
