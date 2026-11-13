@props([
    'question',
    'compact' => false,
])

@php
    $imageUrl = $question->imageUrl();
    $videoFileUrl = $question->videoFileUrl();
    $videoEmbedUrl = $videoFileUrl === null ? $question->videoEmbedUrl() : null;
    $videoLinkUrl = $videoFileUrl === null && $videoEmbedUrl === null ? $question->videoLinkUrl() : null;
    $hasVideo = $videoFileUrl !== null || $videoEmbedUrl !== null || $videoLinkUrl !== null;

    $promptText = \Illuminate\Support\Str::squish((string) $question->prompt);
    $altText = $promptText === ''
        ? 'Question illustration'
        : 'Illustration: '.(mb_strlen($promptText) > 80 ? rtrim(mb_substr($promptText, 0, 80)).'…' : $promptText);

    $videoTypes = [
        'mp4' => 'video/mp4',
        'm4v' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
    ];
    $videoType = $videoTypes[strtolower(pathinfo((string) $question->video_path, PATHINFO_EXTENSION))] ?? null;
    $videoLinkHost = $videoLinkUrl !== null ? preg_replace('/^www\./', '', strtolower((string) parse_url($videoLinkUrl, PHP_URL_HOST))) : null;
@endphp

@if ($imageUrl !== null || $hasVideo)
    @if ($compact)
        <div {{ $attributes->class(['inline-flex max-w-full shrink-0 items-center gap-2']) }} data-question-media="compact">
            @if ($imageUrl !== null)
                <img
                    src="{{ $imageUrl }}"
                    alt="{{ $altText }}"
                    loading="lazy"
                    decoding="async"
                    class="h-16 w-16 shrink-0 rounded-lg border border-ink-900/10 bg-ink-50 object-contain dark:border-white/10 dark:bg-white/5"
                >
            @endif
            @if ($hasVideo)
                <x-badge color="sky" icon="video" size="sm" data-question-media-video>Video</x-badge>
            @endif
        </div>
    @else
        <div {{ $attributes->class(['flex min-w-0 flex-col items-start gap-3']) }} data-question-media="full">
            @if ($imageUrl !== null)
                <img
                    src="{{ $imageUrl }}"
                    alt="{{ $altText }}"
                    loading="lazy"
                    decoding="async"
                    class="block h-auto max-h-72 w-auto max-w-full rounded-xl border border-ink-900/10 bg-ink-50 object-contain dark:border-white/10 dark:bg-white/5"
                >
            @endif

            @if ($videoFileUrl !== null)
                <video
                    controls
                    preload="metadata"
                    playsinline
                    aria-label="Question video"
                    class="block aspect-video w-full max-w-2xl rounded-xl bg-ink-950 ring-1 ring-ink-900/10 dark:ring-white/10"
                >
                    <source src="{{ $videoFileUrl }}" @if ($videoType !== null) type="{{ $videoType }}" @endif>
                    <a href="{{ $videoFileUrl }}">Download the question video</a>
                </video>
            @elseif ($videoEmbedUrl !== null)
                <div class="relative aspect-video w-full max-w-2xl overflow-hidden rounded-xl bg-ink-950 ring-1 ring-ink-900/10 dark:ring-white/10">
                    <iframe
                        src="{{ $videoEmbedUrl }}"
                        title="Question video"
                        loading="lazy"
                        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen
                        class="absolute inset-0 size-full border-0"
                    ></iframe>
                </div>
            @elseif ($videoLinkUrl !== null)
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <x-button
                        variant="secondary"
                        size="sm"
                        icon="circle-play"
                        icon-trailing="external-link"
                        :href="$videoLinkUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Watch video<span class="sr-only"> (opens in a new tab)</span>
                    </x-button>
                    @if (filled($videoLinkHost))
                        <span class="text-xs text-ink-500 dark:text-ink-400">Opens {{ $videoLinkHost }}</span>
                    @endif
                </div>
            @endif
        </div>
    @endif
@endif
