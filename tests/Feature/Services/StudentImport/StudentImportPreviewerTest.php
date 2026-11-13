<?php

use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Models\Classroom;
use App\Models\User;
use App\Services\StudentImport\GuardianAction;
use App\Services\StudentImport\ImportRowStatus;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Services\StudentImport\Support\StudentImportCsv;

it('creates learners that do not exist yet', function () {
    $preview = StudentImportCsv::preview([
        StudentImportCsv::row(['lrn' => '136742210001']),
        StudentImportCsv::row(['first_name' => 'Ana', 'last_name' => 'Santos', 'username' => 'anasantos']),
    ]);

    expect(array_map(fn ($row) => $row->status, $preview->rows))->toBe([ImportRowStatus::Create, ImportRowStatus::Create])
        ->and($preview->counts())->toBe(['all' => 2, 'create' => 2, 'enroll' => 0, 'skip' => 0, 'error' => 0])
        ->and($preview->importableCount())->toBe(2)
        ->and($preview->rows[0]->notes)->toBe([])
        ->and($preview->usesNameParts)->toBeTrue();
});

it('rejects an LRN or username that repeats in the file on the later row', function () {
    $preview = StudentImportCsv::preview([
        StudentImportCsv::row(['lrn' => '136742210001', 'username' => 'jdelacruz']),
        StudentImportCsv::row(['first_name' => 'Ana', 'lrn' => '136742210002', 'username' => 'JDelaCruz']),
        StudentImportCsv::row(['first_name' => 'Ben', 'lrn' => '136742210001']),
    ]);

    expect($preview->rows[0]->status)->toBe(ImportRowStatus::Create)
        ->and($preview->rows[1]->status)->toBe(ImportRowStatus::Error)
        ->and($preview->rows[1]->errors)->toBe(['Username already appears in row 2.'])
        ->and($preview->rows[2]->errors)->toBe(['LRN already appears in row 2.']);
});

it('rejects a username another account already uses, ignoring letter case', function () {
    User::factory()->teacher()->create(['username' => 'MSantos']);

    $preview = StudentImportCsv::preview([StudentImportCsv::row(['username' => 'msantos'])]);

    expect($preview->rows[0]->status)->toBe(ImportRowStatus::Error)
        ->and($preview->rows[0]->errors)->toBe(['Username is already taken.']);
});

it('enrolls a learner whose LRN already exists into the classroom', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $learner = StudentImportCsv::learner('136742210001', GradeLevel::Five, ['name' => 'Juan Dela Cruz', 'username' => 'juan_dc']);

    $preview = StudentImportCsv::preview([StudentImportCsv::row(['lrn' => '136742210001', 'username' => 'juan_dc'])], $classroom);

    expect($preview->rows[0])
        ->status->toBe(ImportRowStatus::Enroll)
        ->notes->toBe(['Matches the existing learner Juan Dela Cruz (juan_dc).'])
        ->and($preview->rows[0]->existingStudent->is($learner))->toBeTrue();
});

it('skips a learner who is already enrolled in the classroom', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $learner = StudentImportCsv::learner('136742210001', GradeLevel::Five, ['name' => 'Juan Dela Cruz', 'username' => 'juan_dc']);
    $classroom->students()->attach($learner);

    $preview = StudentImportCsv::preview([StudentImportCsv::row(['lrn' => '136742210001'])], $classroom);

    expect($preview->rows[0])
        ->status->toBe(ImportRowStatus::Skip)
        ->notes->toBe(['Matches the existing learner Juan Dela Cruz (juan_dc).', 'Already enrolled in this classroom.']);
});

it('skips existing learners when no classroom is chosen', function () {
    StudentImportCsv::learner('136742210001', GradeLevel::Five, ['name' => 'Juan Dela Cruz', 'username' => 'juan_dc']);

    $preview = StudentImportCsv::preview([StudentImportCsv::row(['lrn' => '136742210001'])]);

    expect($preview->rows[0])
        ->status->toBe(ImportRowStatus::Skip)
        ->notes->toBe([
            'Matches the existing learner Juan Dela Cruz (juan_dc).',
            'A learner with this LRN already exists. Choose a classroom to enroll existing learners.',
        ])
        ->and($preview->hasImportableRows())->toBeFalse();
});

it('refuses to enroll a deactivated learner', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    StudentImportCsv::learner('136742210001', GradeLevel::Five)->forceFill(['is_active' => false])->save();

    $preview = StudentImportCsv::preview([StudentImportCsv::row(['lrn' => '136742210001'])], $classroom);

    expect($preview->rows[0]->status)->toBe(ImportRowStatus::Error)
        ->and($preview->rows[0]->errors)->toBe(['The learner with this LRN has a deactivated account.']);
});

it('refuses to enroll an existing learner of another grade', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    StudentImportCsv::learner('136742210001', GradeLevel::Six);

    $preview = StudentImportCsv::preview([StudentImportCsv::row(['lrn' => '136742210001', 'grade_level' => ''])], $classroom);

    expect($preview->rows[0]->status)->toBe(ImportRowStatus::Error)
        ->and($preview->rows[0]->errors)->toBe(['The learner with this LRN is in Grade 6, not Grade 5 like this classroom.']);
});

it('rejects a username that belongs to someone other than the learner with the LRN', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    StudentImportCsv::learner('136742210001', GradeLevel::Five, ['username' => 'juan_dc']);
    User::factory()->student(GradeLevel::Five)->create(['username' => 'ana_s']);

    $preview = StudentImportCsv::preview([StudentImportCsv::row(['lrn' => '136742210001', 'username' => 'ana_s'])], $classroom);

    expect($preview->rows[0]->errors)->toBe(['This username belongs to another account, not to the learner with this LRN.']);
});

it('links an existing parent account found by email, ignoring letter case', function () {
    $parent = User::factory()->parent()->create(['name' => 'Maria Dela Cruz', 'email' => 'maria.delacruz@example.com']);

    $preview = StudentImportCsv::preview([StudentImportCsv::row([
        'guardian_email' => 'Maria.DelaCruz@Example.com',
        'guardian_relationship' => 'mother',
    ])]);

    $guardian = $preview->rows[0]->guardian;

    expect($preview->rows[0]->status)->toBe(ImportRowStatus::Create)
        ->and($guardian->action)->toBe(GuardianAction::LinkExisting)
        ->and($guardian->parent->is($parent))->toBeTrue()
        ->and($guardian->relationship)->toBe(GuardianRelationship::Mother)
        ->and($preview->rows[0]->notes)->toBe(['Links the existing parent account of Maria Dela Cruz.'])
        ->and($preview->parentAccountsToCreate())->toBe(0)
        ->and($preview->guardianLinksToAdd())->toBe(1);
});

it('notes when the existing parent account is deactivated', function () {
    User::factory()->parent()->inactive()->create(['name' => 'Rodel Javier', 'email' => 'rodel@example.com']);

    $preview = StudentImportCsv::preview([StudentImportCsv::row(['guardian_email' => 'rodel@example.com'])]);

    expect($preview->rows[0]->notes)->toBe(['Links the existing parent account of Rodel Javier.', 'That parent account is deactivated.']);
});

it('marks a guardian who is already linked to the existing learner', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    $learner = StudentImportCsv::learner('136742210001');
    User::factory()->parent()->guardianOf($learner, GuardianRelationship::Mother)->create(['name' => 'Maria Dela Cruz', 'email' => 'maria@example.com']);

    $preview = StudentImportCsv::preview([StudentImportCsv::row(['lrn' => '136742210001', 'guardian_email' => 'maria@example.com'])], $classroom);

    expect($preview->rows[0]->status)->toBe(ImportRowStatus::Enroll)
        ->and($preview->rows[0]->guardian->action)->toBe(GuardianAction::AlreadyLinked)
        ->and($preview->rows[0]->notes)->toContain('Already linked to the parent account of Maria Dela Cruz.')
        ->and($preview->guardianLinksToAdd())->toBe(0);
});

it('rejects a guardian email that belongs to a staff or learner account', function (string $role) {
    User::factory()->{$role}()->create(['email' => 'staff@example.com']);

    $preview = StudentImportCsv::preview([StudentImportCsv::row(['guardian_name' => 'Maria', 'guardian_email' => 'staff@example.com'])]);

    expect($preview->rows[0]->status)->toBe(ImportRowStatus::Error)
        ->and($preview->rows[0]->errors)->toBe(['This email belongs to a non-parent account.']);
})->with(['admin', 'teacher']);

it('creates one parent account per new email and links the later rows to it', function () {
    $preview = StudentImportCsv::preview([
        StudentImportCsv::row(['guardian_name' => 'Maria Dela Cruz', 'guardian_email' => 'maria@example.com', 'guardian_relationship' => 'mother']),
        StudentImportCsv::row(['first_name' => 'Pedro', 'grade_level' => '6', 'guardian_email' => 'MARIA@example.com', 'guardian_relationship' => 'mother']),
    ]);

    expect($preview->rows[0]->guardian)
        ->action->toBe(GuardianAction::CreateAccount)
        ->name->toBe('Maria Dela Cruz')
        ->email->toBe('maria@example.com')
        ->and($preview->rows[0]->notes)->toBe(['Creates a parent account for maria@example.com.'])
        ->and($preview->rows[1]->guardian)
        ->action->toBe(GuardianAction::LinkCreated)
        ->sourceRow->toBe(2)
        ->and($preview->rows[1]->notes)->toBe(['Links the parent account created for row 2.'])
        ->and($preview->parentAccountsToCreate())->toBe(1)
        ->and($preview->guardianLinksToAdd())->toBe(2);
});

it('requires a guardian name to create a parent account', function () {
    $preview = StudentImportCsv::preview([StudentImportCsv::row(['guardian_email' => 'maria@example.com'])]);

    expect($preview->rows[0]->status)->toBe(ImportRowStatus::Error)
        ->and($preview->rows[0]->errors)->toBe(['Guardian name is required to create a parent account.']);
});

it('lets the next valid row create the parent account when the first row has errors', function () {
    $preview = StudentImportCsv::preview([
        StudentImportCsv::row(['lrn' => 'bad', 'guardian_name' => 'Maria Dela Cruz', 'guardian_email' => 'maria@example.com']),
        StudentImportCsv::row(['first_name' => 'Pedro', 'guardian_name' => 'Maria D. Cruz', 'guardian_email' => 'maria@example.com']),
    ]);

    expect($preview->rows[0]->status)->toBe(ImportRowStatus::Error)
        ->and($preview->rows[0]->guardian)->toBeNull()
        ->and($preview->rows[1]->guardian)
        ->action->toBe(GuardianAction::CreateAccount)
        ->name->toBe('Maria D. Cruz');
});

it('notes a guardian name without an email', function () {
    $preview = StudentImportCsv::preview([StudentImportCsv::row(['guardian_name' => 'Maria Dela Cruz'])]);

    expect($preview->rows[0])
        ->status->toBe(ImportRowStatus::Create)
        ->guardian->toBeNull()
        ->notes->toBe(['Add a guardian email to link a parent account.']);
});

it('does not apply guardian details to skipped rows', function () {
    StudentImportCsv::learner('136742210001', GradeLevel::Five, ['name' => 'Juan Dela Cruz', 'username' => 'juan_dc']);
    User::factory()->teacher()->create(['email' => 'staff@example.com']);

    $preview = StudentImportCsv::preview([StudentImportCsv::row(['lrn' => '136742210001', 'guardian_email' => 'staff@example.com'])]);

    expect($preview->rows[0])
        ->status->toBe(ImportRowStatus::Skip)
        ->guardian->toBeNull()
        ->and($preview->rows[0]->notes)->toContain('Guardian details are not used because this row is skipped.');
});

it('reads the database with the same number of queries for any number of rows', function () {
    $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
    User::factory()->parent()->create(['email' => 'parent@example.com']);
    $rowsFor = fn (int $count): array => array_map(fn (int $index): array => StudentImportCsv::row([
        'first_name' => 'Learner'.$index,
        'lrn' => '13674221'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'username' => 'learner'.$index,
        'guardian_email' => 'parent@example.com',
    ]), range(1, $count));

    DB::enableQueryLog();
    StudentImportCsv::preview($rowsFor(2), $classroom);
    $fewRows = count(DB::getQueryLog());
    DB::flushQueryLog();
    StudentImportCsv::preview($rowsFor(40), $classroom);
    $manyRows = count(DB::getQueryLog());

    expect($manyRows)->toBe($fewRows)->toBeLessThanOrEqual(4);
});
