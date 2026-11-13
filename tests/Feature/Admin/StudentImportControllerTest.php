<?php

use App\Enums\GradeLevel;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\StudentImport\PendingImports;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Services\StudentImport\Support\StudentImportCsv;

describe('create', function () {
    it('shows the upload form with the classrooms that are not archived', function () {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create(['name' => 'Maricel D. Santos']);
        $schoolYear = SchoolYear::factory()->active()->create(['name' => '2026–2027']);
        $classroom = Classroom::factory()->for($teacher, 'teacher')->for($schoolYear)->create(['name' => 'Health 5 – Sampaguita', 'grade_level' => GradeLevel::Five]);
        Classroom::factory()->for($teacher, 'teacher')->for($schoolYear)->create(['name' => 'Science 6 – Narra', 'grade_level' => GradeLevel::Six]);
        Classroom::factory()->archived()->create(['name' => 'Old Health 5 – Rizal']);

        $response = $this->actingAs($admin)->get(route('admin.students.import.create'));

        $response->assertOk()
            ->assertViewIs('admin.students.import.create')
            ->assertViewHas('classroomOptions', [
                'Grade 5 · SY 2026–2027' => [$classroom->id => 'Health 5 – Sampaguita · Maricel D. Santos'],
                'Grade 6 · SY 2026–2027' => [Classroom::query()->where('name', 'Science 6 – Narra')->value('id') => 'Science 6 – Narra · Maricel D. Santos'],
            ])
            ->assertSee('action="'.route('admin.students.import.store').'"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee(route('admin.students.import.template'))
            ->assertSee('data-import-column="guardian_email"', false)
            ->assertDontSee('Old Health 5 – Rizal');
    });

    it('preselects the classroom from the query string', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.import.create', ['classroom' => $classroom->id]))
            ->assertOk()
            ->assertSeeInOrder(['<option value="'.$classroom->id.'"', 'selected'], false);
    });

    it('explains that accounts can be created without a classroom when none exists', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.import.create'))
            ->assertOk()
            ->assertViewHas('classroomOptions', [])
            ->assertSee('data-import-no-classrooms', false);
    });

    it('shows the result of the import that was just committed with its sign-in details', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->withSession([
                'importResult' => ['created' => 2, 'enrolled' => 1, 'skipped' => 1, 'failed' => 2, 'parentsCreated' => 1, 'guardiansLinked' => 2, 'accounts' => 3, 'classroom' => 'Health 5 – Sampaguita'],
                'credentials' => [
                    ['name' => 'Juan Dela Cruz', 'username' => 'jdelacruz', 'password' => 'mango-4821'],
                    ['name' => 'Maria Dela Cruz', 'username' => 'maria@example.com', 'password' => 'Kp7m-Xw3q-9tRb'],
                ],
            ])
            ->get(route('admin.students.import.create'))
            ->assertOk()
            ->assertSee('data-import-result', false)
            ->assertSeeText('Import complete')
            ->assertSeeText('Learners were added to Health 5 – Sampaguita.')
            ->assertSeeText('2 learners were linked to parent accounts.')
            ->assertSee('data-credentials-alert="2"', false)
            ->assertSeeText('mango-4821')
            ->assertSee(route('admin.students.import.credentials'));
    });

    it('says there is nothing to save when the import created no accounts', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->withSession(['importResult' => ['created' => 0, 'enrolled' => 3, 'skipped' => 0, 'failed' => 0, 'parentsCreated' => 0, 'guardiansLinked' => 0, 'accounts' => 0, 'classroom' => 'Health 5 – Sampaguita']])
            ->get(route('admin.students.import.create'))
            ->assertOk()
            ->assertSeeText('No new accounts were created, so there are no sign-in details to save.')
            ->assertDontSee('data-credentials-alert', false);
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.students.import.create'))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.students.import.create'))
            ->assertForbidden();
    })->with([UserRole::Teacher, UserRole::Parent]);
});

describe('store', function () {
    it('stores the file privately and opens the preview', function () {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.students.import.store'), [
            'file' => StudentImportCsv::upload(StudentImportCsv::make([StudentImportCsv::row()]), 'Grade 5 list.csv'),
        ]);

        $id = Str::afterLast(parse_url($response->headers->get('Location'), PHP_URL_PATH), '/');
        $response->assertRedirectToRoute('admin.students.import.show', $id)->assertSessionHasNoErrors();
        expect(session('imports.'.$id))
            ->toMatchArray(['user_id' => $admin->id, 'classroom_id' => null, 'original_name' => 'Grade 5 list.csv', 'path' => 'imports/'.$id.'.csv']);
        Storage::disk('local')->assertExists('imports/'.$id.'.csv');
    });

    it('remembers the chosen classroom', function () {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);

        $response = $this->actingAs($admin)->post(route('admin.students.import.store'), [
            'classroom_id' => $classroom->id,
            'file' => StudentImportCsv::upload("first_name,last_name\nJuan,Dela Cruz\n"),
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();
        $id = Str::afterLast(parse_url($response->headers->get('Location'), PHP_URL_PATH), '/');
        expect(session('imports.'.$id.'.classroom_id'))->toBe($classroom->id);
    });

    it('rejects invalid uploads', function (Closure $payload, string $field, string $message) {
        Storage::fake('local');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.students.import.store'), $payload())
            ->assertSessionHasErrors([$field => $message]);

        expect(Storage::disk('local')->allFiles())->toBe([]);
    })->with([
        'no file' => [fn (): array => [], 'file', 'Choose a CSV file to upload.'],
        'an image' => [fn (): array => ['file' => \Illuminate\Http\UploadedFile::fake()->image('photo.png')], 'file', 'Upload a CSV file (.csv). In Excel, choose File › Save As › CSV UTF-8.'],
        'a spreadsheet' => [fn (): array => ['file' => \Illuminate\Http\UploadedFile::fake()->create('learners.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')], 'file', 'Upload a CSV file (.csv). In Excel, choose File › Save As › CSV UTF-8.'],
        'a file over the size limit' => [fn (): array => ['file' => \Illuminate\Http\UploadedFile::fake()->create('learners.csv', 2049, 'text/csv')], 'file', 'The file must not be larger than 2 MB.'],
        'a classroom that does not exist' => [fn (): array => ['classroom_id' => 999, 'file' => StudentImportCsv::upload(StudentImportCsv::make([StudentImportCsv::row()]))], 'classroom_id', 'Choose a classroom that is not archived.'],
        'a classroom that is not a number' => [fn (): array => ['classroom_id' => 'abc', 'file' => StudentImportCsv::upload(StudentImportCsv::make([StudentImportCsv::row()]))], 'classroom_id', 'Choose a classroom from the list.'],
    ]);

    it('rejects an archived classroom', function () {
        Storage::fake('local');
        $classroom = Classroom::factory()->archived()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.students.import.store'), [
                'classroom_id' => $classroom->id,
                'file' => StudentImportCsv::upload(StudentImportCsv::make([StudentImportCsv::row()])),
            ])
            ->assertSessionHasErrors(['classroom_id' => 'Choose a classroom that is not archived.']);
    });

    it('rejects files whose columns or rows cannot be imported', function (string $contents, string $message) {
        Storage::fake('local');
        config(['nutrimind.import.max_rows' => 2]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.students.import.store'), ['file' => StudentImportCsv::upload($contents)])
            ->assertSessionHasErrors(['file' => $message]);

        expect(Storage::disk('local')->allFiles())->toBe([]);
    })->with([
        'an empty file' => ['', 'The file is empty. Add a heading row and one learner per row.'],
        'no grade column without a classroom' => ["first_name,last_name\nJuan,Dela Cruz\n", 'The file is missing the grade_level column.'],
        'no name columns' => ["lrn,grade_level\n136742210001,5\n", 'The file is missing the first_name and last_name columns (or a name column).'],
        'only headings' => ["name,grade_level\n", 'The file has no learner rows. Add one learner per row below the heading row.'],
        'too many rows' => ["name,grade_level\nA One,5\nB Two,5\nC Three,5\n", 'The file has 3 learner rows. Import at most 2 learners at a time.'],
    ]);

    it('reports every problem of the file', function () {
        Storage::fake('local');

        $response = $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.students.import.store'), ['file' => StudentImportCsv::upload("lrn,lrn\n")]);

        $response->assertSessionHasErrors('file');
        expect(session('errors')->get('file'))->toBe([
            'The file is missing these columns: first_name and last_name (or name), grade_level.',
            'The file has more than one lrn column. Keep one of each.',
            'The file has no learner rows. Add one learner per row below the heading row.',
        ]);
    });

    it('shows every file problem on the form', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->withViewErrors(['file' => ['The file is missing the grade_level column.', 'The file has no learner rows. Add one learner per row below the heading row.']])
            ->get(route('admin.students.import.create'))
            ->assertOk()
            ->assertSeeText('The file is missing the grade_level column.')
            ->assertSee('data-import-file-errors', false)
            ->assertSeeText('The file has no learner rows.');
    });

    it('sends guests to the sign-in page', function () {
        $this->post(route('admin.students.import.store'))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        Storage::fake('local');

        $this->actingAs(User::factory()->{$role->value}()->create())
            ->post(route('admin.students.import.store'), ['file' => StudentImportCsv::upload(StudentImportCsv::make([StudentImportCsv::row()]))])
            ->assertForbidden();

        expect(Storage::disk('local')->allFiles())->toBe([]);
    })->with([UserRole::Teacher, UserRole::Parent]);
});

describe('show', function () {
    it('previews every row with its status, counts and messages', function () {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create(['name' => 'Health 5 – Sampaguita', 'grade_level' => GradeLevel::Five]);
        $existing = StudentImportCsv::learner('136742210001', GradeLevel::Five, ['name' => 'Juan Dela Cruz', 'username' => 'juan_dc']);
        $enrolled = StudentImportCsv::learner('136742210002', GradeLevel::Five, ['name' => 'Ana Santos', 'username' => 'ana_s']);
        $classroom->students()->attach($enrolled);
        User::factory()->parent()->create(['name' => 'Ana Navarro', 'email' => 'ana.navarro@example.com']);
        $pending = StudentImportCsv::pending($admin, [
            StudentImportCsv::row(['first_name' => 'Sofia', 'last_name' => 'Navarro', 'guardian_email' => 'ana.navarro@example.com', 'guardian_relationship' => 'mother']),
            StudentImportCsv::row(['lrn' => '136742210001']),
            StudentImportCsv::row(['first_name' => 'Ana', 'last_name' => 'Santos', 'lrn' => '136742210002']),
            StudentImportCsv::row(['first_name' => 'Ben', 'grade_level' => '6', 'guardian_name' => 'Rosa Cruz', 'guardian_email' => 'rosa@example.com']),
        ], $classroom);

        $response = $this->actingAs($admin)->get(route('admin.students.import.show', $pending->id));

        $response->assertOk()
            ->assertViewIs('admin.students.import.show')
            ->assertSeeText('grade-5-sampaguita.csv')
            ->assertSeeText('Health 5 – Sampaguita')
            ->assertSee('data-import-row="2" data-import-status="create"', false)
            ->assertSee('data-import-row="3" data-import-status="enroll"', false)
            ->assertSee('data-import-row="4" data-import-status="skip"', false)
            ->assertSee('data-import-row="5" data-import-status="error"', false)
            ->assertSeeText('Links the existing parent account of Ana Navarro.')
            ->assertSeeText('Matches the existing learner Juan Dela Cruz (juan_dc).')
            ->assertSeeText('Already enrolled in this classroom.')
            ->assertSeeText('Grade level does not match this classroom (Grade 5).')
            ->assertSeeText('Import 2 learners')
            ->assertSee('data-import-commit="enabled"', false)
            ->assertSee('action="'.route('admin.students.import.commit', $pending->id).'"', false)
            ->assertSee('data-import-notice="errors"', false)
            ->assertSee('data-import-notice="guardians"', false);
        expect($response->viewData('preview')->counts())->toBe(['all' => 4, 'create' => 1, 'enroll' => 1, 'skip' => 1, 'error' => 1])
            ->and($existing->enrolledClassrooms()->count())->toBe(0);
    });

    it('previews an import without a classroom', function () {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $pending = StudentImportCsv::pending($admin, [StudentImportCsv::row()]);

        $this->actingAs($admin)
            ->get(route('admin.students.import.show', $pending->id))
            ->assertOk()
            ->assertViewHas('classroom', null)
            ->assertSeeText('No classroom · accounts only')
            ->assertSeeText('choose a classroom to enroll');
    });

    it('filters the rows by status', function () {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $pending = StudentImportCsv::pending($admin, [
            StudentImportCsv::row(['first_name' => 'Valid']),
            StudentImportCsv::row(['first_name' => 'Broken', 'lrn' => '12']),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.students.import.show', [$pending->id, 'status' => 'error']))
            ->assertOk()
            ->assertSee('data-import-row="3"', false)
            ->assertDontSee('data-import-row="2"', false)
            ->assertSeeText('LRN must be exactly 12 digits.');
    });

    it('shows an empty state for a status without rows', function () {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $pending = StudentImportCsv::pending($admin, [StudentImportCsv::row()]);

        $this->actingAs($admin)
            ->get(route('admin.students.import.show', [$pending->id, 'status' => 'skip']))
            ->assertOk()
            ->assertSee('data-import-rows-empty', false)
            ->assertSeeText('No skipped rows');
    });

    it('shows every row for an unknown status', function () {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $pending = StudentImportCsv::pending($admin, [StudentImportCsv::row()]);

        $this->actingAs($admin)
            ->get(route('admin.students.import.show', [$pending->id, 'status' => 'everything']))
            ->assertOk()
            ->assertViewHas('status', null)
            ->assertSee('data-import-row="2"', false);
    });

    it('pages through long files 25 rows at a time', function () {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $pending = StudentImportCsv::pending($admin, array_map(
            fn (int $index): array => StudentImportCsv::row(['first_name' => 'Learner'.$index, 'username' => 'learner'.$index]),
            range(1, 30),
        ));

        $this->actingAs($admin)
            ->get(route('admin.students.import.show', [$pending->id, 'page' => 2]))
            ->assertOk()
            ->assertSee('data-import-row="27"', false)
            ->assertSee('data-import-row="31"', false)
            ->assertDontSee('data-import-row="26"', false)
            ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 30 && $rows->count() === 5);
    });

    it('disables the import when nothing can be imported', function () {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $pending = StudentImportCsv::pending($admin, [StudentImportCsv::row(['lrn' => 'invalid'])]);

        $this->actingAs($admin)
            ->get(route('admin.students.import.show', $pending->id))
            ->assertOk()
            ->assertSee('data-import-commit="disabled"', false)
            ->assertDontSee('data-import-commit="enabled"', false)
            ->assertSee('data-import-notice="nothing"', false)
            ->assertSeeText('Import 0 learners');
    });

    it('escapes the uploaded values', function () {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $pending = StudentImportCsv::pending($admin, [StudentImportCsv::row(['first_name' => '<script>alert(1)</script>', 'guardian_name' => '<b>Maria</b>'])]);

        $this->actingAs($admin)
            ->get(route('admin.students.import.show', $pending->id))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<b>Maria</b>', false);
    });

    it('returns 404 for tokens that are unknown, malformed or someone else\'s', function (Closure $token) {
        Storage::fake('local');
        $owner = User::factory()->admin()->create();
        $pending = StudentImportCsv::pending($owner, [StudentImportCsv::row()]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.students.import.show', $token($pending)))
            ->assertNotFound();
    })->with([
        'another administrator\'s token' => [fn ($pending): string => $pending->id],
        'an unknown token' => [fn (): string => (string) Str::uuid()],
        'a malformed token' => [fn (): string => 'not-a-token'],
    ]);

    it('starts over when the uploaded file is gone', function () {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $pending = StudentImportCsv::pending($admin, [StudentImportCsv::row()]);
        Storage::disk('local')->delete($pending->path);

        $this->actingAs($admin)
            ->get(route('admin.students.import.show', $pending->id))
            ->assertRedirectToRoute('admin.students.import.create')
            ->assertSessionHas('error', 'The uploaded file is no longer available. Upload it again.');

        expect(app(PendingImports::class)->find($pending->id, $admin))->toBeNull();
    });

    it('starts over when the chosen classroom was archived after the upload', function () {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);
        $pending = StudentImportCsv::pending($admin, [StudentImportCsv::row()], $classroom);
        $classroom->forceFill(['archived_at' => now()])->save();

        $this->actingAs($admin)
            ->get(route('admin.students.import.show', $pending->id))
            ->assertRedirectToRoute('admin.students.import.create')
            ->assertSessionHas('error', 'The classroom chosen for this import was archived or deleted. Upload the file again.');

        Storage::disk('local')->assertMissing($pending->path);
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('admin.students.import.show', (string) Str::uuid()))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('admin.students.import.show', (string) Str::uuid()))
            ->assertForbidden();
    })->with([UserRole::Teacher, UserRole::Parent]);
});
