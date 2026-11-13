<x-layouts.error
    code="419"
    title="Your session timed out"
    message="For your security, this page expired before it was sent. Go back, refresh the page and try again."
    illustration="hourglass"
>
    <x-slot:actions>
        <x-button :href="url()->previous()" variant="secondary" size="lg" icon="arrow-left">Go back</x-button>
    </x-slot:actions>
</x-layouts.error>
