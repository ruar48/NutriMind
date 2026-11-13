<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

describe('create', function () {
    it('shows the sign-in form of each web portal', function (UserRole $portal) {
        $this->get(route('login.portal', $portal->value))
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertViewHas('portal', $portal)
            ->assertSee('action="'.route('login.portal.store', $portal->value).'"', false);
    })->with([
        'administrator' => UserRole::Admin,
        'teacher' => UserRole::Teacher,
        'parent' => UserRole::Parent,
    ]);

    it('has no sign-in form for learners or unknown portals', function (string $portal) {
        $this->get('/login/'.$portal)->assertNotFound();
        $this->post('/login/'.$portal, ['email' => 'learner@school.test', 'password' => 'password'])->assertNotFound();
    })->with(['student', 'learner', 'Admin']);

    it('shows the school name from the settings', function () {
        app(Settings::class)->set('school_name', 'Mabini Elementary School');

        $this->get(route('login.portal', 'teacher'))
            ->assertOk()
            ->assertSeeText('Mabini Elementary School');
    });

    it('sends signed-in users to their dashboard instead', function (UserRole $role, string $dashboardRoute) {
        $this->actingAs(User::factory()->{$role->value}()->create());

        $this->get(route('login.portal', 'teacher'))->assertRedirectToRoute($dashboardRoute);
    })->with([
        'administrator' => [UserRole::Admin, 'admin.dashboard'],
        'teacher' => [UserRole::Teacher, 'teacher.dashboard'],
        'parent' => [UserRole::Parent, 'parent.dashboard'],
    ]);
});

describe('store', function () {
    it('signs in to each portal and lands on its dashboard', function (UserRole $portal, string $dashboardRoute) {
        $user = User::factory()->{$portal->value}()->create(['email' => 'user@school.test']);

        $response = $this->post(route('login.portal.store', $portal->value), [
            'email' => 'user@school.test',
            'password' => 'password',
        ]);

        $response->assertRedirectToRoute($dashboardRoute)->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    })->with([
        'administrator' => [UserRole::Admin, 'admin.dashboard'],
        'teacher' => [UserRole::Teacher, 'teacher.dashboard'],
        'parent' => [UserRole::Parent, 'parent.dashboard'],
    ]);

    it('records the sign-in time without touching the profile update time', function () {
        $this->travelTo(Carbon::parse('2026-11-01 08:00:00'));
        $teacher = User::factory()->teacher()->create(['email' => 'teacher@school.test']);
        $this->travelTo(Carbon::parse('2026-11-13 07:30:15'));

        $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'password']);

        $teacher->refresh();
        expect($teacher->last_login_at->toDateTimeString())->toBe('2026-11-13 07:30:15')
            ->and($teacher->updated_at->toDateTimeString())->toBe('2026-11-01 08:00:00');
    });

    it('starts a new session on sign-in', function () {
        User::factory()->teacher()->create(['email' => 'teacher@school.test']);
        $this->get(route('login.portal', 'teacher'));
        $sessionIdBefore = session()->getId();

        $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'password']);

        expect(session()->getId())->not->toBe($sessionIdBefore);
    });

    it('rejects a wrong password, an unknown address and an account of another portal with the same message', function (string $email, string $password) {
        User::factory()->teacher()->create(['email' => 'teacher@school.test']);
        User::factory()->parent()->create(['email' => 'parent@school.test']);

        $response = $this->from(route('login.portal', 'teacher'))->post(route('login.portal.store', 'teacher'), [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertRedirect(route('login.portal', 'teacher'))
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.'])
            ->assertSessionHasInput('email', $email)
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    })->with([
        'wrong password' => ['teacher@school.test', 'wrong-password'],
        'unknown address' => ['nobody@school.test', 'password'],
        'parent account on the teacher portal' => ['parent@school.test', 'password'],
    ]);

    it('rejects every account on a portal of another role', function (UserRole $accountRole, UserRole $portal) {
        User::factory()->{$accountRole->value}()->create(['email' => 'user@school.test']);

        $this->post(route('login.portal.store', $portal->value), ['email' => 'user@school.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest();
    })->with([
        'administrator on teacher portal' => [UserRole::Admin, UserRole::Teacher],
        'administrator on parent portal' => [UserRole::Admin, UserRole::Parent],
        'teacher on administrator portal' => [UserRole::Teacher, UserRole::Admin],
        'teacher on parent portal' => [UserRole::Teacher, UserRole::Parent],
        'parent on administrator portal' => [UserRole::Parent, UserRole::Admin],
        'parent on teacher portal' => [UserRole::Parent, UserRole::Teacher],
    ]);

    it('never signs in a learner, whatever the portal', function (UserRole $portal) {
        $learner = User::factory()->student()->create();
        $learner->email = 'learner@school.test';
        $learner->save();

        $this->post(route('login.portal.store', $portal->value), ['email' => 'learner@school.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest();
    })->with([
        'administrator' => UserRole::Admin,
        'teacher' => UserRole::Teacher,
        'parent' => UserRole::Parent,
    ]);

    it('tells a deactivated account with the right password that it is deactivated', function () {
        User::factory()->teacher()->inactive()->create(['email' => 'teacher@school.test']);

        $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This account has been deactivated. Please contact your school administrator.']);

        $this->assertGuest();
    });

    it('does not reveal that an account is deactivated without the right password or on another portal', function (string $portal, string $password) {
        User::factory()->teacher()->inactive()->create(['email' => 'teacher@school.test']);

        $this->post(route('login.portal.store', $portal), ['email' => 'teacher@school.test', 'password' => $password])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        $this->assertGuest();
    })->with([
        'wrong password' => ['teacher', 'wrong-password'],
        'other portal' => ['parent', 'password'],
    ]);

    it('requires an email address and a password', function () {
        $this->post(route('login.portal.store', 'teacher'), [])
            ->assertSessionHasErrors([
                'email' => 'The email field is required.',
                'password' => 'The password field is required.',
            ]);
    });

    it('requires a valid email address', function () {
        $this->post(route('login.portal.store', 'teacher'), ['email' => 'not-an-email', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'The email field must be a valid email address.']);
    });

    it('remembers the device when asked', function () {
        $parent = User::factory()->parent()->create(['email' => 'parent@school.test']);

        $response = $this->post(route('login.portal.store', 'parent'), ['email' => 'parent@school.test', 'password' => 'password', 'remember' => '1']);

        $response->assertCookie(Auth::guard()->getRecallerName());
        expect($parent->refresh()->remember_token)->not->toBeNull();
    });

    it('does not remember the device by default', function () {
        User::factory()->parent()->create(['email' => 'parent@school.test']);

        $response = $this->post(route('login.portal.store', 'parent'), ['email' => 'parent@school.test', 'password' => 'password']);

        $response->assertCookieMissing(Auth::guard()->getRecallerName());
    });

    it('locks the address out for a minute after five failed attempts, even with the right password', function () {
        $this->freezeTime();
        Event::fake([Lockout::class]);
        User::factory()->teacher()->create(['email' => 'teacher@school.test']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'wrong-password']);
        }
        $response = $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'password']);

        $response->assertSessionHasErrors(['email' => 'Too many login attempts. Please try again in 60 seconds.']);
        $this->assertGuest();
        Event::assertDispatchedTimes(Lockout::class, 1);
    });

    it('counts every kind of failed attempt towards the lockout', function (string $portal, string $password, bool $isActive) {
        $this->freezeTime();
        $teacher = User::factory()->teacher()->create(['email' => 'teacher@school.test']);
        $teacher->is_active = $isActive;
        $teacher->save();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.portal.store', $portal), ['email' => 'teacher@school.test', 'password' => $password]);
        }

        $this->post(route('login.portal.store', $portal), ['email' => 'teacher@school.test', 'password' => $password])
            ->assertSessionHasErrors(['email' => 'Too many login attempts. Please try again in 60 seconds.']);
    })->with([
        'wrong password' => ['teacher', 'wrong-password', true],
        'other portal' => ['admin', 'password', true],
        'deactivated account' => ['teacher', 'password', false],
    ]);

    it('allows four failed attempts before a successful sign-in', function () {
        User::factory()->teacher()->create(['email' => 'teacher@school.test']);

        foreach (range(1, 4) as $attempt) {
            $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'wrong-password']);
        }
        $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'password'])
            ->assertRedirectToRoute('teacher.dashboard');

        $this->assertAuthenticated();
    });

    it('resets the failed attempts after a successful sign-in', function () {
        User::factory()->teacher()->create(['email' => 'teacher@school.test']);
        foreach (range(1, 4) as $attempt) {
            $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'wrong-password']);
        }
        $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'password']);
        $this->post(route('logout'));

        foreach (range(1, 4) as $attempt) {
            $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'wrong-password']);
        }
        $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'password'])
            ->assertRedirectToRoute('teacher.dashboard');
    });

    it('locks out the address whatever its letter case', function () {
        $this->freezeTime();
        User::factory()->teacher()->create(['email' => 'teacher@school.test']);
        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.portal.store', 'teacher'), ['email' => 'Teacher@School.test', 'password' => 'wrong-password']);
        }

        $response = $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'password']);

        $response->assertSessionHasErrors(['email' => 'Too many login attempts. Please try again in 60 seconds.']);
        $this->assertGuest();
    });

    it('keeps the lockout to the IP address the failed attempts came from', function () {
        User::factory()->teacher()->create(['email' => 'teacher@school.test']);
        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'wrong-password']);
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'password']);

        $response->assertRedirectToRoute('teacher.dashboard');
        $this->assertAuthenticated();
    });

    it('returns to the page first asked for when it belongs to the portal', function () {
        User::factory()->teacher()->create(['email' => 'teacher@school.test']);
        $this->get('/teacher/quizzes?status=draft')->assertRedirectToRoute('login');

        $response = $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'password']);

        $response->assertRedirect(url('/teacher/quizzes?status=draft'));
    });

    it('returns to the forum and the account settings from any portal', function (UserRole $portal, string $path) {
        User::factory()->{$portal->value}()->create(['email' => 'user@school.test']);
        $this->withSession(['url.intended' => url($path)]);

        $response = $this->post(route('login.portal.store', $portal->value), ['email' => 'user@school.test', 'password' => 'password']);

        $response->assertRedirect(url($path));
    })->with([
        'administrator to the forum' => [UserRole::Admin, '/forum/threads/7'],
        'teacher to the forum' => [UserRole::Teacher, '/forum'],
        'parent to the forum' => [UserRole::Parent, '/forum/categories/healthy-recipes'],
        'parent to the account settings' => [UserRole::Parent, '/account'],
        'administrator to its own area' => [UserRole::Admin, '/admin/users'],
        'parent to its own area' => [UserRole::Parent, '/parent/children/3'],
    ]);

    it('ignores a page first asked for outside the portal and lands on the dashboard', function (string $intendedUrl) {
        User::factory()->teacher()->create(['email' => 'teacher@school.test']);
        $this->withSession(['url.intended' => $intendedUrl]);

        $response = $this->post(route('login.portal.store', 'teacher'), ['email' => 'teacher@school.test', 'password' => 'password']);

        $response->assertRedirectToRoute('teacher.dashboard')->assertSessionMissing('url.intended');
    })->with([
        'administrator area' => fn () => url('/admin/users'),
        'parent area' => fn () => url('/parent'),
        'look-alike path' => fn () => url('/teachers-lounge'),
        'site root' => fn () => url('/'),
        'other host' => 'https://evil.example/teacher/quizzes',
    ]);
});

describe('destroy', function () {
    it('signs out and returns to the portal list with a message', function () {
        $this->actingAs(User::factory()->teacher()->create());
        $tokenBefore = session()->token();

        $response = $this->post(route('logout'));

        $response->assertRedirectToRoute('login')->assertSessionHas('status', 'You have been signed out.');
        $this->assertGuest();
        expect(session()->token())->not->toBe($tokenBefore);
    });

    it('sends guests to the sign-in page', function () {
        $this->post(route('logout'))->assertRedirectToRoute('login');
    });
});
