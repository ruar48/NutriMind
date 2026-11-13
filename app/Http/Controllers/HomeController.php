<?php

namespace App\Http\Controllers;

use App\Services\Settings;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the public landing page.
     */
    public function __invoke(Settings $settings): View
    {
        return view('welcome', [
            'schoolName' => $settings->get('school_name'),
        ]);
    }
}
