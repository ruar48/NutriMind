<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;

describe('store', function () {
    it('signs a learner in and returns a token that opens the game API', function () {
        $student = User::factory()->student()->create(['username' => 'jdelacruz', 'last_login_at' => null]);

        $response = $this->postJson(route('game.login'), [
            'username' => 'jdelacruz',
            'password' => 'password',
            'device_name' => 'Classroom tablet 3',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonPath('data.username', 'jdelacruz')
            ->assertJsonPath('token_type', 'Bearer');

        expect($student->refresh()->last_login_at)->not->toBeNull()
            ->and($student->tokens()->sole()->name)->toBe('Classroom tablet 3');

        $this->app['auth']->forgetGuards();

        $this->withToken($response->json('token'))
            ->getJson(route('game.profile'))
            ->assertOk()
            ->assertJsonPath('data.id', $student->id);
    });

    it('matches the username without regard to letter case or surrounding spaces', function () {
        $student = User::factory()->student()->create(['username' => 'jdelacruz']);

        $this->postJson(route('game.login'), ['username' => '  JDelaCruz ', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.id', $student->id);
    });

    it('names the token after the game when the device sends no name', function () {
        $student = User::factory()->student()->create(['username' => 'jdelacruz']);

        $this->postJson(route('game.login'), ['username' => 'jdelacruz', 'password' => 'password'])->assertOk();

        expect($student->tokens()->sole()->name)->toBe('NutriMind game');
    });

    it('rejects a wrong password with 422', function () {
        User::factory()->student()->create(['username' => 'jdelacruz']);

        $this->postJson(route('game.login'), ['username' => 'jdelacruz', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'These credentials do not match our records.'])
            ->assertJsonMissingPath('token');
    });

    it('gives staff and parent accounts the same answer as a wrong password', function (string $role) {
        User::factory()->{$role}()->create(['username' => 'staffuser']);

        $this->postJson(route('game.login'), ['username' => 'staffuser', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'These credentials do not match our records.']);

        expect(User::query()->where('username', 'staffuser')->sole()->tokens()->count())->toBe(0);
    })->with(['admin', 'teacher', 'parent']);

    it('tells a deactivated learner with the right password that the account is deactivated', function () {
        $student = User::factory()->student()->inactive()->create(['username' => 'jdelacruz']);

        $this->postJson(route('game.login'), ['username' => 'jdelacruz', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'This account has been deactivated. Please contact your school administrator.']);

        expect($student->tokens()->count())->toBe(0);
    });

    it('requires the username and password', function () {
        $this->postJson(route('game.login'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'username' => 'The username field is required.',
                'password' => 'The password field is required.',
            ]);
    });

    it('locks the sign-in after five failed tries', function () {
        Event::fake([Lockout::class]);
        User::factory()->student()->create(['username' => 'jdelacruz']);

        foreach (range(1, 5) as $try) {
            $this->postJson(route('game.login'), ['username' => 'jdelacruz', 'password' => 'wrong-password']);
        }

        $this->postJson(route('game.login'), ['username' => 'jdelacruz', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username' => 'Too many login attempts.']);

        Event::assertDispatched(Lockout::class);
    });
});

describe('destroy', function () {
    it('revokes only the token the game signed out with', function () {
        $student = User::factory()->student()->create();
        $token = $student->createToken('Tablet')->plainTextToken;
        $student->createToken('Phone');

        $this->withToken($token)
            ->postJson(route('game.logout'))
            ->assertNoContent();

        expect($student->tokens()->pluck('name')->all())->toBe(['Phone']);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson(route('game.profile'))
            ->assertUnauthorized();
    });
});
