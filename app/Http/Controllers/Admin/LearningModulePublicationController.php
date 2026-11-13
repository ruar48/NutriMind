<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Administrator › Learning modules › Publish or unpublish a module in one click.
 *
 * Admin-only: the route group applies role:admin (administrators manage content without a policy).
 */
class LearningModulePublicationController extends Controller
{
    /**
     * Publish or unpublish the module.
     */
    public function update(Request $request, LearningModule $module): RedirectResponse
    {
        $validated = $request->validate(
            ['is_published' => ['required', 'boolean']],
            [],
            ['is_published' => 'published setting'],
        );

        $module->update(['is_published' => (bool) $validated['is_published']]);

        $status = $module->is_published
            ? "Module “{$module->title}” published. Learners can play its published activities."
            : "Module “{$module->title}” unpublished. Learners no longer see it in the game.";

        return redirect()->back(fallback: route('admin.modules.show', $module))->with('status', $status);
    }
}
