<?php

namespace App\View\Composers;

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\ForumThread;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Shares the portal shell data with the layout: the portal badge, school name, active school year and term,
 * announcement banner and global search target.
 */
class LayoutComposer
{
    /**
     * The request attribute that memoizes the active school year and term for the rest of the request.
     */
    private const ACADEMIC_PERIOD_ATTRIBUTE = 'nutrimind.layout.academic_period';

    public function __construct(
        private readonly Guard $auth,
        private readonly Request $request,
        private readonly Settings $settings,
    ) {}

    /**
     * Share the layout data with the view.
     */
    public function compose(View $view): void
    {
        $user = $this->auth->user();
        $role = $user instanceof User && $user->role instanceof UserRole ? $user->role : null;
        [$activeSchoolYear, $activeTerm] = $this->academicPeriod();
        $announcement = $this->settings->get('announcement');

        $data = [
            'schoolName' => (string) $this->settings->get('school_name'),
            'activeSchoolYear' => $activeSchoolYear,
            'activeTerm' => $activeTerm,
            'announcement' => is_string($announcement) && filled($announcement) ? trim($announcement) : null,
            'searchUrl' => null,
            'searchPlaceholder' => 'Search…',
        ];

        if ($role !== null) {
            $data['portal'] = ['key' => $role->value, 'label' => $role->portalLabel(), 'color' => $role->color()];
            $data = [...$data, ...$this->search($user, $role)];
        }

        $view->with($data);
    }

    /**
     * Get the active school year and its active term, looked up once per request.
     *
     * @return array{0: SchoolYear|null, 1: AcademicTerm|null}
     */
    private function academicPeriod(): array
    {
        $attributes = $this->request->attributes;

        if (! $attributes->has(self::ACADEMIC_PERIOD_ATTRIBUTE)) {
            $schoolYear = SchoolYear::current();

            $term = $schoolYear === null ? null : AcademicTerm::query()
                ->active()
                ->whereBelongsTo($schoolYear)
                ->orderByDesc('starts_on')
                ->orderByDesc('id')
                ->first()
                ?->setRelation('schoolYear', $schoolYear);

            $attributes->set(self::ACADEMIC_PERIOD_ATTRIBUTE, [$schoolYear, $term]);
        }

        return $attributes->get(self::ACADEMIC_PERIOD_ATTRIBUTE);
    }

    /**
     * Get the global search target of the user's portal; none when the user cannot open it.
     *
     * @return array{searchUrl?: string, searchPlaceholder?: string}
     */
    private function search(User $user, UserRole $role): array
    {
        [$routeName, $placeholder] = match ($role) {
            UserRole::Admin => ['admin.users.index', 'Search users by name, email or username'],
            UserRole::Teacher => ['teacher.students.index', 'Search learners'],
            UserRole::Parent => ['forum.index', 'Search the parent forum'],
            UserRole::Student => [null, null],
        };

        if ($routeName === null || ! Route::has($routeName)) {
            return [];
        }

        if ($role === UserRole::Parent && $user->cannot('viewAny', ForumThread::class)) {
            return [];
        }

        return ['searchUrl' => route($routeName), 'searchPlaceholder' => $placeholder];
    }
}
