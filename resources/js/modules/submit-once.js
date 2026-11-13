/**
 * Prevents double posts.
 *
 * API: <form data-submit-once>. On submit every submit button of the form (including buttons outside it
 * that use form="id") is disabled and the button that submitted gets data-loading + aria-busy, which shows
 * the x-button spinner. A second submit is ignored. Buttons are restored when the page is shown from the
 * back/forward cache.
 */
const SUBMIT_BUTTONS = 'button[type="submit"], button:not([type]), input[type="submit"]';

function buttonsOf(form) {
    const inside = [...form.querySelectorAll(SUBMIT_BUTTONS)];
    const outside = form.id ? [...document.querySelectorAll(`button[form="${CSS.escape(form.id)}"]`)] : [];

    return [...inside, ...outside];
}

function lock(form, submitter) {
    form.dataset.submitting = 'true';

    buttonsOf(form).forEach((button) => {
        button.disabled = true;
    });

    if (submitter instanceof HTMLElement) {
        submitter.setAttribute('data-loading', '');
        submitter.setAttribute('aria-busy', 'true');
    }
}

function unlock(form) {
    delete form.dataset.submitting;

    buttonsOf(form).forEach((button) => {
        button.disabled = false;
        button.removeAttribute('data-loading');
        button.removeAttribute('aria-busy');
    });
}

export default function boot() {
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.matches('[data-submit-once]')) {
            return;
        }

        if (form.dataset.submitting === 'true') {
            event.preventDefault();

            return;
        }

        // Disable after the form data set is built, so the submitter's own name/value is still sent.
        window.setTimeout(() => {
            if (!event.defaultPrevented) {
                lock(form, event.submitter);
            }
        }, 0);
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            document.querySelectorAll('form[data-submitting="true"]').forEach(unlock);
        }
    });
}
