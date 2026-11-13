<?php

namespace App\Services\Reports\Types;

use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Reports\ColumnType;
use App\Services\Reports\Report;
use App\Services\Reports\ReportColumn;
use App\Services\Reports\ReportRow;
use App\Services\Reports\ReportScope;
use App\Services\Reports\ReportStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * User accounts (administrators only): one row per account of any role, filtered by role, status and the
 * account creation date.
 */
final class UserAccountsReport
{
    /**
     * Build the report for the scope.
     */
    public function build(ReportScope $scope): Report
    {
        $filters = $scope->filters;

        $query = User::query()
            ->when($filters->role, fn (Builder $query, UserRole $role) => $query->ofRole($role))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->orderBy('id');

        $scope->applyPeriod($query, 'created_at');

        $rows = $query
            ->get(['id', 'name', 'username', 'email', 'role', 'is_active', 'last_login_at', 'created_at'])
            ->map(fn (User $user): ReportRow => new ReportRow(
                cells: [
                    'name' => $user->name,
                    'role' => $user->role,
                    'login' => $user->email ?? $user->username,
                    'status' => $user->is_active,
                    'last_login' => $user->last_login_at,
                    'created' => $user->created_at,
                ],
                url: $this->profileUrl($user),
            ))
            ->values();

        return new Report(
            type: ReportType::UserAccounts,
            title: ReportType::UserAccounts->label(),
            description: ReportType::UserAccounts->description(),
            filters: $scope->describeAccounts(),
            summary: $this->summary($rows),
            columns: [
                new ReportColumn('name', 'Name'),
                new ReportColumn('role', 'Role', ColumnType::Badge),
                new ReportColumn('login', 'Email / username'),
                new ReportColumn('status', 'Status', ColumnType::Status),
                new ReportColumn('last_login', 'Last sign-in', ColumnType::DateTime, 'Never'),
                new ReportColumn('created', 'Created', ColumnType::Date),
            ],
            rows: $rows,
            notes: array_values(array_filter([
                'Learners play in the NutriMind game and never sign in to the web portals, so their last sign-in stays empty.',
                $filters->hasPeriod() ? 'Only accounts created in the period are listed.' : null,
            ])),
            breakdown: [
                'title' => 'Accounts by role',
                'description' => 'Share of each role among the listed accounts',
                'segments' => array_map(fn (UserRole $role, int $slot): array => [
                    'label' => $role->label(),
                    'value' => $rows->filter(fn (ReportRow $row): bool => $row->value('role') === $role)->count(),
                    'slot' => $slot + 1,
                ], UserRole::cases(), array_keys(UserRole::cases())),
            ],
        );
    }

    /**
     * Get the administrator page of the account.
     */
    private function profileUrl(User $user): string
    {
        return match ($user->role) {
            UserRole::Teacher => route('admin.teachers.show', $user),
            UserRole::Parent => route('admin.parents.show', $user),
            UserRole::Student => route('admin.students.show', $user),
            UserRole::Admin => route('admin.users.edit', $user),
        };
    }

    /**
     * Build the summary tiles.
     *
     * @param  Collection<int, ReportRow>  $rows
     * @return list<ReportStat>
     */
    private function summary(Collection $rows): array
    {
        $active = $rows->filter(fn (ReportRow $row): bool => $row->value('status') === true)->count();
        $neverSignedIn = $rows
            ->filter(fn (ReportRow $row): bool => $row->value('role')?->canAccessWeb() === true && $row->value('last_login') === null)
            ->count();

        return [
            ReportStat::count('Accounts', $rows->count(), 'users', 'indigo', 'Every listed account'),
            ReportStat::count('Active', $active, 'user-check', 'leaf', 'Can sign in'),
            ReportStat::count('Inactive', $rows->count() - $active, 'ban', 'slate', 'Deactivated accounts'),
            ReportStat::count('Never signed in', $neverSignedIn, 'clock', 'sun', 'Staff and parent accounts'),
        ];
    }
}
