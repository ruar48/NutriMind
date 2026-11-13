/**
 * Select-all checkboxes.
 *
 * API: <input type="checkbox" data-check-all="group"> toggles every enabled
 * <input type="checkbox" data-check-group="group">; it turns indeterminate when only some are checked.
 * [data-check-count="group"] shows how many are checked; [data-check-requires="group"] buttons stay
 * disabled until at least one is checked.
 */
function sync(group) {
    const escaped = CSS.escape(group);
    const boxes = [...document.querySelectorAll(`input[data-check-group="${escaped}"]`)].filter((box) => !box.disabled);
    const checked = boxes.filter((box) => box.checked).length;

    document.querySelectorAll(`input[data-check-all="${escaped}"]`).forEach((master) => {
        master.checked = boxes.length > 0 && checked === boxes.length;
        master.indeterminate = checked > 0 && checked < boxes.length;
    });

    document.querySelectorAll(`[data-check-count="${escaped}"]`).forEach((counter) => {
        counter.textContent = String(checked);
    });

    document.querySelectorAll(`[data-check-requires="${escaped}"]`).forEach((control) => {
        control.disabled = checked === 0;
    });
}

export default function boot() {
    const groups = new Set();
    document.querySelectorAll('[data-check-all], [data-check-group]').forEach((element) => {
        groups.add(element.dataset.checkAll ?? element.dataset.checkGroup);
    });
    groups.forEach(sync);

    document.addEventListener('change', (event) => {
        const box = event.target;

        if (!(box instanceof HTMLInputElement)) {
            return;
        }

        if (box.dataset.checkAll) {
            document.querySelectorAll(`input[data-check-group="${CSS.escape(box.dataset.checkAll)}"]`).forEach((member) => {
                if (!member.disabled) {
                    member.checked = box.checked;
                }
            });
            sync(box.dataset.checkAll);

            return;
        }

        if (box.dataset.checkGroup) {
            sync(box.dataset.checkGroup);
        }
    });
}
