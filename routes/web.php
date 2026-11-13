<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PortalSelectionController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', PortalSelectionController::class)->name('login');
    Route::get('/login/{portal}', [LoginController::class, 'create'])->whereIn('portal', ['admin', 'teacher', 'parent'])->name('login.portal');
    Route::post('/login/{portal}', [LoginController::class, 'store'])->whereIn('portal', ['admin', 'teacher', 'parent'])->name('login.portal.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardRedirectController::class)->name('dashboard');

    Route::middleware('role:admin,teacher,parent')->group(function () {
        Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('/account', [AccountController::class, 'update'])->name('account.update');
        Route::put('/account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        require __DIR__.'/admin/insights.php';
        require __DIR__.'/admin/setup.php';
        require __DIR__.'/admin/people.php';
        require __DIR__.'/admin/reports.php';
        require __DIR__.'/admin/content.php';
        require __DIR__.'/admin/forum.php';
    });

    Route::middleware('role:teacher')->prefix('teacher')->name('teacher.')->group(function () {
        require __DIR__.'/teacher/insights.php';
        require __DIR__.'/teacher/classrooms.php';
        require __DIR__.'/teacher/imports.php';
        require __DIR__.'/teacher/quizzes.php';
        require __DIR__.'/teacher/monitoring.php';
        require __DIR__.'/teacher/reports.php';
        require __DIR__.'/teacher/lesson-plans.php';
    });

    Route::middleware('role:parent')->prefix('parent')->name('parent.')->group(fn () => require __DIR__.'/parent.php');

    Route::middleware('role:admin,teacher,parent')->prefix('forum')->name('forum.')->group(fn () => require __DIR__.'/forum.php');
});
