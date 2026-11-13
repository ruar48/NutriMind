@props([
    'body',
])

{{-- The body is escaped first; only the line breaks nl2br() adds are HTML. --}}
<div {{ $attributes->class('text-sm leading-relaxed wrap-break-word text-ink-800 sm:text-[0.9375rem] dark:text-ink-100') }} data-forum-body>{!! nl2br(e($body)) !!}</div>
