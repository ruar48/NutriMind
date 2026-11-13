/**
 * Keyboard shortcuts.
 *
 * API: pressing "/" focuses the first visible [data-search-shortcut] input, unless the user is already typing
 * in a field or holding a modifier key.
 */
const TYPING_TARGETS = 'input, textarea, select, [contenteditable=""], [contenteditable="true"]';

export default function boot() {
    document.addEventListener('keydown', (event) => {
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented) {
            return;
        }

        if (event.target instanceof Element && event.target.closest(TYPING_TARGETS)) {
            return;
        }

        const field = [...document.querySelectorAll('[data-search-shortcut]')].find((candidate) => candidate.offsetParent !== null);

        if (field instanceof HTMLInputElement) {
            event.preventDefault();
            field.focus();
            field.select();
        }
    });
}
