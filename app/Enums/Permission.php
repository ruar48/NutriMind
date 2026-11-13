<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Permission: string
{
    use HasOptions;

    case ManageClassrooms = 'manage_classrooms';
    case ManageStudents = 'manage_students';
    case ImportStudents = 'import_students';
    case CreateQuizzes = 'create_quizzes';
    case ManageQuestionBank = 'manage_question_bank';
    case ShareQuestions = 'share_questions';
    case ManageLessonPlans = 'manage_lesson_plans';
    case GiveFeedback = 'give_feedback';
    case ViewAnalytics = 'view_analytics';
    case ExportReports = 'export_reports';
    case ViewChildProgress = 'view_child_progress';
    case ViewQuizDetails = 'view_quiz_details';
    case ViewTeacherFeedback = 'view_teacher_feedback';
    case AccessForum = 'access_forum';
    case PostInForum = 'post_in_forum';
    case PlayGame = 'play_game';
    case UseOfflineMode = 'use_offline_mode';
    case ChooseLearningMode = 'choose_learning_mode';
    case ViewLeaderboard = 'view_leaderboard';

    /**
     * Get the human-readable name of the permission.
     */
    public function label(): string
    {
        return match ($this) {
            self::ManageClassrooms => 'Manage classrooms',
            self::ManageStudents => 'Manage classroom learners',
            self::ImportStudents => 'Import learners from CSV',
            self::CreateQuizzes => 'Create and manage quizzes',
            self::ManageQuestionBank => 'Use the question bank',
            self::ShareQuestions => 'Share questions',
            self::ManageLessonPlans => 'Manage lesson plans',
            self::GiveFeedback => 'Give learner feedback',
            self::ViewAnalytics => 'View learning analytics',
            self::ExportReports => 'Export reports',
            self::ViewChildProgress => 'View child progress',
            self::ViewQuizDetails => 'View quiz details',
            self::ViewTeacherFeedback => 'View teacher feedback',
            self::AccessForum => 'Access the parent forum',
            self::PostInForum => 'Post in the parent forum',
            self::PlayGame => 'Play NutriMind',
            self::UseOfflineMode => 'Use offline mode',
            self::ChooseLearningMode => 'Choose a learning mode',
            self::ViewLeaderboard => 'View the leaderboard',
        };
    }

    /**
     * Get a one-sentence explanation of what the permission allows.
     */
    public function description(): string
    {
        return match ($this) {
            self::ManageClassrooms => 'Create, edit, archive and delete classrooms for assigned subjects.',
            self::ManageStudents => 'Add learners, enroll existing learners, remove learners and reset their passwords.',
            self::ImportStudents => 'Create and enroll many learners at once by uploading a CSV file.',
            self::CreateQuizzes => 'Build quizzes with images and videos, review them, publish them and assign them to classrooms.',
            self::ManageQuestionBank => 'Save, organize and reuse questions in the question bank.',
            self::ShareQuestions => 'Share question bank items with other teachers of the same subject.',
            self::ManageLessonPlans => 'Write lesson plans and align game activities, missions and quizzes to them.',
            self::GiveFeedback => 'Write praise, encouragement, guidance and concerns that parents and guardians can read.',
            self::ViewAnalytics => 'See performance trends, score distributions, module mastery and at-risk learners.',
            self::ExportReports => 'Download reports as CSV spreadsheets.',
            self::ViewChildProgress => "See a linked learner's level, points, scores, missions and rewards.",
            self::ViewQuizDetails => "Open a linked learner's quiz attempts question by question.",
            self::ViewTeacherFeedback => 'Read the feedback that teachers share with parents and guardians.',
            self::AccessForum => 'Read discussions in the moderated parent forum.',
            self::PostInForum => 'Start discussions and reply to threads in the parent forum.',
            self::PlayGame => 'Sign in to the NutriMind game and play missions, activities and quizzes.',
            self::UseOfflineMode => 'Keep playing without an internet connection and sync progress later.',
            self::ChooseLearningMode => "Pick worlds and activities freely when a classroom uses the learner's choice mode.",
            self::ViewLeaderboard => 'See classroom rankings by points and level inside the game.',
        };
    }

    /**
     * Get the heading the permission is listed under in the roles matrix.
     */
    public function group(): string
    {
        return match ($this) {
            self::ManageClassrooms, self::ManageStudents, self::ImportStudents => 'Classrooms & learners',
            self::CreateQuizzes, self::ManageQuestionBank, self::ShareQuestions => 'Assessments',
            self::ManageLessonPlans, self::GiveFeedback => 'Teaching',
            self::ViewAnalytics, self::ExportReports => 'Insights',
            self::ViewChildProgress, self::ViewQuizDetails, self::ViewTeacherFeedback => 'Parent portal',
            self::AccessForum, self::PostInForum => 'Parent forum',
            self::PlayGame, self::UseOfflineMode, self::ChooseLearningMode, self::ViewLeaderboard => 'Learning game',
        };
    }

    /**
     * Get the roles the permission can be granted to (administrators always hold every permission).
     *
     * @return list<UserRole>
     */
    public function roles(): array
    {
        return match ($this) {
            self::ManageClassrooms,
            self::ManageStudents,
            self::ImportStudents,
            self::CreateQuizzes,
            self::ManageQuestionBank,
            self::ShareQuestions,
            self::ManageLessonPlans,
            self::GiveFeedback,
            self::ViewAnalytics,
            self::ExportReports => [UserRole::Teacher],
            self::ViewChildProgress, self::ViewQuizDetails, self::ViewTeacherFeedback => [UserRole::Parent],
            self::AccessForum, self::PostInForum => [UserRole::Teacher, UserRole::Parent],
            self::PlayGame, self::UseOfflineMode, self::ChooseLearningMode, self::ViewLeaderboard => [UserRole::Student],
        };
    }

    /**
     * Get the roles that hold the permission until an administrator changes the matrix.
     *
     * Every applicable role is granted by default.
     *
     * @return list<UserRole>
     */
    public function defaultRoles(): array
    {
        return $this->roles();
    }

    /**
     * Determine whether the permission is enforced by the learner game instead of the web portals.
     */
    public function enforcedByGame(): bool
    {
        return match ($this) {
            self::PlayGame, self::UseOfflineMode, self::ChooseLearningMode, self::ViewLeaderboard => true,
            default => false,
        };
    }
}
