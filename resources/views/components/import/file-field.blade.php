@php
    $fileErrors = isset($errors) ? $errors->get('file') : [];
    $maxRows = number_format(\App\Services\StudentImport\ImportColumns::maxRows());
    $maxSize = \App\Services\StudentImport\ImportColumns::maxFileSizeLabel();
@endphp

<div data-imports-dropzone {{ $attributes->class('col-span-full flex flex-col gap-3 rounded-2xl transition data-dragging:ring-2 data-dragging:ring-leaf-500 data-dragging:ring-offset-4 data-dragging:ring-offset-white dark:data-dragging:ring-leaf-400 dark:data-dragging:ring-offset-ink-900') }}>
    <x-form.file
        name="file"
        label="CSV file"
        accept=".csv,text/csv"
        :hint="'A .csv file up to '.$maxSize.' with at most '.$maxRows.' learners.'"
        required
    />
    <p class="text-xs text-ink-500 dark:text-ink-400" data-js-only>You can also drop the file onto the box above.</p>

    @if (count($fileErrors) > 1)
        <x-alert variant="danger" title="Also fix these problems in the file" data-import-file-errors>
            <ul class="list-disc space-y-1 pl-4">
                @foreach (array_slice($fileErrors, 1) as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif
</div>
