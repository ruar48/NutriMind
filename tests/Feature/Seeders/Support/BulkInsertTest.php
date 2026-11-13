<?php

use App\Models\Setting;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\Support\BulkInsert;

it('inserts more rows than fit in one statement', function () {
    $rows = array_map(fn (int $index): array => [
        'key' => "demo_setting_{$index}",
        'value' => json_encode($index),
        'created_at' => '2026-11-13 10:00:00',
        'updated_at' => '2026-11-13 10:00:00',
    ], range(1, 850));

    BulkInsert::models(Setting::class, $rows);

    $this->assertDatabaseCount('settings', 850);
    $this->assertDatabaseHas('settings', ['key' => 'demo_setting_850', 'value' => '850']);
});

it('inserts rows into the pivot table of a relationship', function () {
    $teacher = User::factory()->teacher()->create();
    $subjects = Subject::factory()->count(2)->create();

    BulkInsert::pivot((new User)->assignedSubjects(), $subjects->map(fn (Subject $subject): array => [
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'assigned_by' => null,
        'created_at' => '2026-05-18 10:00:00',
        'updated_at' => '2026-05-18 10:00:00',
    ])->all());

    expect($teacher->assignedSubjects()->pluck('subjects.id')->all())->toEqualCanonicalizing($subjects->modelKeys());
});
