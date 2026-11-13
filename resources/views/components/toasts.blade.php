@php
    $status = session('status');
    $error = session('error');
@endphp

<div data-toast-region aria-live="polite" class="pointer-events-none fixed inset-x-0 top-0 z-[60] flex justify-end p-4 sm:top-16 sm:p-6" data-print-hidden>
    <div data-toast-stack class="flex w-full max-w-sm flex-col gap-3">
        @if (is_string($status) && filled($status))
            <x-toast variant="success" :message="$status" />
        @endif
        @if (is_string($error) && filled($error))
            <x-toast variant="danger" :message="$error" data-toast-timeout="9000" />
        @endif
    </div>

    <template data-toast-template="success">
        <x-toast variant="success" />
    </template>
    <template data-toast-template="danger">
        <x-toast variant="danger" data-toast-timeout="9000" />
    </template>
</div>
