<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubjectRequest;
use App\Http\Requests\Admin\SubjectFormRequest;
use App\Http\Requests\Admin\UpdateSubjectRequest;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Subject configuration (administrator only, see SchoolYearController).
 */
#[Middleware('role:admin')]
class SubjectController extends Controller
{
    /**
     * The records that keep a subject from being deleted: relation name => [singular, plural].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const USAGE = [
        'classrooms' => ['classroom', 'classrooms'],
        'learningModules' => ['learning module', 'learning modules'],
        'missions' => ['mission', 'missions'],
        'quizzes' => ['quiz', 'quizzes'],
        'questionBankItems' => ['question bank item', 'question bank items'],
        'lessonPlans' => ['lesson plan', 'lesson plans'],
    ];

    /**
     * The status filters of the list, keyed by query value.
     *
     * @var array<string, string>
     */
    public const STATUS_FILTERS = [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    /**
     * List the subjects as cards with their usage counts.
     */
    public function index(Request $request): View
    {
        $search = $this->textFilter($request, 'search');
        $status = array_key_exists($this->textFilter($request, 'status'), self::STATUS_FILTERS) ? $this->textFilter($request, 'status') : null;

        $subjects = Subject::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', '%'.$search.'%')
                ->orWhereLike('code', '%'.$search.'%')))
            ->when($status !== null, fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->withCount(['teachers', ...array_keys(self::USAGE)])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return view('admin.subjects.index', [
            'subjects' => $subjects,
            'status' => $status,
            'statusOptions' => self::STATUS_FILTERS,
            'isFiltered' => $search !== '' || $status !== null,
            'totalCount' => Subject::query()->count(),
            'activeCount' => Subject::query()->active()->count(),
        ]);
    }

    /**
     * Show the form for a new subject.
     */
    public function create(): View
    {
        return view('admin.subjects.form', [
            'subject' => new Subject,
            'icons' => SubjectFormRequest::ICONS,
            'colors' => SubjectFormRequest::COLORS,
        ]);
    }

    /**
     * Create a subject.
     */
    public function store(StoreSubjectRequest $request): RedirectResponse
    {
        $subject = Subject::query()->create([
            ...$request->safe()->only(['name', 'code', 'description', 'color', 'icon']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.subjects.index')->with('status', "{$subject->name} was added.");
    }

    /**
     * Show the form for editing a subject.
     */
    public function edit(Subject $subject): View
    {
        $subject->loadCount(['teachers', ...array_keys(self::USAGE)]);

        return view('admin.subjects.form', [
            'subject' => $subject,
            'icons' => SubjectFormRequest::ICONS,
            'colors' => SubjectFormRequest::COLORS,
        ]);
    }

    /**
     * Update a subject.
     */
    public function update(UpdateSubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update([
            ...$request->safe()->only(['name', 'code', 'description', 'color', 'icon']),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.subjects.index')->with('status', "{$subject->name} was updated.");
    }

    /**
     * Delete an unused subject (its teacher assignments cascade); a subject in use must be deactivated instead.
     */
    public function destroy(Subject $subject): RedirectResponse
    {
        $subject->loadCount(array_keys(self::USAGE));

        $usage = collect(self::USAGE)
            ->map(fn (array $nouns, string $relation): int => (int) $subject->getAttribute(Str::snake($relation).'_count'))
            ->filter()
            ->map(fn (int $count, string $relation): string => $count.' '.(self::USAGE[$relation][$count === 1 ? 0 : 1]));

        if ($usage->isNotEmpty()) {
            return redirect()->back(fallback: route('admin.subjects.index'))
                ->with('error', "{$subject->name} is used by ".Arr::join($usage->values()->all(), ', ', ' and ').'. Deactivate it instead.');
        }

        $subject->delete();

        return redirect()->route('admin.subjects.index')->with('status', "{$subject->name} was deleted.");
    }

    /**
     * Read a query-string filter as squished text, or an empty string when it is missing or not text.
     */
    private function textFilter(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? Str::squish($value) : '';
    }
}
