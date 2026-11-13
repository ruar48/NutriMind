@php
    $reason = isset($exception) ? trim((string) $exception->getMessage()) : '';
    $hasCustomReason = $reason !== '' && $reason !== 'This action is unauthorized.' && $reason !== 'Forbidden';
@endphp

<x-layouts.error
    code="403"
    title="This area is locked"
    :message="$hasCustomReason ? $reason : 'You don’t have permission to open this page. If you think you should, ask your school administrator.'"
    illustration="lock"
/>
