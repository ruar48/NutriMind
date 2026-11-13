<?php

use App\Enums\GradeLevel;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentImport\ImportNotCommitted;
use App\Services\StudentImport\ImportRowStatus;
use App\Services\StudentImport\PendingImports;
use App\Services\StudentImport\StudentImports;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Services\StudentImport\Support\StudentImportCsv;

it('reports the problems of an uploaded file', function () {
    expect(app(StudentImports::class)->problemsWith(StudentImportCsv::upload(''), gradeFromClassroom: true))
        ->toBe(['The file is empty. Add a heading row and one learner per row.'])
        ->and(app(StudentImports::class)->problemsWith(StudentImportCsv::upload("first_name,last_name\nJuan,Dela Cruz\n"), gradeFromClassroom: true))
        ->toBe([]);
});

it('imports nothing and keeps the pending import when the data changes during the commit', function () {
    Storage::fake('local');
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $intruder = User::factory()->studentWithoutProfile()->create();
    $pending = StudentImportCsv::pending($classroom->teacher, [
        StudentImportCsv::row(['first_name' => 'Ana', 'lrn' => '136742210001']),
        StudentImportCsv::row(['first_name' => 'Ben', 'lrn' => '136742210002']),
    ], $classroom);
    StudentProfile::creating(function (StudentProfile $profile) use ($intruder): void {
        if ($profile->lrn === '136742210002' && ! DB::table('student_profiles')->where('lrn', '136742210002')->exists()) {
            DB::table('student_profiles')->insert([
                'user_id' => $intruder->id, 'lrn' => '136742210002', 'grade_level' => 5, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    });

    expect(fn () => app(StudentImports::class)->commit($pending, $classroom, $classroom->teacher))
        ->toThrow(ImportNotCommitted::class, 'Nothing was imported because some learners or parents changed in the meantime. Review the preview and import again.');

    expect(User::query()->where('name', 'Ana Dela Cruz')->exists())->toBeFalse()
        ->and(app(PendingImports::class)->find($pending->id, $classroom->teacher))->not->toBeNull();
    Storage::disk('local')->assertExists($pending->path);
});

it('commits once and keeps the sign-in details for the importer', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();
    $pending = StudentImportCsv::pending($admin, [StudentImportCsv::row()]);

    $result = app(StudentImports::class)->commit($pending, null, $admin);

    expect($result->created)->toBe(1)
        ->and(app(PendingImports::class)->pullCredentials($admin))->toHaveCount(1)
        ->and(User::query()->ofRole(UserRole::Student)->count())->toBe(1);
    Storage::disk('local')->assertMissing($pending->path);
});

it('pages the preview rows 25 at a time and clamps pages past the end', function () {
    $preview = StudentImportCsv::preview(array_map(
        fn (int $index): array => StudentImportCsv::row(['first_name' => 'Learner'.$index, 'username' => 'learner'.$index]),
        range(1, 27),
    ));
    request()->query->set('page', '9');

    $page = app(StudentImports::class)->paginateRows($preview, null);

    expect($page->currentPage())->toBe(2)
        ->and($page->total())->toBe(27)
        ->and(collect($page->items())->map(fn ($row): int => $row->number())->all())->toBe([27, 28])
        ->and(app(StudentImports::class)->paginateRows($preview, ImportRowStatus::Error)->total())->toBe(0);
});
