/**
 * Pending subject assignment changes (admin subject assignment page).
 *
 * API: in form[data-setup-assignment-form], every subject checkbox carries data-setup-assigned="1|0" (assigned
 * when the page loaded) and data-setup-locks="N" (classrooms that revoking it locks). Its
 * [data-setup-assignment-option] wrapper gets data-adding / data-revoking while the box differs from the saved
 * state, and [data-setup-assignment-summary] spells out the pending changes (its original text returns when
 * nothing is pending). The server-rendered descriptions already state what revoking removes.
 */
function plural(count, singular, pluralForm) {
    return `${count} ${count === 1 ? singular : pluralForm}`;
}

function refresh(form) {
    let adding = 0;
    let revoking = 0;
    let locked = 0;

    form.querySelectorAll('input[type="checkbox"][data-setup-assigned]').forEach((box) => {
        const wasAssigned = box.dataset.setupAssigned === '1';
        const option = box.closest('[data-setup-assignment-option]');
        const isAdding = !wasAssigned && box.checked;
        const isRevoking = wasAssigned && !box.checked;

        option?.toggleAttribute('data-adding', isAdding);
        option?.toggleAttribute('data-revoking', isRevoking);

        if (isAdding) {
            adding += 1;
        }

        if (isRevoking) {
            revoking += 1;
            locked += Number(box.dataset.setupLocks ?? 0);
        }
    });

    const summary = form.querySelector('[data-setup-assignment-summary]');

    if (!summary) {
        return;
    }

    summary.dataset.setupOriginal ??= summary.textContent.trim();

    if (adding === 0 && revoking === 0) {
        summary.textContent = summary.dataset.setupOriginal;

        return;
    }

    const parts = [];

    if (adding > 0) {
        parts.push(`${plural(adding, 'subject', 'subjects')} to assign`);
    }

    if (revoking > 0) {
        const lockNote = locked > 0 ? ` (locks ${plural(locked, 'classroom', 'classrooms')})` : '';
        parts.push(`${plural(revoking, 'subject', 'subjects')} to revoke${lockNote}`);
    }

    summary.textContent = `Unsaved: ${parts.join(' · ')}`;
}

export default function boot() {
    document.querySelectorAll('form[data-setup-assignment-form]').forEach(refresh);

    document.addEventListener('change', (event) => {
        const form = event.target.closest?.('form[data-setup-assignment-form]');

        if (form && event.target.matches('[data-setup-assigned]')) {
            refresh(form);
        }
    });
}
