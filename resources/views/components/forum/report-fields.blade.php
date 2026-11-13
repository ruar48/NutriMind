@props([
    'key',
    'submitted' => false,
])

@php
    $bag = $submitted ? 'report' : 'report-'.$key;
@endphp

{{-- Only the report form that was sent shows the old input and errors. "required" is added by forum-report.js: without JS the closed dialog must still submit, and the server validates. --}}
<div {{ $attributes->class('flex flex-col gap-4 text-left') }}>
    <x-form.select name="reason" :id="'report-reason-'.$key" label="Reason" :options="\App\Enums\ForumReportReason::options()"
        placeholder="Choose a reason" :bag="$bag" :old="$submitted" data-forum-report-reason />
    <x-form.textarea name="details" :id="'report-details-'.$key" label="Details" :bag="$bag" :old="$submitted" :max-length="500" rows="3" optional
        placeholder="What should the moderators look at?" />
</div>
