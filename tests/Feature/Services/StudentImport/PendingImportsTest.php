<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use App\Services\StudentImport\PendingImports;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Services\StudentImport\Support\StudentImportCsv;

it('stores the upload privately and remembers it in the session', function () {
    Storage::fake('local');
    $this->freezeSecond();
    $user = User::factory()->admin()->create();
    $classroom = Classroom::factory()->create();

    $pending = app(PendingImports::class)->store(StudentImportCsv::upload("name,grade_level\nJuan,5\n", 'Class List.csv'), $user, $classroom);

    expect(Str::isUuid($pending->id))->toBeTrue()
        ->and($pending->path)->toBe('imports/'.$pending->id.'.csv')
        ->and($pending->originalName)->toBe('Class List.csv')
        ->and($pending->classroomId)->toBe($classroom->id)
        ->and(session('imports.'.$pending->id))->toBe([
            'user_id' => $user->id,
            'classroom_id' => $classroom->id,
            'original_name' => 'Class List.csv',
            'path' => 'imports/'.$pending->id.'.csv',
            'uploaded_at' => now()->getTimestamp(),
        ]);
    Storage::disk('local')->assertExists($pending->path);
    expect(Storage::disk('local')->get($pending->path))->toBe("name,grade_level\nJuan,5\n");
});

it('finds an import only for the user who uploaded it', function () {
    Storage::fake('local');
    $owner = User::factory()->teacher()->create();
    $imports = app(PendingImports::class);
    $pending = $imports->store(StudentImportCsv::upload("name\nJuan\n"), $owner, null);

    expect($imports->find($pending->id, $owner)?->id)->toBe($pending->id)
        ->and($imports->find($pending->id, User::factory()->teacher()->create()))->toBeNull()
        ->and($imports->find((string) Str::uuid(), $owner))->toBeNull()
        ->and($imports->find('../../.env', $owner))->toBeNull();
});

it('replaces the earlier upload of the same user and classroom', function () {
    Storage::fake('local');
    $user = User::factory()->admin()->create();
    $classroom = Classroom::factory()->create();
    $imports = app(PendingImports::class);
    $first = $imports->store(StudentImportCsv::upload("name\nJuan\n"), $user, $classroom);
    $otherClassroom = $imports->store(StudentImportCsv::upload("name\nAna\n"), $user, null);

    $second = $imports->store(StudentImportCsv::upload("name\nPedro\n"), $user, $classroom);

    expect($imports->find($first->id, $user))->toBeNull()
        ->and($imports->find($otherClassroom->id, $user))->not->toBeNull()
        ->and($imports->find($second->id, $user))->not->toBeNull();
    Storage::disk('local')->assertMissing($first->path);
    Storage::disk('local')->assertExists($otherClassroom->path);
});

it('deletes uploads older than a day when a new file is stored', function () {
    Storage::fake('local');
    $user = User::factory()->admin()->create();
    $imports = app(PendingImports::class);
    $this->travelTo(now()->subHours(25));
    $stale = $imports->store(StudentImportCsv::upload("name\nJuan\n"), $user, null);
    touch(Storage::disk('local')->path($stale->path), now()->getTimestamp());
    Storage::disk('local')->put('imports/abandoned-by-someone-else.csv', 'name');
    touch(Storage::disk('local')->path('imports/abandoned-by-someone-else.csv'), now()->getTimestamp());
    $this->travelBack();

    $imports->store(StudentImportCsv::upload("name\nAna\n"), $user, Classroom::factory()->create());

    expect($imports->find($stale->id, $user))->toBeNull();
    Storage::disk('local')->assertMissing([$stale->path, 'imports/abandoned-by-someone-else.csv']);
});

it('keeps only the file name of the uploaded file for display', function () {
    Storage::fake('local');

    $pending = app(PendingImports::class)->store(StudentImportCsv::upload("name\nJuan\n", '..\\..\\secret\\list.csv'), User::factory()->admin()->create(), null);

    expect($pending->originalName)->toBe('list.csv');
});

it('discards the file and the token', function () {
    Storage::fake('local');
    $user = User::factory()->admin()->create();
    $imports = app(PendingImports::class);
    $pending = $imports->store(StudentImportCsv::upload("name\nJuan\n"), $user, null);

    $imports->discard($pending);

    expect($imports->find($pending->id, $user))->toBeNull()
        ->and($imports->contents($pending))->toBeNull();
    Storage::disk('local')->assertMissing($pending->path);
});

it('keeps sign-in details encrypted for one download by the same user', function () {
    $user = User::factory()->teacher()->create();
    $imports = app(PendingImports::class);
    $credentials = [
        ['name' => 'Juan Dela Cruz', 'username' => 'jdelacruz', 'password' => 'mango-1234', 'role' => UserRole::Student],
        ['name' => 'Maria Dela Cruz', 'username' => 'maria@example.com', 'password' => 'Kp7m-Xw3q-9tRb', 'role' => UserRole::Parent],
    ];

    $imports->putCredentials($user, $credentials);

    expect(session(PendingImports::CREDENTIALS_SESSION_KEY))->toBeString()->not->toContain('mango-1234')
        ->and($imports->pullCredentials($user))->toBe($credentials)
        ->and($imports->pullCredentials($user))->toBeNull();
});

it('gives sign-in details to no one but the importing user', function () {
    $imports = app(PendingImports::class);
    $imports->putCredentials(User::factory()->teacher()->create(), [
        ['name' => 'Juan Dela Cruz', 'username' => 'jdelacruz', 'password' => 'mango-1234', 'role' => UserRole::Student],
    ]);

    expect($imports->pullCredentials(User::factory()->teacher()->create()))->toBeNull()
        ->and(session()->has(PendingImports::CREDENTIALS_SESSION_KEY))->toBeFalse();
});

it('ignores tampered sign-in details', function () {
    $user = User::factory()->teacher()->create();
    session()->put(PendingImports::CREDENTIALS_SESSION_KEY, 'not-encrypted');

    expect(app(PendingImports::class)->pullCredentials($user))->toBeNull();
});

it('forgets earlier sign-in details when an import creates no accounts', function () {
    $user = User::factory()->teacher()->create();
    $imports = app(PendingImports::class);
    $imports->putCredentials($user, [['name' => 'Juan', 'username' => 'juan', 'password' => 'mango-1234', 'role' => UserRole::Student]]);

    $imports->putCredentials($user, []);

    expect($imports->pullCredentials($user))->toBeNull();
});
