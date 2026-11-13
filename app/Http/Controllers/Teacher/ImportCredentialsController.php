<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\CsvExport;
use App\Services\StudentImport\ImportResult;
use App\Services\StudentImport\PendingImports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportCredentialsController extends Controller
{
    /**
     * Download the sign-in details of the accounts the teacher's last import created; works once.
     */
    #[Authorize(Permission::ImportStudents)]
    public function __invoke(Request $request, PendingImports $pending, CsvExport $csv): StreamedResponse|RedirectResponse
    {
        $credentials = $pending->pullCredentials($request->user());

        if ($credentials === null) {
            return redirect()->back(fallback: route('teacher.classrooms.index'))
                ->with('error', PendingImports::NO_CREDENTIALS_MESSAGE);
        }

        return $csv->download(
            'learner-sign-in-details-'.now()->format('Y-m-d'),
            ImportResult::CREDENTIALS_HEADINGS,
            ImportResult::credentialsCsvRows($credentials),
        );
    }
}
