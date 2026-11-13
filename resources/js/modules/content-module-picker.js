/**
 * Learning-module pickers (mission and lesson plan forms).
 *
 * API: <select data-content-module-picker data-content-subject-field="subject_id" [data-content-grade-field="grade_level"]>
 * whose <option>s carry data-subject / data-grade (see components/content/module-options.blade.php).
 * Options of another subject (or, once a grade is chosen, another grade) are hidden and disabled; a selection
 * that no longer fits is cleared. Picking a module copies its data-code / data-competency into the form's empty
 * [data-content-fill="code"] / [data-content-fill="competency"] fields. Without JS every option stays available
 * and the server validates the choice.
 */
function fieldValue(form, name) {
    if (!form || !name) {
        return '';
    }

    const field = form.elements.namedItem(name);

    return field && 'value' in field ? String(field.value) : '';
}

function sync(picker) {
    const subject = fieldValue(picker.form, picker.dataset.contentSubjectField);
    const grade = fieldValue(picker.form, picker.dataset.contentGradeField);

    picker.querySelectorAll('option[data-subject]').forEach((option) => {
        const fits = (subject === '' || option.dataset.subject === subject) && (grade === '' || option.dataset.grade === grade);

        option.hidden = !fits;
        option.disabled = !fits;
    });

    picker.querySelectorAll('optgroup[data-content-module-group]').forEach((group) => {
        group.hidden = [...group.querySelectorAll('option')].every((option) => option.hidden);
    });

    const selected = picker.selectedOptions[0];

    if (selected && selected.disabled) {
        picker.value = '';
    }
}

function fill(picker) {
    const selected = picker.selectedOptions[0];

    if (!selected || !picker.form) {
        return;
    }

    [['code', selected.dataset.code], ['competency', selected.dataset.competency]].forEach(([key, value]) => {
        const field = picker.form.querySelector(`[data-content-fill="${key}"]`);

        if (field && value && field.value.trim() === '') {
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
}

export default function boot() {
    document.querySelectorAll('select[data-content-module-picker]').forEach(sync);

    document.addEventListener('change', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLElement) || !('form' in target) || !target.form) {
            return;
        }

        target.form.querySelectorAll('select[data-content-module-picker]').forEach((picker) => {
            if (picker === target) {
                fill(picker);

                return;
            }

            const name = target.getAttribute('name');

            if (name && (name === picker.dataset.contentSubjectField || name === picker.dataset.contentGradeField)) {
                sync(picker);
            }
        });
    });
}
