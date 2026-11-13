<?php

namespace Database\Seeders\Support\Catalog;

use App\Enums\LearningMode;
use Database\Seeders\Support\LearnerArchetype;
use Illuminate\Support\Str;

/**
 * The people of the demo school: staff, sections, classrooms, the demo family and the name pools.
 */
final class PeopleCatalog
{
    /**
     * The password of every demo account.
     */
    public const PASSWORD = 'password';

    /**
     * The administrator account.
     *
     * @var array{name: string, email: string, phone: string, created_on: string}
     */
    public const ADMIN = [
        'name' => 'Josefina A. Ramos',
        'email' => 'admin@nutrimind.test',
        'phone' => '09171230001',
        'created_on' => '2026-05-04',
    ];

    /**
     * The e-mail address of the demo teacher.
     */
    public const DEMO_TEACHER_EMAIL = 'teacher@nutrimind.test';

    /**
     * The e-mail address of the demo parent.
     */
    public const DEMO_PARENT_EMAIL = 'parent@nutrimind.test';

    /**
     * The day the administrator assigned subjects to the teachers.
     */
    public const SUBJECT_ASSIGNMENT_DAY = '2026-05-18';

    /**
     * The sections of the active school year: grade level, number of learners and homeroom.
     *
     * @var array<string, array{grade: int, size: int, room: string}>
     */
    public const SECTIONS = [
        'Sampaguita' => ['grade' => 5, 'size' => 24, 'room' => 'Room 105'],
        'Ilang-Ilang' => ['grade' => 5, 'size' => 23, 'room' => 'Room 106'],
        'Gumamela' => ['grade' => 5, 'size' => 23, 'room' => 'Room 107'],
        'Narra' => ['grade' => 6, 'size' => 24, 'room' => 'Room 201'],
        'Molave' => ['grade' => 6, 'size' => 23, 'room' => 'Room 202'],
        'Mahogany' => ['grade' => 6, 'size' => 23, 'room' => 'Room 203'],
    ];

    /**
     * The section whose learners were last year's Health 5 – Sampaguita class (the archived classroom).
     */
    public const PREVIOUS_YEAR_SECTION = 'Narra';

    /**
     * The school IDs of the schools some learners transferred from (the first six digits of their LRN).
     *
     * @var list<string>
     */
    public const TRANSFEREE_SCHOOL_IDS = ['136593', '136610', '109832', '301245'];

    /**
     * The demo family: the demo parent and two children in different grades.
     *
     * @var array{surname: string, guardian: array{first_name: string, email: string, phone: string, relationship: string}, children: list<array{first_name: string, sex: string, section: string}>}
     */
    public const DEMO_FAMILY = [
        'surname' => 'Navarro',
        'guardian' => ['first_name' => 'Ana', 'email' => self::DEMO_PARENT_EMAIL, 'phone' => '09171230099', 'relationship' => 'mother'],
        'children' => [
            ['first_name' => 'Sofia', 'sex' => 'female', 'section' => 'Sampaguita'],
            ['first_name' => 'Miguel', 'sex' => 'male', 'section' => 'Narra'],
        ],
    ];

    /**
     * Learners who enrolled this week and have not opened the game yet.
     *
     * @var list<array{first_name: string, surname: string, sex: string, section: string, guardian: string|null}>
     */
    public const LATE_ENROLLEES = [
        ['first_name' => 'Carlo', 'surname' => 'Manalastas', 'sex' => 'male', 'section' => 'Ilang-Ilang', 'guardian' => 'Teresa'],
        ['first_name' => 'Janelle', 'surname' => 'Umali', 'sex' => 'female', 'section' => 'Molave', 'guardian' => null],
    ];

    /**
     * First names of boys.
     *
     * @var list<string>
     */
    public const BOY_NAMES = [
        'Gabriel', 'Angelo', 'Joshua', 'John Paul', 'Mark Anthony', 'Nathaniel', 'Ethan', 'Jacob', 'Elijah', 'Adrian',
        'Rafael', 'Joaquin', 'Andrei', 'Kyle', 'Vincent', 'Christian', 'Jericho', 'Matthew', 'Lance', 'Xander',
        'Paolo', 'Francis', 'Daniel', 'Aaron', 'Jhon Lloyd', 'Prince', 'Kenji', 'Zion', 'Juan Carlo', 'Renz',
        'Ivan', 'Jasper', 'Sean', 'Marco', 'Enzo', 'Lorenzo', 'Benedict', 'Emmanuel', 'Raphael', 'Kristoffer',
        'Jerome', 'Bryan', 'Cedric', 'Dominic', 'Ezekiel', 'Gian', 'Harvey', 'Ian', 'Jairus', 'Kurt',
        'Luis', 'Nico', 'Oliver', 'Patrick', 'Ryle', 'Samuel', 'Tristan', 'Vince', 'Zachary', 'Clarence',
        'Rhett', 'Seth', 'Timothy', 'Wesley', 'Yuan', 'Elian', 'Josiah', 'Karl', 'Miko', 'Noah',
    ];

    /**
     * First names of girls.
     *
     * @var list<string>
     */
    public const GIRL_NAMES = [
        'Althea', 'Princess', 'Angel', 'Jasmine', 'Kyla', 'Andrea', 'Bea', 'Mikaela', 'Samantha', 'Chloe',
        'Ella', 'Nicole', 'Ashley', 'Kate', 'Trisha', 'Hannah', 'Aubrey', 'Precious', 'Zia', 'Ysabel',
        'Lianne', 'Rhian', 'Faith', 'Mariel', 'Jillian', 'Danica', 'Carmela', 'Bianca', 'Erica', 'Gwyneth',
        'Kristine', 'Leah', 'Maxine', 'Nathalie', 'Pia', 'Queenie', 'Reign', 'Stephanie', 'Tricia', 'Venice',
        'Yna', 'Alyssa', 'Bettina', 'Camille', 'Denise', 'Elaine', 'Francine', 'Gianna', 'Hazel', 'Isabelle',
        'Jamie', 'Katrina', 'Lara', 'Margarette', 'Nadine', 'Patricia', 'Rose Ann', 'Shaira', 'Thea', 'Vanessa',
        'Mary Grace', 'Angelica', 'Clarisse', 'Dianne', 'Joanna', 'Ma. Teresa', 'Kim', 'Louise', 'Sabrina', 'Tiffany',
    ];

    /**
     * Family names of the learners' families (the staff's family names are left out to avoid confusion).
     *
     * @var list<string>
     */
    public const SURNAMES = [
        'Dela Cruz', 'Reyes', 'Cruz', 'Garcia', 'Torres', 'Flores', 'Rivera', 'Gonzales', 'Lopez', 'Salazar',
        'Mercado', 'Aguilar', 'Domingo', 'Pascual', 'Soriano', 'Valdez', 'De Leon', 'Del Rosario', 'Santiago', 'Panganiban',
        'Tolentino', 'Evangelista', 'Magbanua', 'Dimaculangan', 'Lacson', 'Ocampo', 'Marquez', 'Sison', 'Galang', 'Concepcion',
        'Javier', 'Morales', 'Robles', 'Velasco', 'Samonte', 'Bernardo', 'Mangubat', 'Cabrera', 'Austria', 'Abad',
        'Alcantara', 'Agustin', 'Dizon', 'Esguerra', 'Ferrer', 'Ignacio', 'Jimenez', 'Lim', 'Macaraeg', 'Nepomuceno',
        'Padilla', 'Quiambao', 'Roxas', 'Sarmiento', 'Tan', 'Vergara', 'Yap', 'Zamora', 'Pineda', 'Rosales',
        'Serrano', 'Tiongson', 'Belmonte', 'Cortez', 'Dumalagan', 'Enriquez', 'Fajardo', 'Hernandez', 'Ilagan', 'Katigbak',
        'Legaspi', 'Mallari', 'Olivarez', 'Perez', 'Quizon', 'Ramirez', 'Sevilla', 'Tamayo', 'Uy', 'Vasquez',
    ];

    /**
     * First names of mothers and other female guardians.
     *
     * @var list<string>
     */
    public const WOMEN_NAMES = [
        'Marites', 'Cristina', 'Analyn', 'Jocelyn', 'Rachelle', 'Lorna', 'Gemma', 'Arlene', 'Sheryl', 'Maricar',
        'Divina', 'Leonora', 'Rosalie', 'Marilou', 'Grace', 'Joy', 'Charmaine', 'Michelle', 'April', 'Jennifer',
        'Liezl', 'Melanie', 'Rhea', 'Sheila', 'Annabelle', 'Lovely', 'Mylene', 'Haydee', 'Evangeline', 'Imelda',
        'Josephine', 'Lilibeth', 'Maribel', 'Nenita', 'Ofelia', 'Rosemarie', 'Vilma', 'Yolanda', 'Zenaida', 'Bernadette',
        'Catherine', 'Donna', 'Edna', 'Gina', 'Hilda', 'Irene', 'Janet', 'Karen', 'Lea', 'Mercy',
    ];

    /**
     * First names of fathers and other male guardians.
     *
     * @var list<string>
     */
    public const MEN_NAMES = [
        'Rodel', 'Ronaldo', 'Edgardo', 'Reynaldo', 'Romeo', 'Joel', 'Rommel', 'Danilo', 'Ferdinand', 'Allan',
        'Noel', 'Ariel', 'Jerome', 'Rogelio', 'Eduardo', 'Ricardo', 'Wilfredo', 'Marlon', 'Jayson', 'Rey',
        'Benjie', 'Arman', 'Ramil', 'Nestor', 'Elmer', 'Jimmy', 'Roberto', 'Antonio', 'Efren', 'Gilbert',
        'Isagani', 'Leandro', 'Mario', 'Oscar', 'Ruben', 'Tirso', 'Virgilio', 'Alvin', 'Bong', 'Cesar',
    ];

    /**
     * First names of grandmothers.
     *
     * @var list<string>
     */
    public const GRANDMOTHER_NAMES = ['Corazon', 'Estrella', 'Remedios', 'Teodora', 'Purificacion', 'Lourdes', 'Milagros', 'Rosario', 'Consolacion', 'Felicidad'];

    /**
     * First names of grandfathers.
     *
     * @var list<string>
     */
    public const GRANDFATHER_NAMES = ['Ernesto', 'Bienvenido', 'Alfredo', 'Crisanto', 'Leopoldo', 'Teodoro', 'Florencio', 'Gregorio'];

    /**
     * First names of older siblings who act as guardians.
     *
     * @var list<string>
     */
    public const OLDER_SIBLING_NAMES = ['Kristel', 'Jerald', 'Aileen', 'Mark Joseph', 'Camille', 'Ronald'];

    /**
     * How often each relationship is the primary guardian of a family with a parent account.
     *
     * @var array<string, float>
     */
    public const RELATIONSHIP_WEIGHTS = [
        'mother' => 0.62,
        'father' => 0.18,
        'grandparent' => 0.08,
        'guardian' => 0.05,
        'relative' => 0.04,
        'sibling' => 0.02,
        'other' => 0.01,
    ];

    /**
     * Get the teachers keyed by a short key: profile, assigned subject codes and account details.
     *
     * The last login is given in days before now, or as a fixed moment for the retired teacher, whose account was
     * deactivated after retirement.
     *
     * @return array<string, array{name: string, email: string, phone: string, subjects: list<string>, created_on: string, last_login: float|string, deactivated_at: string|null}>
     */
    public static function teachers(): array
    {
        return [
            'santos' => [
                'name' => 'Maricel D. Santos', 'email' => self::DEMO_TEACHER_EMAIL, 'phone' => '09171230002',
                'subjects' => ['HEALTH', 'SCIENCE'], 'created_on' => '2026-05-11', 'last_login' => 0.08, 'deactivated_at' => null,
            ],
            'villanueva' => [
                'name' => 'Jonathan P. Villanueva', 'email' => 'jonathan.villanueva@nutrimind.test', 'phone' => '09181230003',
                'subjects' => ['SCIENCE'], 'created_on' => '2026-05-11', 'last_login' => 0.9, 'deactivated_at' => null,
            ],
            'bautista' => [
                'name' => 'Rowena C. Bautista', 'email' => 'rowena.bautista@nutrimind.test', 'phone' => '09271230004',
                'subjects' => ['EPP-HE'], 'created_on' => '2026-05-11', 'last_login' => 1.6, 'deactivated_at' => null,
            ],
            'mendoza' => [
                'name' => 'Arnel B. Mendoza', 'email' => 'arnel.mendoza@nutrimind.test', 'phone' => '09351230005',
                'subjects' => ['PE', 'HEALTH'], 'created_on' => '2026-05-12', 'last_login' => 0.3, 'deactivated_at' => null,
            ],
            'fernandez' => [
                'name' => 'Liza M. Fernandez', 'email' => 'liza.fernandez@nutrimind.test', 'phone' => '09451230006',
                'subjects' => ['HEALTH', 'EPP-HE'], 'created_on' => '2026-05-12', 'last_login' => 2.8, 'deactivated_at' => null,
            ],
            'aquino' => [
                'name' => 'Dennis R. Aquino', 'email' => 'dennis.aquino@nutrimind.test', 'phone' => '09561230007',
                'subjects' => [], 'created_on' => '2026-10-26', 'last_login' => 6.2, 'deactivated_at' => null,
            ],
            'gutierrez' => [
                'name' => 'Ramon T. Gutierrez', 'email' => 'ramon.gutierrez@nutrimind.test', 'phone' => '09171230008',
                'subjects' => [], 'created_on' => '2025-05-12', 'last_login' => '2026-03-27 16:42:00', 'deactivated_at' => '2026-05-29 14:05:00',
            ],
        ];
    }

    /**
     * Get the e-mail address of a teacher by key.
     */
    public static function teacherEmail(string $key): string
    {
        return self::teachers()[$key]['email'];
    }

    /**
     * Get the classrooms of the active school year: section, subject code, teacher key and learning mode.
     *
     * @return list<array{section: string, subject: string, teacher: string, mode: LearningMode}>
     */
    public static function classrooms(): array
    {
        $plan = [
            ['Sampaguita', 'HEALTH', 'santos', LearningMode::LearnerChoice],
            ['Sampaguita', 'SCIENCE', 'santos', LearningMode::Automatic],
            ['Sampaguita', 'EPP-HE', 'bautista', LearningMode::Manual],
            ['Sampaguita', 'PE', 'mendoza', LearningMode::Automatic],
            ['Ilang-Ilang', 'HEALTH', 'santos', LearningMode::Automatic],
            ['Ilang-Ilang', 'SCIENCE', 'villanueva', LearningMode::Automatic],
            ['Ilang-Ilang', 'PE', 'mendoza', LearningMode::LearnerChoice],
            ['Gumamela', 'HEALTH', 'fernandez', LearningMode::Manual],
            ['Gumamela', 'SCIENCE', 'villanueva', LearningMode::Automatic],
            ['Narra', 'HEALTH', 'santos', LearningMode::Automatic],
            ['Narra', 'SCIENCE', 'santos', LearningMode::Manual],
            ['Narra', 'EPP-HE', 'bautista', LearningMode::Automatic],
            ['Molave', 'HEALTH', 'mendoza', LearningMode::Automatic],
            ['Molave', 'SCIENCE', 'villanueva', LearningMode::LearnerChoice],
            ['Molave', 'EPP-HE', 'fernandez', LearningMode::Automatic],
            ['Mahogany', 'HEALTH', 'fernandez', LearningMode::Automatic],
            ['Mahogany', 'PE', 'mendoza', LearningMode::Manual],
        ];

        return array_map(
            fn (array $classroom): array => ['section' => $classroom[0], 'subject' => $classroom[1], 'teacher' => $classroom[2], 'mode' => $classroom[3]],
            $plan,
        );
    }

    /**
     * Get the archived classroom of the previous school year.
     *
     * @return array{section: string, grade: int, subject: string, teacher: string, mode: LearningMode, room: string, created_on: string, enrolled_on: string, archived_at: string}
     */
    public static function archivedClassroom(): array
    {
        return [
            'section' => 'Sampaguita',
            'grade' => 5,
            'subject' => 'HEALTH',
            'teacher' => 'santos',
            'mode' => LearningMode::Automatic,
            'room' => 'Room 105',
            'created_on' => '2025-06-09',
            'enrolled_on' => '2025-06-16',
            'archived_at' => '2026-04-17 10:15:00',
        ];
    }

    /**
     * Get the room of a classroom: Home Economics and PE have their own venues, other subjects use the homeroom.
     */
    public static function roomFor(string $subjectCode, string $section): string
    {
        return match ($subjectCode) {
            'EPP-HE' => 'Home Economics Room',
            'PE' => 'Covered Court',
            default => self::SECTIONS[$section]['room'],
        };
    }

    /**
     * Get the learners whose profile is fixed so the demo family tells a clear story.
     *
     * @return array<string, array{archetype: LearnerArchetype, growth?: float}>
     */
    public static function profileOverrides(): array
    {
        return [
            self::usernameFor('Sofia', self::DEMO_FAMILY['surname']) => ['archetype' => LearnerArchetype::Strong, 'growth' => 0.004],
            self::usernameFor('Miguel', self::DEMO_FAMILY['surname']) => ['archetype' => LearnerArchetype::Developing, 'growth' => 0.018],
        ];
    }

    /**
     * Build a learner's username from the first name and family name (lowercase, alpha-dash safe).
     */
    public static function usernameFor(string $firstName, string $surname): string
    {
        $slug = fn (string $name): string => Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9]/', '')->toString();

        return Str::limit($slug($firstName).'_'.$slug($surname), 26, '');
    }

    /**
     * Get the first name of a learner or guardian from the full name.
     */
    public static function firstNameOf(string $fullName): string
    {
        $surnames = [...self::SURNAMES, self::DEMO_FAMILY['surname'], ...array_column(self::LATE_ENROLLEES, 'surname')];

        usort($surnames, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($surnames as $surname) {
            if (Str::endsWith($fullName, ' '.$surname)) {
                return Str::beforeLast($fullName, ' '.$surname);
            }
        }

        return Str::beforeLast($fullName, ' ');
    }
}
