<?php

use App\Http\Controllers\Teacher\ReportController;
use App\Http\Controllers\Teacher\ReportExportController;
use App\Http\Controllers\Teacher\ReportPrintController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /teacher group (auth, role:teacher, names "teacher."). Owner: P2-Reports.
| {report} is a ReportType value: type-hint `ReportType $report` for implicit enum binding (unknown values are 404).
| Invokable controllers use [Controller::class, '__invoke'] (see insights.php).
*/

Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
Route::get('/reports/{report}/export', [ReportExportController::class, '__invoke'])->name('reports.export');
Route::get('/reports/{report}/print', [ReportPrintController::class, '__invoke'])->name('reports.print');
