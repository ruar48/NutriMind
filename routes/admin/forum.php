<?php

use App\Http\Controllers\Admin\ForumApprovalController;
use App\Http\Controllers\Admin\ForumCategoryController;
use App\Http\Controllers\Admin\ForumReportController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /admin group (auth, role:admin, names "admin."). Owner: P2-Community.
| {forum_category} binds by slug (ForumCategory's route key).
*/

Route::get('/forum/reports', [ForumReportController::class, 'index'])->name('forum.reports.index');
Route::put('/forum/reports/{forum_report}', [ForumReportController::class, 'update'])->name('forum.reports.update');

Route::get('/forum/pending', [ForumApprovalController::class, 'index'])->name('forum.pending.index');
Route::put('/forum/pending/{thread}', [ForumApprovalController::class, 'update'])->name('forum.pending.update');

Route::resource('forum-categories', ForumCategoryController::class)->except(['show']);
