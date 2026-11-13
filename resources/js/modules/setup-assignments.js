/**
 * Pending subject assignment changes (admin subject assignment page).
 *
 * API: in form[data-setup-assignment-form], every subject checkbox carries data-setup-assigned="1|0" (assigned
 * when the page loaded), data-setup-locks="N" (classrooms that revoking it locks) and data-setup-subject (its
 * name). Its [data-setup-assignment-option] wrapper gets data-adding / data-revoking while the box differs from
 * the saved state, and [data-setup-assignment-summary] spells out the pending changes (its original text
 * returns when nothing is pending).
 * Saving a form that revokes subjects first opens <dialog data-modal="…"> named by the form's
 * data-setup-revoke-dialog, listing them in [data-setup-revoke-list]; its [data-setup-revoke-confirm] button
 * (form="…") submits the form. Without JS the form submits directly and the server-rendered descriptions
 * already state what revoking removes.
 */
import { openDialog } from './modal.js';

function plural(count, singular, pluralForm) {
    return `${count} ${count === 1 ? singular : pluralForm}`;
}

function revokedBoxes(form) {
    return [...form.querySelectorAll('input[type="checkbox"][data-setup-assigned="1"]')].filter((box) => !box.checked);
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

function confirmRevocations(event) {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches('[data-setup-assignment-form]')) {
        return;
    }

    if (event.submitter?.hasAttribute('data-setup-revoke-confirm')) {
        return;
    }

    const revoked = revokedBoxes(form);
    const dialog = document.querySelector(`dialog[data-modal="${CSS.escape(form.dataset.setupRevokeDialog ?? '')}"]`);

    if (revoked.length === 0 || !(dialog instanceof HTMLDialogElement)) {
        return;
    }

    event.preventDefault();

    dialog.querySelector('[data-setup-revoke-list]')?.replaceChildren(
        ...revoked.map((box) => {
            const item = document.createElement('li');
            const locks = Number(box.dataset.setupLocks ?? 0);
            item.textContent = locks > 0
                ? `${box.dataset.setupSubject}: ${plural(locks, 'classroom', 'classrooms')} locked`
                : `${box.dataset.setupSubject}`;

            return item;
        }),
    );

    openDialog(dialog);
}

export default function boot() {
    document.querySelectorAll('form[data-setup-assignment-form]').forEach(refresh);

    document.addEventListener('change', (event) => {
        const form = event.target.closest?.('form[data-setup-assignment-form]');

        if (form && event.target.matches('[data-setup-assigned]')) {
            refresh(form);
        }
    });

    document.addEventListener('submit', confirmRevocations);
}
