<?php

namespace Database\Factories;

use App\Enums\GradeLevel;
use App\Enums\GuardianRelationship;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state: an active, verified teacher.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => null,
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Teacher,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => fake()->numerify('09#########'),
            'is_active' => true,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->state(['role' => UserRole::Admin]);
    }

    /**
     * Indicate that the user is a teacher.
     */
    public function teacher(): static
    {
        return $this->state(['role' => UserRole::Teacher]);
    }

    /**
     * Indicate that the user is a parent or guardian.
     */
    public function parent(): static
    {
        return $this->state(['role' => UserRole::Parent]);
    }

    /**
     * Indicate that the user is a learner with a learner profile, optionally in the given grade.
     */
    public function student(?GradeLevel $grade = null): static
    {
        $profile = $grade === null
            ? StudentProfile::factory()
            : StudentProfile::factory()->state(['grade_level' => $grade]);

        return $this->studentWithoutProfile()->has($profile, 'studentProfile');
    }

    /**
     * Indicate that the user is a learner account that has no learner profile yet.
     */
    public function studentWithoutProfile(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Student,
            'username' => fake()->unique()->userName(),
            'email' => null,
            'email_verified_at' => null,
            'phone' => null,
        ]);
    }

    /**
     * Indicate that the account is deactivated.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assign the given subjects to the teacher after creating it (a new subject when none are given).
     */
    public function teaching(Subject ...$subjects): static
    {
        return $this->afterCreating(function (User $teacher) use ($subjects): void {
            $teacher->assignedSubjects()->attach(
                $subjects === []
                    ? [Subject::factory()->create()->getKey()]
                    : array_map(fn (Subject $subject): int => $subject->getKey(), $subjects),
            );
        });
    }

    /**
     * Enroll the learner in the given classrooms after creating it.
     */
    public function enrolledIn(Classroom ...$classrooms): static
    {
        return $this->afterCreating(function (User $student) use ($classrooms): void {
            $student->enrolledClassrooms()->attach(
                array_map(fn (Classroom $classroom): int => $classroom->getKey(), $classrooms),
                ['enrolled_at' => now()],
            );
        });
    }

    /**
     * Link the parent or guardian to the learner after creating it.
     */
    public function guardianOf(User $student, GuardianRelationship $relationship = GuardianRelationship::Guardian): static
    {
        return $this->afterCreating(function (User $guardian) use ($student, $relationship): void {
            $guardian->linkedStudents()->attach($student->getKey(), ['relationship' => $relationship]);
        });
    }
}
