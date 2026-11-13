<?php

namespace App\Services;

use App\Enums\GradeLevel;
use App\Enums\Sex;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates learner accounts (user + learner profile + optional enrollment) with readable generated credentials.
 *
 * Callers validate the input first (Form Requests / the CSV importer); this service only normalizes it.
 */
class StudentAccounts
{
    /**
     * The longest username the service generates.
     */
    public const USERNAME_MAX_LENGTH = 30;

    /**
     * The username used when a name has no letters or digits that survive transliteration.
     */
    public const USERNAME_FALLBACK = 'learner';

    /**
     * The food words generated passwords start with (lowercase ASCII, at least 4 letters).
     *
     * @var list<string>
     */
    public const PASSWORD_WORDS = [
        'apple', 'mango', 'banana', 'papaya', 'guava', 'melon', 'lemon', 'grape', 'peach', 'pear',
        'plum', 'cherry', 'kiwi', 'orange', 'pomelo', 'calamansi', 'lanzones', 'rambutan', 'chico', 'atis',
        'coconut', 'avocado', 'saba', 'carrot', 'potato', 'tomato', 'squash', 'pechay', 'okra', 'cabbage',
        'lettuce', 'spinach', 'onion', 'garlic', 'ginger', 'radish', 'eggplant', 'kangkong', 'malunggay', 'sitaw',
        'patola', 'corn', 'rice', 'bread', 'oats', 'beans', 'peanut', 'almond', 'cashew', 'honey',
        'yogurt', 'cheese', 'milk', 'tofu', 'fish',
    ];

    /**
     * How many digits a numeric username suffix may have while the single prefix lookup stays exhaustive.
     */
    private const USERNAME_SUFFIX_MAX_DIGITS = 6;

    /**
     * Generational suffixes left out of usernames.
     *
     * @var list<string>
     */
    private const NAME_SUFFIXES = ['jr', 'sr', 'ii', 'iii', 'iv'];

    /**
     * Words that belong to the surname that follows them ("Dela Cruz", "de los Reyes", "San Juan").
     *
     * @var list<string>
     */
    private const SURNAME_PARTICLES = [
        'da', 'das', 'de', 'del', 'dela', 'della', 'delos', 'der', 'di', 'do', 'dos', 'du',
        'la', 'las', 'le', 'los', 'san', 'santa', 'santo', 'sta', 'sto', 'van', 'von',
    ];

    /**
     * Create a learner account, its learner profile and, when a classroom is given, its enrollment — all or nothing.
     *
     * A blank username or password is generated. Keys other than the documented ones are ignored, and the role is
     * always Student.
     *
     * @param  array{name: string, grade_level: int|GradeLevel, lrn?: string|null, sex?: Sex|string|null, birthdate?: DateTimeInterface|string|null, username?: string|null, password?: string|null}  $attributes
     */
    public function create(array $attributes, ?Classroom $classroom = null): CreatedStudentAccount
    {
        return DB::transaction(function () use ($attributes, $classroom): CreatedStudentAccount {
            $name = Str::squish((string) $attributes['name']);
            $plainPassword = filled($attributes['password'] ?? null) ? (string) $attributes['password'] : $this->generatePassword();

            $student = new User([
                'name' => $name,
                'username' => filled($attributes['username'] ?? null) ? trim((string) $attributes['username']) : $this->generateUsername($name),
                'password' => $plainPassword,
            ]);
            $student->role = UserRole::Student;
            $student->save();

            $grade = $attributes['grade_level'];

            $profile = $student->studentProfile()->create([
                'lrn' => $this->blankToNull($attributes['lrn'] ?? null),
                'grade_level' => $grade instanceof GradeLevel ? $grade : GradeLevel::from((int) $grade),
                'sex' => $this->blankToNull($attributes['sex'] ?? null),
                'birthdate' => $this->blankToNull($attributes['birthdate'] ?? null),
            ]);
            $student->setRelation('studentProfile', $profile);

            $enrolled = $classroom !== null && $this->enroll($student, $classroom);

            return new CreatedStudentAccount($student, $plainPassword, $enrolled);
        });
    }

    /**
     * Enroll the learner in the classroom (enrolled_at = now) unless already enrolled; true when a new enrollment was made.
     */
    public function enroll(User $student, Classroom $classroom): bool
    {
        if ($classroom->students()->whereKey($student->getKey())->exists()) {
            return false;
        }

        $classroom->students()->attach($student->getKey(), ['enrolled_at' => now()]);

        return true;
    }

    /**
     * Generate an unused username from a name: the given name's initial plus the surname, ASCII lowercase.
     *
     * "Juan Dela Cruz" → "jdelacruz", "José P. Rizal Jr." → "jrizal", "Dela Cruz, Juan" → "jdelacruz". Accents are
     * transliterated, punctuation dropped, the result is cut to 30 characters, and a taken username (compared
     * case-insensitively) gets the smallest free numeric suffix: "jdelacruz2", "jdelacruz3"… One query finds every
     * candidate, as all of them share the base's first 24 characters.
     */
    public function generateUsername(string $name): string
    {
        $base = $this->usernameBase($name);
        $base = $base === '' ? self::USERNAME_FALLBACK : $base;

        $taken = User::query()
            ->whereLike('username', substr($base, 0, self::USERNAME_MAX_LENGTH - self::USERNAME_SUFFIX_MAX_DIGITS).'%')
            ->pluck('username')
            ->map(fn (string $username): string => Str::lower($username))
            ->flip();

        $candidate = $base;

        for ($suffix = 2; $taken->has($candidate); $suffix++) {
            $candidate = substr($base, 0, self::USERNAME_MAX_LENGTH - strlen((string) $suffix)).$suffix;
        }

        return $candidate;
    }

    /**
     * Generate a readable password: a food word, a dash and 4 digits ("mango-4821").
     */
    public function generatePassword(): string
    {
        $word = self::PASSWORD_WORDS[random_int(0, count(self::PASSWORD_WORDS) - 1)];

        return $word.'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Give the learner a new generated password, sign out remembered sessions, and return the password to show once.
     *
     * @throws InvalidArgumentException when the account is not a learner account.
     */
    public function resetPassword(User $student): string
    {
        if (! $student->isStudent()) {
            throw new InvalidArgumentException('Only learner accounts can get a generated learner password.');
        }

        $plainPassword = $this->generatePassword();

        $student->password = $plainPassword;
        $student->setRememberToken(Str::random(60));
        $student->save();

        return $plainPassword;
    }

    /**
     * Build the username base of a name: initial of the given name + surname words, or the only word.
     */
    private function usernameBase(string $name): string
    {
        $ascii = Str::lower(Str::ascii($name));

        [$surnamePart, $givenPart] = str_contains($ascii, ',') ? explode(',', $ascii, 2) : ['', $ascii];

        $surnameWords = $this->usernameWords($surnamePart);
        $givenWords = $this->usernameWords($givenPart);

        if ($surnameWords === []) {
            if (count($givenWords) <= 1) {
                return $givenWords[0] ?? '';
            }

            $surnameStart = count($givenWords) - 1;

            while ($surnameStart > 1 && in_array($givenWords[$surnameStart - 1], self::SURNAME_PARTICLES, true)) {
                $surnameStart--;
            }

            $surnameWords = array_slice($givenWords, $surnameStart);
            $givenWords = array_slice($givenWords, 0, $surnameStart);
        }

        $initial = isset($givenWords[0]) ? $givenWords[0][0] : '';

        return substr($initial.implode('', $surnameWords), 0, self::USERNAME_MAX_LENGTH);
    }

    /**
     * Split lowercase ASCII text into words of letters and digits, leaving out generational suffixes after the first word.
     *
     * @return list<string>
     */
    private function usernameWords(string $text): array
    {
        $words = array_values(array_filter(
            array_map(
                fn (string $word): string => (string) preg_replace('/[^a-z0-9]/', '', $word),
                preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            ),
            fn (string $word): bool => $word !== '',
        ));

        return array_values(array_filter(
            $words,
            fn (string $word, int $index): bool => $index === 0 || ! in_array($word, self::NAME_SUFFIXES, true),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * Turn blank strings into null.
     */
    private function blankToNull(mixed $value): mixed
    {
        return blank($value) ? null : $value;
    }
}
