<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\StoreQuizAttemptRequest;
use App\Http\Resources\Game\ProfileResource;
use App\Http\Resources\Game\QuizAttemptResource;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Game\QuizSubmission;
use Illuminate\Http\JsonResponse;

class QuizAttemptController extends Controller
{
    /**
     * Grade the answers the learner gave in the game and store them as a submitted attempt.
     *
     * Responds 201 with the graded attempt and the learner's updated profile (points and level).
     */
    public function store(StoreQuizAttemptRequest $request, Quiz $gameQuiz, QuizSubmission $submission): JsonResponse
    {
        /** @var User $student */
        $student = $request->user();

        $attempt = $submission->submit(
            $student,
            $gameQuiz,
            $request->answersByQuestion(),
            startedAt: $request->startedAt(),
            durationSeconds: $request->validated('duration_seconds'),
            submittedAt: $request->submittedAt(),
            isOffline: $request->boolean('is_offline'),
        );

        return (new QuizAttemptResource($attempt))
            ->additional(['student' => new ProfileResource($student->fresh())])
            ->response()
            ->setStatusCode(201);
    }
}
