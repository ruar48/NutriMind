<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Admin\ReportPrintController;
use Illuminate\Support\Facades\Route;

/*
| Loaded inside the /admin group (auth, role:admin, names "admin."). Owner: P2-Reports.
| {report} is a ReportType value: type-hint `ReportType $report` for implicit enum binding (unknown values are 404).
| Invokable controllers use [Controller::class, '__invoke'] (see insights.php).
*/

Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
Route::get('/reports/{report}/export', [ReportExportController::class, '__invoke'])->name('reports.export');
Route::get('/reports/{report}/print', [ReportPrintController::class, '__invoke'])->name('reports.print');
