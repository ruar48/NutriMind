<?php

use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

dataset('application tables', [[[
    'users', 'student_profiles', 'school_years', 'academic_terms', 'subjects', 'subject_teacher', 'classrooms',
    'classroom_student', 'guardian_student', 'learning_modules', 'learning_activities', 'missions', 'rewards',
    'reward_student', 'quizzes', 'classroom_quiz', 'question_bank_items', 'quiz_questions', 'quiz_attempts',
    'quiz_attempt_answers', 'mission_progress', 'activity_progress', 'lesson_plans', 'learning_activity_lesson_plan',
    'lesson_plan_mission', 'lesson_plan_quiz', 'learner_feedback', 'forum_categories', 'forum_threads',
    'forum_replies', 'forum_reports', 'settings',
]]]);

it('defines exactly the foreign keys and delete actions of the schema contract', function (array $tables) {
    $foreignKeys = collect($tables)
        ->flatMap(fn (string $table): array => array_map(
            fn (array $foreignKey): string => sprintf(
                '%s.%s -> %s (%s)',
                $table,
                implode(',', $foreignKey['columns']),
                $foreignKey['foreign_table'],
                $foreignKey['on_delete'],
            ),
            Schema::getForeignKeys($table),
        ))
        ->sort()
        ->values()
        ->all();

    expect($foreignKeys)->toBe(collect([
        'student_profiles.user_id -> users (cascade)',
        'academic_terms.school_year_id -> school_years (cascade)',
        'subject_teacher.subject_id -> subjects (cascade)',
        'subject_teacher.teacher_id -> users (cascade)',
        'subject_teacher.assigned_by -> users (set null)',
        'classrooms.school_year_id -> school_years (restrict)',
        'classrooms.subject_id -> subjects (restrict)',
        'classrooms.teacher_id -> users (restrict)',
        'classroom_student.classroom_id -> classrooms (cascade)',
        'classroom_student.student_id -> users (cascade)',
        'guardian_student.guardian_id -> users (cascade)',
        'guardian_student.student_id -> users (cascade)',
        'learning_modules.subject_id -> subjects (restrict)',
        'learning_activities.learning_module_id -> learning_modules (cascade)',
        'missions.subject_id -> subjects (restrict)',
        'missions.learning_module_id -> learning_modules (set null)',
        'reward_student.reward_id -> rewards (cascade)',
        'reward_student.student_id -> users (cascade)',
        'quizzes.teacher_id -> users (restrict)',
        'quizzes.subject_id -> subjects (restrict)',
        'quizzes.learning_module_id -> learning_modules (set null)',
        'quizzes.academic_term_id -> academic_terms (set null)',
        'classroom_quiz.classroom_id -> classrooms (cascade)',
        'classroom_quiz.quiz_id -> quizzes (cascade)',
        'question_bank_items.teacher_id -> users (set null)',
        'question_bank_items.subject_id -> subjects (restrict)',
        'question_bank_items.learning_module_id -> learning_modules (set null)',
        'quiz_questions.quiz_id -> quizzes (cascade)',
        'quiz_questions.question_bank_item_id -> question_bank_items (set null)',
        'quiz_attempts.quiz_id -> quizzes (cascade)',
        'quiz_attempts.student_id -> users (cascade)',
        'quiz_attempts.classroom_id -> classrooms (set null)',
        'quiz_attempt_answers.quiz_attempt_id -> quiz_attempts (cascade)',
        'quiz_attempt_answers.quiz_question_id -> quiz_questions (cascade)',
        'mission_progress.mission_id -> missions (cascade)',
        'mission_progress.student_id -> users (cascade)',
        'activity_progress.learning_activity_id -> learning_activities (cascade)',
        'activity_progress.student_id -> users (cascade)',
        'lesson_plans.teacher_id -> users (cascade)',
        'lesson_plans.subject_id -> subjects (restrict)',
        'lesson_plans.academic_term_id -> academic_terms (set null)',
        'lesson_plans.learning_module_id -> learning_modules (set null)',
        'learning_activity_lesson_plan.lesson_plan_id -> lesson_plans (cascade)',
        'learning_activity_lesson_plan.learning_activity_id -> learning_activities (cascade)',
        'lesson_plan_mission.lesson_plan_id -> lesson_plans (cascade)',
        'lesson_plan_mission.mission_id -> missions (cascade)',
        'lesson_plan_quiz.lesson_plan_id -> lesson_plans (cascade)',
        'lesson_plan_quiz.quiz_id -> quizzes (cascade)',
        'learner_feedback.teacher_id -> users (cascade)',
        'learner_feedback.student_id -> users (cascade)',
        'learner_feedback.classroom_id -> classrooms (set null)',
        'learner_feedback.quiz_attempt_id -> quiz_attempts (set null)',
        'forum_threads.forum_category_id -> forum_categories (restrict)',
        'forum_threads.author_id -> users (cascade)',
        'forum_replies.forum_thread_id -> forum_threads (cascade)',
        'forum_replies.author_id -> users (cascade)',
        'forum_reports.forum_thread_id -> forum_threads (cascade)',
        'forum_reports.forum_reply_id -> forum_replies (cascade)',
        'forum_reports.reporter_id -> users (cascade)',
        'forum_reports.resolved_by -> users (set null)',
    ])->sort()->values()->all());
})->with('application tables');

it('defines exactly the unique constraints and indexes of the schema contract', function (array $tables) {
    $indexes = collect($tables)
        ->flatMap(fn (string $table): array => array_map(
            fn (array $index): string => sprintf('%s(%s)%s', $table, implode(',', $index['columns']), $index['unique'] ? ' unique' : ''),
            array_filter(Schema::getIndexes($table), fn (array $index): bool => ! $index['primary']),
        ))
        ->sort()
        ->values()
        ->all();

    expect($indexes)->toBe(collect([
        'users(username) unique',
        'users(email) unique',
        'users(role)',
        'users(role,is_active)',
        'student_profiles(user_id) unique',
        'student_profiles(lrn) unique',
        'school_years(name) unique',
        'school_years(is_active)',
        'academic_terms(school_year_id,name) unique',
        'subjects(name) unique',
        'subjects(code) unique',
        'subject_teacher(subject_id,teacher_id) unique',
        'classrooms(class_code) unique',
        'classrooms(teacher_id,archived_at)',
        'classrooms(subject_id)',
        'classroom_student(classroom_id,student_id) unique',
        'guardian_student(guardian_id,student_id) unique',
        'learning_modules(competency_code)',
        'learning_modules(subject_id,grade_level)',
        'rewards(name) unique',
        'reward_student(reward_id,student_id) unique',
        'quizzes(status)',
        'quizzes(teacher_id,status)',
        'classroom_quiz(classroom_id,quiz_id) unique',
        'question_bank_items(subject_id,grade_level)',
        'quiz_questions(quiz_id,sort_order)',
        'quiz_attempts(submitted_at)',
        'quiz_attempts(quiz_id,student_id)',
        'quiz_attempts(student_id,submitted_at)',
        'quiz_attempt_answers(quiz_question_id,is_correct)',
        'mission_progress(mission_id,student_id) unique',
        'mission_progress(student_id,status)',
        'activity_progress(learning_activity_id,student_id) unique',
        'lesson_plans(competency_code)',
        'learning_activity_lesson_plan(lesson_plan_id,learning_activity_id) unique',
        'lesson_plan_mission(lesson_plan_id,mission_id) unique',
        'lesson_plan_quiz(lesson_plan_id,quiz_id) unique',
        'learner_feedback(student_id,created_at)',
        'forum_categories(slug) unique',
        'forum_threads(status)',
        'forum_threads(forum_category_id,last_activity_at)',
        'settings(key) unique',
    ])->sort()->values()->all());
})->with('application tables');

it('refuses to delete a teacher who still owns classrooms', function () {
    $classroom = Classroom::factory()->create();

    expect(fn () => $classroom->teacher->delete())->toThrow(QueryException::class);
    $this->assertModelExists($classroom->teacher);
});

it('refuses to delete a teacher who still owns quizzes', function () {
    $quiz = Quiz::factory()->create();

    expect(fn () => $quiz->teacher->delete())->toThrow(QueryException::class);
    $this->assertModelExists($quiz->teacher);
});

it('refuses to delete a subject that classrooms still use', function () {
    $subject = Subject::factory()->create();
    Classroom::factory()->for($subject)->create();

    expect(fn () => $subject->delete())->toThrow(QueryException::class);
    $this->assertModelExists($subject);
});

it('removes a learner\'s profile, enrollments and attempts with the learner account', function () {
    $classroom = Classroom::factory()->create();
    $student = User::factory()->student()->enrolledIn($classroom)->create();
    $attempt = QuizAttempt::factory()->for($student, 'student')->create();

    $student->delete();

    $this->assertModelMissing($attempt);
    $this->assertDatabaseMissing('student_profiles', ['user_id' => $student->id]);
    $this->assertDatabaseMissing('classroom_student', ['student_id' => $student->id]);
});

it('keeps a learner\'s attempts when the classroom they were made in is deleted', function () {
    $classroom = Classroom::factory()->create();
    $attempt = QuizAttempt::factory()->for($classroom)->create();

    $classroom->delete();

    expect($attempt->fresh()->classroom_id)->toBeNull();
});
