/**
 * Classroom name suggestion.
 *
 * API: <form data-classrooms-name-suggest> containing [data-classrooms-section] (input) and
 * [data-classrooms-name] (input), plus either
 *   - [data-classrooms-subject] and [data-classrooms-grade] selects, with the subjects' short names on the form as
 *     data-classrooms-subject-names='{"3":"Health"}' (new classroom), or
 *   - data-classrooms-fixed-subject="Health" and data-classrooms-fixed-grade="5" on the form (edit).
 * The name follows "{subject} {grade} – {section}" (e.g. "Health 5 – Sampaguita") until the teacher types a name
 * of their own; clearing the name lets the suggestion take over again.
 */
function subjectNames(form) {
    try {
        const names = JSON.parse(form.dataset.classroomsSubjectNames || '{}');

        return names && typeof names === 'object' ? names : {};
    } catch {
        return {};
    }
}

function suggestion(form) {
    const subjectSelect = form.querySelector('select[data-classrooms-subject]');
    const gradeSelect = form.querySelector('select[data-classrooms-grade]');
    const sectionInput = form.querySelector('[data-classrooms-section]');

    const subject = subjectSelect ? (subjectNames(form)[subjectSelect.value] ?? '') : (form.dataset.classroomsFixedSubject ?? '');
    const grade = gradeSelect ? gradeSelect.value : (form.dataset.classroomsFixedGrade ?? '');
    const section = (sectionInput?.value ?? '').trim().replace(/\s+/g, ' ');

    if (subject === '' || grade === '' || section === '') {
        return '';
    }

    return `${subject} ${grade} – ${section}`;
}

function refresh(form) {
    const nameInput = form.querySelector('[data-classrooms-name]');

    if (!nameInput) {
        return;
    }

    const current = nameInput.value.trim();
    const followsSuggestion = current === '' || current === (nameInput.dataset.classroomsSuggested ?? '');
    const next = suggestion(form);

    if (followsSuggestion && next !== '') {
        nameInput.value = next;
        nameInput.dataset.classroomsSuggested = next;
    }
}

function handle(event) {
    const field = event.target;

    if (!(field instanceof HTMLElement) || !field.matches('[data-classrooms-subject], [data-classrooms-grade], [data-classrooms-section]')) {
        return;
    }

    const form = field.closest('[data-classrooms-name-suggest]');

    if (form) {
        refresh(form);
    }
}

export default function boot() {
    document.querySelectorAll('[data-classrooms-name-suggest]').forEach((form) => {
        const nameInput = form.querySelector('[data-classrooms-name]');
        const initial = suggestion(form);

        if (nameInput && initial !== '' && nameInput.value.trim() === initial) {
            nameInput.dataset.classroomsSuggested = initial;
        }
    });

    document.addEventListener('input', handle);
    document.addEventListener('change', handle);
}
