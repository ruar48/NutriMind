/**
 * Dropdown menus built on <details data-dropdown>. Works without JS (native disclosure); this module adds:
 * close on outside click and Esc (focus returns to the summary), one menu open at a time, close after an
 * item is chosen ([data-dropdown-item]; opt out with data-dropdown-keep-open) and ArrowUp/ArrowDown focus.
 * A dropdown that contains an open <dialog> (e.g. a confirm dialog) is never closed underneath it.
 */
const ITEM_SELECTOR = '[data-dropdown-item]:not([disabled]):not([aria-disabled="true"])';

const openDropdowns = () => [...document.querySelectorAll('details[data-dropdown][open]')];

const hasOpenDialog = (dropdown) => dropdown.querySelector('dialog[open]') !== null;

function close(dropdown, { focusSummary = false } = {}) {
    if (hasOpenDialog(dropdown)) {
        return;
    }

    dropdown.removeAttribute('open');

    if (focusSummary) {
        dropdown.querySelector('summary')?.focus();
    }
}

function closeOthers(current) {
    openDropdowns().forEach((dropdown) => {
        if (dropdown !== current && !dropdown.contains(current)) {
            close(dropdown);
        }
    });
}

export default function boot() {
    document.addEventListener('click', (event) => {
        if (event.target.closest('dialog[open]')) {
            return;
        }

        const current = event.target.closest('details[data-dropdown]');
        closeOthers(current);

        const item = event.target.closest('[data-dropdown-item]');

        if (current && item && !item.hasAttribute('data-dropdown-keep-open')) {
            window.requestAnimationFrame(() => close(current));
        }
    });

    document.addEventListener(
        'toggle',
        (event) => {
            const dropdown = event.target;

            if (dropdown instanceof HTMLDetailsElement && dropdown.matches('[data-dropdown]') && dropdown.open) {
                closeOthers(dropdown);
            }
        },
        true,
    );

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            const current = event.target.closest?.('details[data-dropdown][open]') ?? openDropdowns().pop();

            if (current && !hasOpenDialog(current)) {
                event.preventDefault();
                close(current, { focusSummary: true });
            }

            return;
        }

        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
            return;
        }

        const current = event.target.closest?.('details[data-dropdown]');

        if (!current || hasOpenDialog(current)) {
            return;
        }

        const items = [...current.querySelectorAll(ITEM_SELECTOR)];

        if (items.length === 0) {
            return;
        }

        event.preventDefault();

        if (!current.open) {
            current.setAttribute('open', '');
        }

        const index = items.indexOf(document.activeElement);
        const forward = event.key === 'ArrowDown';
        const next = index === -1 ? (forward ? 0 : items.length - 1) : (index + (forward ? 1 : -1) + items.length) % items.length;

        items[next].focus();
    });
}
