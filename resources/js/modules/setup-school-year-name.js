/**
 * School year name suggestion (admin school year form).
 *
 * API: inside form[data-setup-school-year-form], picking the dates in [data-setup-year-start] and
 * [data-setup-year-end] fills [data-setup-year-name] with "start year–end year" (e.g. "2027–2028") while the
 * name is empty or still holds an earlier suggestion. A name the administrator typed is never replaced.
 */
function yearOf(input) {
    const match = /^(\d{4})-\d{2}-\d{2}$/.exec(input?.value ?? '');

    return match ? match[1] : null;
}

function suggest(form) {
    const name = form.querySelector('[data-setup-year-name]');
    const startYear = yearOf(form.querySelector('[data-setup-year-start]'));
    const endYear = yearOf(form.querySelector('[data-setup-year-end]'));

    if (!name || !startYear) {
        return;
    }

    const isUntouched = name.value.trim() === '' || name.value === name.dataset.setupSuggested;

    if (!isUntouched) {
        return;
    }

    const suggestion = `${startYear}–${endYear ?? String(Number(startYear) + 1)}`;
    name.value = suggestion;
    name.dataset.setupSuggested = suggestion;
}

export default function boot() {
    document.addEventListener('change', (event) => {
        const field = event.target;

        if (!(field instanceof HTMLInputElement) || !field.matches('[data-setup-year-start], [data-setup-year-end]')) {
            return;
        }

        const form = field.closest('form[data-setup-school-year-form]');

        if (form) {
            suggest(form);
        }
    });
}
