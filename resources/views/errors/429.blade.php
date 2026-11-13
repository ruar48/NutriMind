@php
    $headers = isset($exception) && method_exists($exception, 'getHeaders') ? $exception->getHeaders() : [];
    $retryAfter = isset($headers['Retry-After']) && is_numeric($headers['Retry-After']) ? (int) $headers['Retry-After'] : null;
    $wait = match (true) {
        $retryAfter === null => 'a minute',
        $retryAfter < 60 => $retryAfter.' '.\Illuminate\Support\Str::plural('second', $retryAfter),
        default => (int) ceil($retryAfter / 60).' '.\Illuminate\Support\Str::plural('minute', (int) ceil($retryAfter / 60)),
    };
@endphp

<x-layouts.error
    code="429"
    title="Whoa, slow down a little"
    :message="'There were too many requests in a short time. Please wait '.$wait.' and try again.'"
    illustration="snail"
/>
