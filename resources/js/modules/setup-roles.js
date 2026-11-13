/**
 * Unsaved permission changes (admin roles & permissions matrix).
 *
 * API: in form[data-setup-roles-form], [data-setup-roles-changes] counts the permission checkboxes whose state
 * differs from the saved one ("3 unsaved changes") and shows its data-none text again when nothing is pending.
 */
function refresh(form) {
    const target = form.querySelector('[data-setup-roles-changes]');

    if (!target) {
        return;
    }

    const changed = [...form.querySelectorAll('input[type="checkbox"][name^="permissions["]')].filter(
        (box) => box.checked !== box.defaultChecked,
    ).length;

    if (changed === 0) {
        target.textContent = target.dataset.none ?? '';

        return;
    }

    target.textContent = `${changed} unsaved ${changed === 1 ? 'change' : 'changes'}. Save to apply ${changed === 1 ? 'it' : 'them'}.`;
}

export default function boot() {
    document.addEventListener('change', (event) => {
        const form = event.target.closest?.('form[data-setup-roles-form]');

        if (form) {
            refresh(form);
        }
    });
}
