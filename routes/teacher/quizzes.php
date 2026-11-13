<?php

use App\Http\Controllers\Teacher\QuestionBankController;
use App\Http\Controllers\Teacher\QuestionBankDuplicateController;
use App\Http\Controllers\Teacher\QuestionBankSaveController;
use App\Http\Controllers\Teacher\QuizBankQuestionController;
use App\Http\Controllers\Teacher\QuizController;
use App\Http\Controllers\Teacher\QuizDuplicateController;
use App\Http\Controllers\Teacher\QuizQuestionController;
use App\Http\Controllers\Teacher\QuizQuestionPositionController;
use App\Http\Controllers\Teacher\QuizReviewController;
use App\Http\Controllers\Teacher\QuizStatusController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /teacher group (auth, role:teacher, names "teacher."). Owner: P2-Quizzes.
| {question} is scoped to {quiz} through Quiz::questions(): a question of another quiz is 404.
| The question bank resource uses the parameter {item} (QuestionBankItem).
*/

Route::resource('quizzes', QuizController::class);

Route::resource('quizzes.questions', QuizQuestionController::class)
    ->scoped()
    ->except(['index', 'show']);
Route::put('/quizzes/{quiz}/questions/{question}/position', [QuizQuestionPositionController::class, 'update'])
    ->scopeBindings()
    ->name('quizzes.questions.position.update');
Route::post('/quizzes/{quiz}/questions/{question}/bank', [QuestionBankSaveController::class, 'store'])
    ->scopeBindings()
    ->name('quizzes.questions.bank.store');

Route::get('/quizzes/{quiz}/review', [QuizReviewController::class, 'show'])->name('quizzes.review.show');
Route::post('/quizzes/{quiz}/review', [QuizReviewController::class, 'store'])->name('quizzes.review.store');
Route::put('/quizzes/{quiz}/status', [QuizStatusController::class, 'update'])->name('quizzes.status.update');
Route::post('/quizzes/{quiz}/duplicate', [QuizDuplicateController::class, 'store'])->name('quizzes.duplicate.store');
Route::get('/quizzes/{quiz}/bank-questions', [QuizBankQuestionController::class, 'create'])->name('quizzes.bank-questions.create');
Route::post('/quizzes/{quiz}/bank-questions', [QuizBankQuestionController::class, 'store'])->name('quizzes.bank-questions.store');

Route::resource('question-bank', QuestionBankController::class)->parameters(['question-bank' => 'item']);
Route::post('/question-bank/{item}/duplicate', [QuestionBankDuplicateController::class, 'store'])->name('question-bank.duplicate.store');
