/**
 * Flash toasts.
 *
 * API: [data-toast] (optional data-toast-timeout in ms, default 6000, 0 = sticky) inside
 * [data-toast-region]; [data-toast-close] dismisses. Timers pause while hovered or focused.
 * `showToast(message, 'success' | 'danger')` clones <template data-toast-template="…"> for client-side use.
 */
const DEFAULT_TIMEOUT = 6000;

function dismiss(toast) {
    if (toast.hasAttribute('data-leaving')) {
        return;
    }

    toast.setAttribute('data-leaving', '');
    window.setTimeout(() => toast.remove(), 220);
}

function schedule(toast) {
    const timeout = Number(toast.dataset.toastTimeout ?? DEFAULT_TIMEOUT);

    if (!Number.isFinite(timeout) || timeout <= 0) {
        return;
    }

    let remaining = timeout;
    let startedAt = Date.now();
    let timer = window.setTimeout(() => dismiss(toast), remaining);

    const pause = () => {
        window.clearTimeout(timer);
        remaining = Math.max(0, remaining - (Date.now() - startedAt));
    };

    const resume = () => {
        if (toast.matches(':hover') || toast.contains(document.activeElement)) {
            return;
        }

        startedAt = Date.now();
        window.clearTimeout(timer);
        timer = window.setTimeout(() => dismiss(toast), Math.max(remaining, 1500));
    };

    toast.addEventListener('pointerenter', pause);
    toast.addEventListener('pointerleave', resume);
    toast.addEventListener('focusin', pause);
    toast.addEventListener('focusout', () => window.setTimeout(resume, 0));
}

function announce(toast) {
    const message = toast.querySelector('[data-toast-message]');

    if (!message) {
        return;
    }

    const text = message.textContent;
    message.textContent = '';
    window.setTimeout(() => {
        message.textContent = text;
    }, 120);
}

export function showToast(message, variant = 'success') {
    const region = document.querySelector('[data-toast-region]');
    const template = region?.querySelector(`template[data-toast-template="${CSS.escape(variant)}"]`);
    const stack = region?.querySelector('[data-toast-stack]');

    if (!template || !stack) {
        return null;
    }

    const toast = template.content.firstElementChild.cloneNode(true);
    toast.querySelector('[data-toast-message]').textContent = message;
    stack.append(toast);
    schedule(toast);

    return toast;
}

export default function boot() {
    document.querySelectorAll('[data-toast]').forEach((toast) => {
        announce(toast);
        schedule(toast);
    });

    document.addEventListener('click', (event) => {
        const toast = event.target.closest('[data-toast-close]')?.closest('[data-toast]');

        if (toast) {
            dismiss(toast);
        }
    });
}
