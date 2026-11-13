<?php

namespace App\Http\Controllers\Teacher\Concerns;

use App\Models\Classroom;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Query-string filters shared by the teacher monitoring pages (P2-Monitoring).
 */
trait ReadsMonitoringFilters
{
    /**
     * Resolve the ?classroom= filter to one of the allowed classrooms. Blank means no filter; any other value (another
     * teacher's classroom, a revoked or archived one, garbage) is reported as missing.
     *
     * @param  Collection<int, Classroom>  $classrooms
     */
    protected function classroomFilter(Request $request, Collection $classrooms): ?Classroom
    {
        $value = $request->query('classroom');

        if ($value === null || $value === '') {
            return null;
        }

        $classroom = is_string($value) && ctype_digit($value) ? $classrooms->find((int) $value) : null;

        abort_if($classroom === null, 404);

        return $classroom;
    }

    /**
     * Read the ?search= term (null when blank or not a string).
     */
    protected function searchFilter(Request $request): ?string
    {
        $search = $request->query('search');

        return is_string($search) && trim($search) !== '' ? trim($search) : null;
    }

    /**
     * Read a whitelisted ?sort= key and its ?direction=.
     *
     * Without a sort key the default sort and its default direction apply. A sort key without a direction sorts
     * ascending, which is what the sortable table headers show for it.
     *
     * @param  array<string, string>  $sorts  sort key => default direction
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    protected function sortFilter(Request $request, array $sorts, string $default): array
    {
        $sort = $request->query('sort');

        if (! is_string($sort) || ! array_key_exists($sort, $sorts)) {
            return [$default, $sorts[$default] === 'desc' ? 'desc' : 'asc'];
        }

        return [$sort, $request->query('direction') === 'desc' ? 'desc' : 'asc'];
    }

    /**
     * Read a backed-enum filter (null when blank or not one of its values).
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum|null
     */
    protected function enumFilter(Request $request, string $key, string $enum): ?BackedEnum
    {
        $value = $request->query($key);

        return is_string($value) && $value !== '' ? $enum::tryFrom($value) : null;
    }
}
