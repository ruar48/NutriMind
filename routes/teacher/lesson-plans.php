<?php

use App\Http\Controllers\Teacher\LessonPlanAlignmentController;
use App\Http\Controllers\Teacher\LessonPlanController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /teacher group (auth, role:teacher, names "teacher."). Owner: P2-Content.
*/

Route::resource('lesson-plans', LessonPlanController::class);
Route::put('/lesson-plans/{lesson_plan}/alignment', [LessonPlanAlignmentController::class, 'update'])->name('lesson-plans.alignment.update');
