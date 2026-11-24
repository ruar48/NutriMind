<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use App\Http\Resources\Game\QuizResource;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuizController extends Controller
{
    /**
     * List the quizzes the learner may take (published, assigned to one of their classrooms and opened),
     * newest first, without their questions.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $student */
        $student = $request->user();

        $quizzes = Quiz::query()
            ->availableTo($student)
            ->with(['subject', 'classrooms' => fn (BelongsToMany $classrooms) => Quiz::constrainToOpenAssignments($classrooms->getQuery(), $student)])
            ->withCount($this->counts($student))
            ->latest('published_at')
            ->latest('id')
            ->get();

        return QuizResource::collection($quizzes);
    }

    /**
     * Show one available quiz with its questions (never their correct answers), shuffled when the teacher asked
     * for it. A quiz the learner may not take answers 404, through the route binding.
     */
    public function show(Request $request, Quiz $gameQuiz): QuizResource
    {
        /** @var User $student */
        $student = $request->user();

        $gameQuiz->load(['subject', 'questions'])
            ->loadCount($this->counts($student))
            ->setRelation('classrooms', $gameQuiz->classroomsOpenTo($student)->get());

        if ($gameQuiz->shuffle_questions) {
            $gameQuiz->setRelation('questions', $gameQuiz->questions->shuffle()->values());
        }

        return new QuizResource($gameQuiz);
    }

    /**
     * Get the counts the quiz resource expects: the learner's submitted attempts and the questions.
     *
     * @return array<string|int, mixed>
     */
    private function counts(User $student): array
    {
        return [
            'questions',
            'attempts as attempts_used' => fn (Builder $attempts) => $attempts->where('student_id', $student->getKey())->whereNotNull('submitted_at'),
        ];
    }
}
