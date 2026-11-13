<?php

namespace App\Http\Controllers\Admin;

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
     * The message shown when there is nothing (left) to download.
     */
    public const NOTHING_TO_DOWNLOAD_MESSAGE = 'There are no sign-in details to download. They can be downloaded once, right after an import.';

    /**
     * Download the sign-in details of the accounts the last import created; works once.
     */
    #[Authorize(Permission::ImportStudents)]
    public function __invoke(Request $request, PendingImports $pending, CsvExport $csv): StreamedResponse|RedirectResponse
    {
        $credentials = $pending->pullCredentials($request->user());

        if ($credentials === null) {
            return redirect()->back(fallback: route('admin.students.import.create'))
                ->with('error', self::NOTHING_TO_DOWNLOAD_MESSAGE);
        }

        return $csv->download(
            'learner-sign-in-details-'.now()->format('Y-m-d'),
            ImportResult::CREDENTIALS_HEADINGS,
            ImportResult::credentialsCsvRows($credentials),
        );
    }
}
