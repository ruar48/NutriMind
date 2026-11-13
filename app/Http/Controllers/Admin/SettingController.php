<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LearningMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingRequest;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\View\View;

/**
 * System settings: school profile, academic defaults, gamification, parent forum and the announcement
 * (administrator only, see SchoolYearController).
 */
#[Middleware('role:admin')]
class SettingController extends Controller
{
    /**
     * Show the sectioned settings form with the current values.
     */
    public function edit(Settings $settings): View
    {
        $values = $settings->all();

        return view('admin.settings.edit', [
            'settings' => $values,
            'learningMode' => LearningMode::tryFrom((string) ($values['default_learning_mode'] ?? '')) ?? LearningMode::Automatic,
        ]);
    }

    /**
     * Save every setting of the form at once.
     */
    public function update(UpdateSettingRequest $request, Settings $settings): RedirectResponse
    {
        $settings->setMany($request->settings());

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }
}
