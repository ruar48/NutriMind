<?php

use App\Http\Controllers\Admin\AcademicTermActivationController;
use App\Http\Controllers\Admin\AcademicTermController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SchoolYearActivationController;
use App\Http\Controllers\Admin\SchoolYearController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubjectAssignmentController;
use App\Http\Controllers\Admin\SubjectController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /admin group (auth, role:admin, names "admin."). Owner: P2-Setup.
*/

Route::resource('school-years', SchoolYearController::class);
Route::put('/school-years/{school_year}/activation', [SchoolYearActivationController::class, 'update'])->name('school-years.activation.update');

Route::resource('school-years.terms', AcademicTermController::class)
    ->shallow()
    ->only(['create', 'store', 'edit', 'update', 'destroy']);
Route::put('/terms/{term}/activation', [AcademicTermActivationController::class, 'update'])->name('terms.activation.update');

Route::resource('subjects', SubjectController::class)->except(['show']);

Route::get('/subject-assignments', [SubjectAssignmentController::class, 'index'])->name('subject-assignments.index');
Route::put('/subject-assignments/{teacher}', [SubjectAssignmentController::class, 'update'])->name('subject-assignments.update');

Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

Route::get('/roles', [RolePermissionController::class, 'index'])->name('roles.index');
Route::put('/roles', [RolePermissionController::class, 'update'])->name('roles.update');
