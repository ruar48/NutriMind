<?php

namespace Database\Factories;

use App\Enums\GradeLevel;
use App\Enums\LearningMode;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\User;
use Database\Factories\Concerns\AssignsSubjectToTeacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    use AssignsSubjectToTeacher;

    /**
     * Section names in the style of Philippine public schools.
     *
     * @var list<string>
     */
    private const SECTIONS = ['Sampaguita', 'Rosal', 'Ilang-Ilang', 'Gumamela', 'Camia', 'Rizal', 'Mabini', 'Bonifacio', 'Luna', 'Silang'];

    /**
     * The class codes this factory instance handed out, so classrooms made in one batch never share a code.
     *
     * @var list<string>
     */
    private array $issuedClassCodes = [];

    /**
     * Define the model's default state: an active classroom whose teacher teaches its subject.
     *
     * The class code is assigned here rather than left to the model's "creating" hook, so classrooms
     * can be created while model events are disabled (WithoutModelEvents seeders, Event::fake()).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_code' => fn (): string => $this->issuedClassCodes[] = Classroom::generateClassCode($this->issuedClassCodes),
            'school_year_id' => SchoolYear::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory()->teacher(),
            'grade_level' => fake()->randomElement(GradeLevel::cases()),
            'section' => fake()->randomElement(self::SECTIONS),
            'name' => fn (array $attributes): string => $this->gradeLevel($attributes['grade_level'])->label().' – '.$attributes['section'],
            'room' => 'Room '.fake()->numberBetween(101, 320),
            'description' => fake()->sentence(),
            'learning_mode' => LearningMode::Automatic,
            'archived_at' => null,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Classroom $classroom): void {
            $this->assignSubjectToTeacher($classroom);
        });
    }

    /**
     * Indicate that the classroom is archived.
     */
    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }

    /**
     * Resolve a grade level attribute that may be an enum or its integer value.
     */
    private function gradeLevel(GradeLevel|int $grade): GradeLevel
    {
        return $grade instanceof GradeLevel ? $grade : GradeLevel::from($grade);
    }
}
