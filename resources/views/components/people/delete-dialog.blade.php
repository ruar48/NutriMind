@props([
    'user',
    'buttonVariant' => 'menu',
    'buttonSize' => 'sm',
    'button' => 'Delete',
])

@php
    $message = match ($user->role) {
        \App\Enums\UserRole::Student => 'Their classroom enrollments, quiz attempts, mission and activity progress, rewards, feedback and guardian links are deleted too. This cannot be undone.',
        \App\Enums\UserRole::Teacher => 'Only teachers without classrooms or quizzes can be deleted. Their lesson plans and the feedback they wrote are deleted too; their question bank items stay. This cannot be undone.',
        \App\Enums\UserRole::Parent => 'Their links to learners and their forum posts are deleted too. This cannot be undone.',
        default => 'The account and its sign-in are removed permanently. This cannot be undone.',
    };
@endphp

<x-confirm-dialog
    :action="route('admin.users.destroy', $user)"
    :title="'Delete '.$user->name.'?'"
    :message="$message"
    :confirm="$user->isStudent() ? 'Delete learner and progress' : 'Delete account'"
    :button="$button"
    :button-variant="$buttonVariant"
    :button-size="$buttonSize"
    {{ $attributes }}
/>
