<?php

use App\Models\StudentProfile;
use App\Models\User;
use App\Services\LearnerProgressService;
use Carbon\CarbonImmutable;

it('flags the game sync as overdue only after more than seven days', function (?string $lastActiveAt, ?string $lastSyncedAt, bool $overdue) {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00:00'));
    $student = User::factory()->studentWithoutProfile()->create();
    StudentProfile::factory()->for($student, 'user')->create([
        'last_active_at' => $lastActiveAt,
        'last_synced_at' => $lastSyncedAt,
    ]);

    $summary = app(LearnerProgressService::class)->summarize($student);

    expect($summary->isSyncOverdue())->toBe($overdue);
})->with([
    'synced an hour ago' => ['2026-11-13 08:55:00', '2026-11-13 09:00:00', false],
    'synced exactly seven days ago' => ['2026-11-06 09:00:00', '2026-11-06 10:00:00', false],
    'synced seven days and one second ago' => ['2026-11-06 09:00:00', '2026-11-06 09:59:59', true],
    'played but never synced' => ['2026-11-12 09:00:00', null, true],
    'never played' => [null, null, false],
]);
