<?php

use App\Http\Controllers\Forum\CategoryController;
use App\Http\Controllers\Forum\ForumController;
use App\Http\Controllers\Forum\ReplyController;
use App\Http\Controllers\Forum\ReplyModerationController;
use App\Http\Controllers\Forum\ReportController;
use App\Http\Controllers\Forum\ThreadController;
use App\Http\Controllers\Forum\ThreadModerationController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /forum group (auth, role:admin,teacher,parent, names "forum."). Owner: P2-Community.
| /threads/create is registered before /threads/{thread} by the resource.
*/

Route::get('/', [ForumController::class, 'index'])->name('index');
Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');

Route::resource('threads', ThreadController::class)->except(['index']);
Route::resource('threads.replies', ReplyController::class)
    ->shallow()
    ->only(['store', 'edit', 'update', 'destroy']);

Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');

Route::put('/threads/{thread}/moderation', [ThreadModerationController::class, 'update'])->name('threads.moderation.update');
Route::put('/replies/{reply}/moderation', [ReplyModerationController::class, 'update'])->name('replies.moderation.update');
