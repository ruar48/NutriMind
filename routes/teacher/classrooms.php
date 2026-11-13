<?php

use App\Http\Controllers\Teacher\ClassroomArchiveController;
use App\Http\Controllers\Teacher\ClassroomController;
use App\Http\Controllers\Teacher\ClassroomStudentController;
use App\Http\Controllers\Teacher\EnrollmentController;
use App\Http\Controllers\Teacher\StudentPasswordController;
use App\Http\Controllers\Teacher\SubjectController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /teacher group (auth, role:teacher, names "teacher."). Owner: P2-Classrooms.
| {student} resolves only learner accounts (AppServiceProvider); enrollment in {classroom} is checked by the
| controller or policy, not by the binding.
*/

Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');

Route::resource('classrooms', ClassroomController::class);

Route::post('/classrooms/{classroom}/archive', [ClassroomArchiveController::class, 'store'])->name('classrooms.archive.store');
Route::delete('/classrooms/{classroom}/archive', [ClassroomArchiveController::class, 'destroy'])->name('classrooms.archive.destroy');

Route::get('/classrooms/{classroom}/students/create', [ClassroomStudentController::class, 'create'])->name('classrooms.students.create');
Route::post('/classrooms/{classroom}/students', [ClassroomStudentController::class, 'store'])->name('classrooms.students.store');
Route::delete('/classrooms/{classroom}/students/{student}', [ClassroomStudentController::class, 'destroy'])->name('classrooms.students.destroy');
Route::post('/classrooms/{classroom}/enrollments', [EnrollmentController::class, 'store'])->name('classrooms.enrollments.store');
Route::put('/classrooms/{classroom}/students/{student}/password', [StudentPasswordController::class, 'update'])->name('classrooms.students.password.update');
