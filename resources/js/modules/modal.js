/**
 * Native <dialog> modals and confirm dialogs.
 *
 * API: [data-modal-open="name"] opens <dialog data-modal="name">; [data-confirm-open="dialog-id"] opens a
 * confirm dialog (its trigger is a real submit button, so without JS the form still submits);
 * [data-modal-close] inside a dialog closes it; a click on the backdrop closes it; Esc is native.
 * <dialog data-modal-autoopen> opens on page load (used to re-open a form modal after a validation error).
 */
const FOCUS_TARGET = '[autofocus], [data-modal-initial-focus], input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])';

export function openDialog(dialog) {
    if (!(dialog instanceof HTMLDialogElement)) {
        return;
    }

    if (dialog.open) {
        dialog.close();
    }

    dialog.showModal();
    dialog.querySelector(FOCUS_TARGET)?.focus();
}

export default function boot() {
    let pointerDownTarget = null;

    document.addEventListener('pointerdown', (event) => {
        pointerDownTarget = event.target;
    });

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-modal-open]');

        if (opener) {
            event.preventDefault();
            openDialog(document.querySelector(`dialog[data-modal="${CSS.escape(opener.dataset.modalOpen)}"]`));

            return;
        }

        const confirmTrigger = event.target.closest('[data-confirm-open]');

        if (confirmTrigger) {
            const dialog = document.getElementById(confirmTrigger.dataset.confirmOpen);

            // Enter inside an open confirm dialog "clicks" the form's first submit button (the trigger):
            // that is a confirmation, so let the submit through.
            if (dialog instanceof HTMLDialogElement && dialog.open) {
                return;
            }

            event.preventDefault();
            openDialog(dialog);

            return;
        }

        const closer = event.target.closest('[data-modal-close]');

        if (closer) {
            const dialog = closer.closest('dialog');

            if (dialog) {
                event.preventDefault();
                dialog.close();
            }

            return;
        }

        const dialog = event.target;

        if (dialog instanceof HTMLDialogElement && dialog.open && pointerDownTarget === dialog) {
            dialog.close();
        }
    });

    document.querySelectorAll('dialog[data-modal-autoopen]').forEach((dialog) => openDialog(dialog));
}
