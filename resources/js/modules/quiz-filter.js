/**
 * Quiz settings and question bank forms: keep the learning-module list and the classroom list in step with the
 * chosen subject and grade.
 *
 * API:
 *   <form data-quiz-filter> — optional data-quiz-subject / data-quiz-grade hold fixed values when the selects
 *   are not on the page (settings locked by learner attempts).
 *   select[data-quiz-subject-select], select[data-quiz-grade-select] — drive the filter.
 *   select[data-quiz-module-select] > optgroup[data-quiz-match-subject][data-quiz-match-grade] — module groups;
 *   groups of another subject or grade are hidden and their options disabled; a hidden selection is cleared.
 *   [data-quiz-classroom][data-quiz-match-subject][data-quiz-match-grade] — classroom checkbox wrappers; others are
 *   hidden and their checkboxes disabled (never submitted). Checkboxes marked data-quiz-locked are left alone.
 *   [data-quiz-classrooms-empty] — shown when no classroom matches.
 * Without JavaScript every option stays visible and the server rejects mismatches.
 */
function chosenValues(form) {
    const subjectSelect = form.querySelector('select[data-quiz-subject-select]');
    const gradeSelect = form.querySelector('select[data-quiz-grade-select]');

    return {
        subject: subjectSelect ? subjectSelect.value : (form.dataset.quizSubject ?? ''),
        grade: gradeSelect ? gradeSelect.value : (form.dataset.quizGrade ?? ''),
    };
}

function matches(element, { subject, grade }) {
    const subjectMatches = subject === '' || element.dataset.quizMatchSubject === subject;
    const gradeMatches = grade === '' || element.dataset.quizMatchGrade === grade;

    return subjectMatches && gradeMatches;
}

function filterModules(form, chosen) {
    const select = form.querySelector('select[data-quiz-module-select]');

    if (!select) {
        return;
    }

    select.querySelectorAll('optgroup[data-quiz-match-subject]').forEach((group) => {
        const visible = matches(group, chosen);

        group.hidden = !visible;
        group.disabled = !visible;

        if (!visible && [...group.querySelectorAll('option')].some((option) => option.selected)) {
            select.value = '';
        }
    });
}

function filterClassrooms(form, chosen) {
    const wrappers = [...form.querySelectorAll('[data-quiz-classroom]')];

    if (wrappers.length === 0) {
        return;
    }

    let visibleCount = 0;

    wrappers.forEach((wrapper) => {
        const visible = matches(wrapper, chosen);

        wrapper.hidden = !visible;
        visibleCount += visible ? 1 : 0;

        wrapper.querySelectorAll('input[type="checkbox"]:not([data-quiz-locked])').forEach((checkbox) => {
            checkbox.disabled = !visible;
        });
    });

    form.querySelectorAll('[data-quiz-classrooms-empty]').forEach((notice) => {
        notice.hidden = visibleCount > 0;
    });
}

function sync(form) {
    const chosen = chosenValues(form);

    filterModules(form, chosen);
    filterClassrooms(form, chosen);
}

export default function boot() {
    document.querySelectorAll('form[data-quiz-filter]').forEach(sync);

    document.addEventListener('change', (event) => {
        const select = event.target.closest?.('select[data-quiz-subject-select], select[data-quiz-grade-select]');
        const form = select?.closest('form[data-quiz-filter]');

        if (form) {
            sync(form);
        }
    });
}
