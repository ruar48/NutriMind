<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Settings;
use Illuminate\View\View;

class PortalSelectionController extends Controller
{
    /**
     * Show the list of web portals to sign in to.
     */
    public function __invoke(Settings $settings): View
    {
        return view('auth.portals', [
            'schoolName' => $settings->get('school_name'),
        ]);
    }
}
