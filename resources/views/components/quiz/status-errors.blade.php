{{--
    The reasons a quiz status change was refused (validation errors on "status"), shown at the top of the page the
    teacher came from. Renders nothing when there are none.
--}}
@php
    $messages = isset($errors) ? $errors->get('status') : [];
@endphp

@if ($messages !== [])
    <x-alert variant="danger" title="The quiz status did not change" {{ $attributes->merge(['data-quiz-status-errors' => count($messages)]) }}>
        <ul role="list" class="list-disc space-y-1 pl-5">
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
