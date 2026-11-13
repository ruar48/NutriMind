/**
 * Off-canvas sidebar drawer below the lg breakpoint.
 *
 * API: [data-sidebar] (the drawer, needs an id), [data-sidebar-toggle] (opens/closes, keeps aria-expanded in
 * sync), [data-sidebar-close] and [data-sidebar-backdrop] (close). Esc closes, Tab stays inside the open
 * drawer, the page behind it cannot scroll. Without JS the toggle is a #id link and CSS :target opens it.
 */
const desktop = window.matchMedia('(min-width: 64rem)');

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

export default function boot() {
    const sidebar = document.querySelector('[data-sidebar]');

    if (!sidebar) {
        return;
    }

    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    let returnFocusTo = null;

    const isOpen = () => sidebar.hasAttribute('data-open');

    const setExpanded = (expanded) => {
        document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => {
            toggle.setAttribute('aria-expanded', String(expanded));
        });
    };

    const open = (trigger) => {
        returnFocusTo = trigger instanceof HTMLElement ? trigger : document.activeElement;
        sidebar.setAttribute('data-open', '');
        backdrop?.setAttribute('data-open', '');
        document.documentElement.classList.add('overflow-hidden');
        setExpanded(true);

        const active = sidebar.querySelector('[aria-current="page"]') ?? sidebar.querySelector(FOCUSABLE);
        window.requestAnimationFrame(() => active?.focus());
    };

    const close = ({ restoreFocus = true } = {}) => {
        if (!isOpen() && window.location.hash !== `#${sidebar.id}`) {
            return;
        }

        sidebar.removeAttribute('data-open');
        backdrop?.removeAttribute('data-open');
        document.documentElement.classList.remove('overflow-hidden');
        setExpanded(false);

        if (window.location.hash === `#${sidebar.id}`) {
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }

        if (restoreFocus && returnFocusTo instanceof HTMLElement) {
            returnFocusTo.focus();
        }
    };

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-sidebar-toggle]');

        if (toggle) {
            event.preventDefault();
            isOpen() ? close() : open(toggle);

            return;
        }

        if (event.target.closest('[data-sidebar-close], [data-sidebar-backdrop]')) {
            event.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!isOpen()) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close();

            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = [...sidebar.querySelectorAll(FOCUSABLE)].filter((element) => element.offsetParent !== null);

        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        } else if (!sidebar.contains(document.activeElement)) {
            event.preventDefault();
            first.focus();
        }
    });

    desktop.addEventListener('change', (event) => {
        if (event.matches) {
            close({ restoreFocus: false });
        }
    });
}
