<?php

use App\Enums\GradeLevel;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Policies\ClassroomPolicy;
use App\Services\RolePermissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Services\StudentImport\Support\StudentImportCsv;

describe('create', function () {
    it('shows the upload form of the teacher\'s classroom', function () {
        $classroom = Classroom::factory()->create(['name' => 'Health 5 – Sampaguita', 'grade_level' => GradeLevel::Five]);
        User::factory()->student(GradeLevel::Five)->enrolledIn($classroom)->count(2)->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.import.create', $classroom))
            ->assertOk()
            ->assertViewIs('teacher.imports.create')
            ->assertSeeText('Health 5 – Sampaguita')
            ->assertSeeText('2 learners enrolled')
            ->assertSee('action="'.route('teacher.classrooms.import.store', $classroom).'"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee(route('teacher.imports.template'))
            ->assertSeeText('Blank cells use Grade 5; other grades are rejected.')
            ->assertSee(route('teacher.classrooms.show', $classroom));
    });

    it('shows the result of the import that was just committed', function () {
        $classroom = Classroom::factory()->create(['name' => 'Health 5 – Sampaguita']);

        $this->actingAs($classroom->teacher)
            ->withSession([
                'importResult' => ['created' => 1, 'enrolled' => 0, 'skipped' => 0, 'failed' => 0, 'parentsCreated' => 0, 'guardiansLinked' => 0, 'accounts' => 1, 'classroom' => 'Health 5 – Sampaguita'],
                'credentials' => [['name' => 'Juan Dela Cruz', 'username' => 'jdelacruz', 'password' => 'mango-4821']],
            ])
            ->get(route('teacher.classrooms.import.create', $classroom))
            ->assertOk()
            ->assertSee('data-import-result', false)
            ->assertSee('data-credentials-alert="1"', false)
            ->assertSee(route('teacher.imports.credentials'));
    });

    it('returns 404 for another teacher\'s classroom', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs(User::factory()->teaching($classroom->subject)->create())
            ->get(route('teacher.classrooms.import.create', $classroom))
            ->assertNotFound();
    });

    it('forbids a classroom whose subject was revoked', function () {
        $classroom = Classroom::factory()->revokedSubject()->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.import.create', $classroom))
            ->assertForbidden()
            ->assertSeeText(ClassroomPolicy::SUBJECT_REVOKED_MESSAGE);
    });

    it('forbids importing when the administrator revoked the permission', function () {
        $classroom = Classroom::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ImportStudents);

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.import.create', $classroom))
            ->assertForbidden()
            ->assertSeeText(RolePermissions::deniedMessage(Permission::ImportStudents));
    });

    it('forbids an archived classroom', function () {
        $classroom = Classroom::factory()->archived()->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.import.create', $classroom))
            ->assertForbidden()
            ->assertSeeText(ClassroomPolicy::ARCHIVED_MESSAGE);
    });

    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.classrooms.import.create', Classroom::factory()->create()))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('teacher.classrooms.import.create', Classroom::factory()->create()))
            ->assertForbidden();
    })->with([UserRole::Admin, UserRole::Parent]);
});

describe('store', function () {
    it('stores the file for the classroom and opens the preview', function () {
        Storage::fake('local');
        $classroom = Classroom::factory()->create(['grade_level' => GradeLevel::Five]);

        $response = $this->actingAs($classroom->teacher)->post(route('teacher.classrooms.import.store', $classroom), [
            'file' => StudentImportCsv::upload("first_name,last_name\nJuan,Dela Cruz\n"),
        ]);

        $id = Str::afterLast(parse_url($response->headers->get('Location'), PHP_URL_PATH), '/');
        $response->assertSessionHasNoErrors()->assertRedirectToRoute('teacher.classrooms.import.show', [$classroom, $id]);
        expect(session('imports.'.$id))->toMatchArray(['user_id' => $classroom->teacher->id, 'classroom_id' => $classroom->id]);
        Storage::disk('local')->assertExists('imports/'.$id.'.csv');
    });

    it('rejects invalid uploads', function (Closure $file, string $message) {
        Storage::fake('local');
        $classroom = Classroom::factory()->create();

        $this->actingAs($classroom->teacher)
            ->post(route('teacher.classrooms.import.store', $classroom), array_filter(['file' => $file()]))
            ->assertSessionHasErrors(['file' => $message]);

        expect(Storage::disk('local')->allFiles())->toBe([]);
    })->with([
        'no file' => [fn () => null, 'Choose a CSV file to upload.'],
        'a PDF' => [fn () => UploadedFile::fake()->create('list.pdf', 10, 'application/pdf'), 'Upload a CSV file (.csv). In Excel, choose File › Save As › CSV UTF-8.'],
        'no name columns' => [fn () => StudentImportCsv::upload("lrn\n136742210001\n"), 'The file is missing the first_name and last_name columns (or a name column).'],
        'no rows' => [fn () => StudentImportCsv::upload("first_name,last_name\n"), 'The file has no learner rows. Add one learner per row below the heading row.'],
    ]);

    it('checks access before validating the upload', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs(User::factory()->teaching($classroom->subject)->create())
            ->post(route('teacher.classrooms.import.store', $classroom), [])
            ->assertNotFound()
            ->assertSessionHasNoErrors();
    });

    it('forbids an archived classroom', function () {
        Storage::fake('local');
        $classroom = Classroom::factory()->archived()->create();

        $this->actingAs($classroom->teacher)
            ->post(route('teacher.classrooms.import.store', $classroom), ['file' => StudentImportCsv::upload(StudentImportCsv::make([StudentImportCsv::row()]))])
            ->assertForbidden();

        expect(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('forbids importing when the administrator revoked the permission', function () {
        Storage::fake('local');
        $classroom = Classroom::factory()->create();
        app(RolePermissions::class)->revoke(UserRole::Teacher, Permission::ImportStudents);

        $this->actingAs($classroom->teacher)
            ->post(route('teacher.classrooms.import.store', $classroom), ['file' => StudentImportCsv::upload(StudentImportCsv::make([StudentImportCsv::row()]))])
            ->assertForbidden();
    });

    it('sends guests to the sign-in page', function () {
        $this->post(route('teacher.classrooms.import.store', Classroom::factory()->create()))->assertRedirectToRoute('login');
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->post(route('teacher.classrooms.import.store', Classroom::factory()->create()))
            ->assertForbidden();
    })->with([UserRole::Admin, UserRole::Parent]);
});

describe('show', function () {
    it('previews the rows against the classroom grade', function () {
        Storage::fake('local');
        $classroom = Classroom::factory()->create(['name' => 'Health 5 – Sampaguita', 'grade_level' => GradeLevel::Five]);
        $pending = StudentImportCsv::pending($classroom->teacher, [
            StudentImportCsv::row(['grade_level' => '']),
            StudentImportCsv::row(['first_name' => 'Ana', 'grade_level' => 'Grade 6']),
        ], $classroom);

        $response = $this->actingAs($classroom->teacher)->get(route('teacher.classrooms.import.show', [$classroom, $pending->id]));

        $response->assertOk()
            ->assertViewIs('teacher.imports.show')
            ->assertSee('data-import-row="2" data-import-status="create"', false)
            ->assertSee('data-import-row="3" data-import-status="error"', false)
            ->assertSeeText('Grade level does not match this classroom (Grade 5).')
            ->assertSee('action="'.route('teacher.classrooms.import.commit', [$classroom, $pending->id]).'"', false)
            ->assertSee(route('teacher.classrooms.import.create', $classroom));
        expect($response->viewData('preview')->rows[0]->data->grade)->toBe(GradeLevel::Five);
    });

    it('returns 404 for the token of another classroom', function () {
        Storage::fake('local');
        $classroom = Classroom::factory()->create();
        $otherClassroom = Classroom::factory()->for($classroom->teacher, 'teacher')->for($classroom->subject)->create();
        $pending = StudentImportCsv::pending($classroom->teacher, [StudentImportCsv::row()], $classroom);

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.import.show', [$otherClassroom, $pending->id]))
            ->assertNotFound();
    });

    it('returns 404 for another teacher\'s token', function () {
        Storage::fake('local');
        $classroom = Classroom::factory()->create();
        $pending = StudentImportCsv::pending($classroom->teacher, [StudentImportCsv::row()], $classroom);
        $otherClassroom = Classroom::factory()->create();

        $this->actingAs($otherClassroom->teacher)
            ->get(route('teacher.classrooms.import.show', [$otherClassroom, $pending->id]))
            ->assertNotFound();
    });

    it('returns 404 for an unknown token', function () {
        $classroom = Classroom::factory()->create();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.import.show', [$classroom, (string) Str::uuid()]))
            ->assertNotFound();
    });

    it('starts over when the uploaded file is gone', function () {
        Storage::fake('local');
        $classroom = Classroom::factory()->create();
        $pending = StudentImportCsv::pending($classroom->teacher, [StudentImportCsv::row()], $classroom);
        Storage::disk('local')->delete($pending->path);

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.import.show', [$classroom, $pending->id]))
            ->assertRedirectToRoute('teacher.classrooms.import.create', $classroom)
            ->assertSessionHas('error', 'The uploaded file is no longer available. Upload it again.');
    });

    it('forbids the preview once the classroom is archived', function () {
        Storage::fake('local');
        $classroom = Classroom::factory()->create();
        $pending = StudentImportCsv::pending($classroom->teacher, [StudentImportCsv::row()], $classroom);
        $classroom->forceFill(['archived_at' => now()])->save();

        $this->actingAs($classroom->teacher)
            ->get(route('teacher.classrooms.import.show', [$classroom, $pending->id]))
            ->assertForbidden();
    });

    it('forbids other roles', function (UserRole $role) {
        $this->actingAs(User::factory()->{$role->value}()->create())
            ->get(route('teacher.classrooms.import.show', [Classroom::factory()->create(), (string) Str::uuid()]))
            ->assertForbidden();
    })->with([UserRole::Admin, UserRole::Parent]);

    it('sends guests to the sign-in page', function () {
        $this->get(route('teacher.classrooms.import.show', [Classroom::factory()->create(), (string) Str::uuid()]))->assertRedirectToRoute('login');
    });
});
