<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\CsvExport;
use App\Services\StudentImport\ImportColumns;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportTemplateController extends Controller
{
    /**
     * Download the learner CSV template: the column headings and two example rows.
     */
    #[Authorize(Permission::ImportStudents)]
    public function __invoke(CsvExport $csv): StreamedResponse
    {
        return $csv->download('learner-import-template', ImportColumns::TEMPLATE_HEADERS, ImportColumns::TEMPLATE_EXAMPLE_ROWS);
    }
}
