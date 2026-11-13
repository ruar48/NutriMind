/**
 * File inputs: selected file name and image preview.
 *
 * API: <input type="file" data-image-preview="img-id"> shows the chosen image in <img id="img-id"> (its
 * wrapper [data-image-preview-wrapper] is un-hidden); a non-image choice restores the original image.
 * [data-file-name-for="input-id"] shows the chosen file's name. A [data-file-remove] checkbox dims the
 * current-file block ([data-file-current]) while checked.
 */
function showFileName(input) {
    document.querySelectorAll(`[data-file-name-for="${CSS.escape(input.id)}"]`).forEach((label) => {
        const file = input.files?.[0];
        label.textContent = file ? file.name : label.dataset.emptyText ?? '';
    });
}

function preview(input) {
    const image = document.getElementById(input.dataset.imagePreview);

    if (!(image instanceof HTMLImageElement)) {
        return;
    }

    if (image.dataset.objectUrl) {
        URL.revokeObjectURL(image.dataset.objectUrl);
        delete image.dataset.objectUrl;
    }

    if (!image.hasAttribute('data-original-src')) {
        image.setAttribute('data-original-src', image.getAttribute('src') ?? '');
    }

    const wrapper = image.closest('[data-image-preview-wrapper]');
    const file = input.files?.[0];

    if (file && file.type.startsWith('image/')) {
        const url = URL.createObjectURL(file);
        image.dataset.objectUrl = url;
        image.src = url;
        wrapper?.removeAttribute('hidden');

        return;
    }

    const original = image.getAttribute('data-original-src');

    if (original) {
        image.src = original;
    } else {
        image.removeAttribute('src');
        wrapper?.setAttribute('hidden', '');
    }
}

export default function boot() {
    document.addEventListener('change', (event) => {
        const field = event.target;

        if (field instanceof HTMLInputElement && field.type === 'file') {
            showFileName(field);

            if (field.dataset.imagePreview) {
                preview(field);
            }

            return;
        }

        if (field instanceof HTMLInputElement && field.matches('[data-file-remove]')) {
            field.closest('[data-file-field]')?.querySelector('[data-file-current]')?.toggleAttribute('data-removing', field.checked);
        }
    });
}
