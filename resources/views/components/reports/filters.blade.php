@props([
    'reportType',
    'options',
    'portal',
])

@php
    $isAccounts = $reportType === \App\Enums\ReportType::UserAccounts;
    $showUrl = route($portal.'.reports.show', $reportType);
    $periodHint = match ($reportType) {
        \App\Enums\ReportType::StudentPerformance => 'From and To count the quiz attempts submitted and the missions completed in that period.',
        \App\Enums\ReportType::QuizResults => 'From and To count the attempts submitted in that period.',
        \App\Enums\ReportType::MissionProgress => 'From and To count missions completed, or started and still in progress, in that period.',
        \App\Enums\ReportType::ClassroomSummary => 'From and To count the quiz attempts and mission completions in that period.',
        \App\Enums\ReportType::UserAccounts => 'Created from and until list the accounts created in that period.',
    };
    $scopeHint = match (true) {
        $isAccounts => null,
        $portal === 'teacher' => 'Reports cover your active classrooms. Choose an archived classroom to report on it.',
        default => 'Reports cover every active classroom. Choose an archived classroom to report on it.',
    };
@endphp

<div {{ $attributes->class('flex flex-col gap-2') }} data-print-hidden data-report-filters>
    <x-filter-bar :action="$showUrl" :search="false" :reset-url="$showUrl" :preserve="[]">
        @if ($isAccounts)
            <x-form.select name="role" label="Role" hide-label placeholder="All roles" :options="$options['roles'] ?? []" :value="request('role')" :old="false" class="sm:w-48" />
            <x-form.select name="status" label="Status" hide-label placeholder="Any status" :options="$options['statuses'] ?? []" :value="request('status')" :old="false" class="sm:w-40" />
        @else
            <x-form.select name="classroom_id" label="Classroom" hide-label placeholder="All active classrooms" :options="$options['classrooms'] ?? []" :value="request('classroom_id')" :old="false" class="sm:w-64" />
            <x-form.select name="subject_id" label="Subject" hide-label placeholder="All subjects" :options="$options['subjects'] ?? []" :value="request('subject_id')" :old="false" class="sm:w-48" />
            <x-form.select name="grade_level" label="Grade level" hide-label placeholder="All grades" :options="$options['grades'] ?? []" :value="request('grade_level')" :old="false" class="sm:w-36" />
            @isset($options['teachers'])
                <x-form.select name="teacher_id" label="Teacher" hide-label placeholder="All teachers" :options="$options['teachers']" :value="request('teacher_id')" :old="false" class="sm:w-56" />
            @endisset
        @endif
        <x-form.input type="date" name="from" :label="$isAccounts ? 'Created from' : 'From'" :value="request('from')" :old="false" class="sm:w-44" />
        <x-form.input type="date" name="to" :label="$isAccounts ? 'Created until' : 'To'" :value="request('to')" :old="false" class="sm:w-44" />
    </x-filter-bar>

    <p class="flex items-start gap-1.5 text-xs text-ink-600 dark:text-ink-300">
        <x-icon name="info" class="mt-px size-3.5 shrink-0" />
        <span>{{ $scopeHint ? $scopeHint.' ' : '' }}{{ $periodHint }}</span>
    </p>
</div>
