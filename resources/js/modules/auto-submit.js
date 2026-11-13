/**
 * Auto-submitting GET filter forms.
 *
 * API: <form method="GET" data-auto-submit>. Selects, checkboxes, radios and date inputs submit immediately
 * on change; text-like inputs submit 450 ms after typing stops. Empty fields are left out of the query
 * string, and the field that triggered the submit gets focus back (caret at the end) after the reload.
 */
const DEBOUNCE_MS = 450;
const FOCUS_KEY = 'nutrimind.autosubmit.focus';
const TYPED_INPUTS = new Set(['text', 'search', 'email', 'tel', 'url', 'number']);

const timers = new WeakMap();

const isTyped = (field) => field instanceof HTMLInputElement && TYPED_INPUTS.has(field.type);

const formKey = (form) => form.id || form.getAttribute('action') || window.location.pathname;

function submit(form, field) {
    window.clearTimeout(timers.get(form));

    try {
        if (field?.name) {
            window.sessionStorage.setItem(FOCUS_KEY, JSON.stringify({ form: formKey(form), name: field.name }));
        }
    } catch {
        // Focus restoration is a nicety; ignore unavailable storage.
    }

    const emptied = [...form.elements].filter(
        (element) => element.name && !element.disabled && element.tagName !== 'BUTTON' && element.type !== 'checkbox' && element.type !== 'radio' && element.value === '',
    );

    emptied.forEach((element) => {
        element.disabled = true;
    });

    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
    } else {
        form.submit();
    }

    window.requestAnimationFrame(() => {
        emptied.forEach((element) => {
            element.disabled = false;
        });
    });
}

function restoreFocus() {
    let saved = null;

    try {
        saved = JSON.parse(window.sessionStorage.getItem(FOCUS_KEY) ?? 'null');
        window.sessionStorage.removeItem(FOCUS_KEY);
    } catch {
        return;
    }

    if (!saved) {
        return;
    }

    const form = [...document.querySelectorAll('form[data-auto-submit]')].find((candidate) => formKey(candidate) === saved.form);
    const field = form?.elements.namedItem(saved.name);

    if (!(field instanceof HTMLElement)) {
        return;
    }

    field.focus();

    if (isTyped(field)) {
        try {
            field.setSelectionRange(field.value.length, field.value.length);
        } catch {
            // number/email inputs do not support selection ranges.
        }
    }
}

export default function boot() {
    restoreFocus();

    document.addEventListener('change', (event) => {
        const field = event.target;
        const form = field.form;

        if (!form?.matches('[data-auto-submit]') || isTyped(field)) {
            return;
        }

        submit(form, field);
    });

    document.addEventListener('input', (event) => {
        const field = event.target;
        const form = field.form;

        if (!form?.matches('[data-auto-submit]') || !isTyped(field)) {
            return;
        }

        window.clearTimeout(timers.get(form));
        timers.set(
            form,
            window.setTimeout(() => submit(form, field), DEBOUNCE_MS),
        );
    });

    document.addEventListener('submit', (event) => {
        if (event.target instanceof HTMLFormElement && event.target.matches('[data-auto-submit]')) {
            window.clearTimeout(timers.get(event.target));
        }
    });
}
