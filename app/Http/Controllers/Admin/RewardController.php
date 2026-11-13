<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RewardType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRewardRequest;
use App\Http\Requests\Admin\UpdateRewardRequest;
use App\Models\Reward;
use App\Services\Content\ListFilter;
use App\Services\Content\RewardAppearance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administrator › Rewards: the badges, trophies and avatar items learners earn in the game.
 *
 * Admin-only: the route group applies role:admin (administrators manage content without a policy).
 */
class RewardController extends Controller
{
    /**
     * Reward cards per page.
     */
    private const int PER_PAGE = 18;

    /**
     * Shown when a reward cannot be deleted because learners earned it.
     */
    public const string AWARDED_MESSAGE = 'Learners have already earned this reward. Deactivate it instead.';

    /**
     * List the rewards as cards with filters (type, active state, search).
     */
    public function index(Request $request): View
    {
        $filters = [
            'type' => ListFilter::enum($request, 'type', RewardType::class),
            'status' => ListFilter::choice($request, 'status', ['active', 'inactive']),
            'search' => ListFilter::search($request),
        ];

        $rewards = Reward::query()
            ->withCount('students')
            ->when($filters['type'], fn (Builder $query, RewardType $type) => $query->where('type', $type))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('name', "%{$search}%")->orWhereLike('criteria', "%{$search}%");
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.rewards.index', [
            'rewards' => $rewards,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for a new reward.
     */
    public function create(): View
    {
        return view('admin.rewards.form', [
            'reward' => new Reward(['type' => RewardType::Badge, 'icon' => 'award', 'color' => 'sun', 'is_active' => true]),
            'icons' => RewardAppearance::ICONS,
        ]);
    }

    /**
     * Store a new reward.
     */
    public function store(StoreRewardRequest $request): RedirectResponse
    {
        $reward = Reward::query()->create($request->validated());

        return redirect()->route('admin.rewards.index')->with('status', "Reward “{$reward->name}” created.");
    }

    /**
     * Show the form for editing a reward.
     */
    public function edit(Reward $reward): View
    {
        $reward->loadCount('students');

        return view('admin.rewards.form', [
            'reward' => $reward,
            'icons' => array_values(array_unique([...RewardAppearance::ICONS, $reward->icon])),
        ]);
    }

    /**
     * Update a reward.
     */
    public function update(UpdateRewardRequest $request, Reward $reward): RedirectResponse
    {
        $reward->update($request->validated());

        return redirect()->route('admin.rewards.index')->with('status', 'Reward updated.');
    }

    /**
     * Delete a reward, unless learners already earned it.
     */
    public function destroy(Reward $reward): RedirectResponse
    {
        if ($reward->students()->exists()) {
            return redirect()->route('admin.rewards.index')->with('error', self::AWARDED_MESSAGE);
        }

        $reward->delete();

        return redirect()->route('admin.rewards.index')->with('status', "Reward “{$reward->name}” deleted.");
    }
}
