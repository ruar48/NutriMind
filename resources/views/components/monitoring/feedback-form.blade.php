@props([
    'feedback',
    'student',
    'action',
    'method' => 'POST',
    'classroomOptions' => [],
    'attemptOptions' => [],
    'submit' => 'Send feedback',
    'submitIcon' => 'send',
    'cancel' => null,
    'classroomUnavailable' => false,
    'attemptUnavailable' => false,
])

@php
    $isCreating = strtoupper($method) === 'POST';
    $classroomOptions = collect($classroomOptions)->all();
    $attemptOptions = collect($attemptOptions)->all();
@endphp

<form id="feedback-form" method="POST" action="{{ $action }}" {{ $attributes->class('flex flex-col gap-8') }} data-submit-once>
    @csrf
    @unless ($isCreating)
        @method($method)
    @endunless

    @if ($isCreating)
        <input type="hidden" name="student_id" value="{{ $student->getKey() }}">
        <x-form.error for="student_id" />
    @endif

    <x-form.section title="Message" :description="'Write to '.$student->name.'. Keep it specific, kind and actionable.'">
        <x-form.radio-cards name="type" label="Kind of feedback" :options="\App\Enums\FeedbackType::cases()" :value="$feedback->type" columns="2" required class="col-span-full" />
        <x-form.textarea name="message" label="Message" :value="$feedback->message" :max-length="\App\Http\Requests\Teacher\StoreFeedbackRequest::MESSAGE_MAX_LENGTH" rows="6" required class="col-span-full" />
    </x-form.section>

    <x-form.section title="Context" description="Link the feedback to a classroom or a quiz attempt so it is easy to find later." columns="2">
        <x-form.select
            name="classroom_id"
            label="Classroom"
            :options="$classroomOptions"
            :value="$feedback->classroom_id"
            placeholder="No classroom"
            :hint="$classroomUnavailable ? 'The classroom this feedback was linked to is no longer yours. Saving removes the link.' : null"
            optional
        />
        <x-form.select
            name="quiz_attempt_id"
            label="Quiz attempt"
            :options="$attemptOptions"
            :value="$feedback->quiz_attempt_id"
            placeholder="Not about a specific attempt"
            :hint="$attemptUnavailable ? 'The attempt this feedback was about is no longer available to you. Saving removes the link.' : 'Attempts at your own quizzes, newest first.'"
            optional
            class="col-span-full"
        />
    </x-form.section>

    <x-form.section title="Visibility" description="Parents and guardians read visible feedback on their child’s page in the Parent Portal.">
        <x-form.toggle
            name="is_visible_to_guardians"
            label="Show to parents and guardians"
            description="Turn this off to keep a private note that only you can see."
            :checked="$feedback->is_visible_to_guardians ?? true"
            unchecked-value="0"
        />
    </x-form.section>

    <x-form.actions :cancel="$cancel" :submit="$submit" :submit-icon="$submitIcon">
        <x-icon name="info" class="size-4" />
        @if ($isCreating)
            Visible feedback appears in the Parent Portal right away.
        @else
            Changing the message shows it to parents as unread again.
        @endif
    </x-form.actions>
</form>
