/**
 * Live subject preview (admin subject form).
 *
 * API: inside form[data-setup-subject-form], [data-setup-subject-preview] mirrors the form: its data-color
 * follows the checked `color` radio (the preview's group-data-[color=…] classes do the styling),
 * [data-setup-subject-preview-icon] copies the SVG of the checked `icon` option, and
 * [data-setup-subject-preview-name] / [data-setup-subject-preview-code] show the typed name and code (their
 * data-placeholder while empty). The server renders the same initial state, so the preview works without JS.
 */
function render(form) {
    const preview = form.querySelector('[data-setup-subject-preview]');

    if (!preview) {
        return;
    }

    const color = form.querySelector('input[name="color"]:checked');

    if (color) {
        preview.dataset.color = color.value;
    }

    const icon = form.querySelector('input[name="icon"]:checked');
    const iconSlot = preview.querySelector('[data-setup-subject-preview-icon]');
    const iconSvg = icon?.closest('label')?.querySelector('svg');

    if (iconSlot && iconSvg) {
        const copy = iconSvg.cloneNode(true);
        copy.setAttribute('class', 'size-6');
        iconSlot.replaceChildren(copy);
    }

    const pairs = [
        ['[data-setup-subject-name]', '[data-setup-subject-preview-name]', (value) => value],
        ['[data-setup-subject-code]', '[data-setup-subject-preview-code]', (value) => value.toUpperCase()],
    ];

    pairs.forEach(([inputSelector, targetSelector, format]) => {
        const input = form.querySelector(inputSelector);
        const target = preview.querySelector(targetSelector);

        if (input && target) {
            const value = format(input.value.trim());
            target.textContent = value === '' ? (target.dataset.placeholder ?? '') : value;
        }
    });
}

export default function boot() {
    ['input', 'change'].forEach((type) => {
        document.addEventListener(type, (event) => {
            const form = event.target.closest?.('form[data-setup-subject-form]');

            if (form) {
                render(form);
            }
        });
    });
}
