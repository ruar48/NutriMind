/**
 * Game mission ID suggestion on the admin mission form.
 *
 * API: <form data-mission-game-key data-game-missions='[{"key":"G5-PEH-M1","title":"…","world":"PEH","grade":5,
 * "number":1,"taken":false}]' data-game-subject-worlds='{"3":"PEH"}'> containing the subject_id and grade_level
 * selects, an [data-game-key-input] input and an optional [data-game-key-status] line.
 * When the subject belongs to a game world and one grade is chosen, the input is filled with the first game mission
 * of that world and grade that no other mission uses, until the administrator types an ID of their own; clearing
 * the input lets the suggestion take over again. The status line names the game mission the ID points to.
 */
function readJson(value, fallback) {
    try {
        return JSON.parse(value || '') ?? fallback;
    } catch {
        return fallback;
    }
}

function context(form) {
    const missions = readJson(form.dataset.gameMissions, []);
    const worlds = readJson(form.dataset.gameSubjectWorlds, {});
    const subject = form.querySelector('select[name="subject_id"]')?.value ?? '';
    const grade = Number.parseInt(form.querySelector('select[name="grade_level"]')?.value ?? '', 10);

    return { missions: Array.isArray(missions) ? missions : [], world: worlds[subject] ?? null, grade };
}

function suggestion({ missions, world, grade }) {
    if (world === null || Number.isNaN(grade)) {
        return '';
    }

    return missions.find((mission) => mission.world === world && mission.grade === grade && !mission.taken)?.key ?? '';
}

function status(form, input) {
    const line = form.querySelector('[data-game-key-status]');

    if (!line) {
        return;
    }

    const ctx = context(form);
    const key = input.value.trim().toUpperCase();
    const match = ctx.missions.find((mission) => mission.key === key);
    let text = '';

    if (match) {
        text = match.taken
            ? `${match.key} is already used by another mission.`
            : `Links to the game mission “${match.title}”.`;
    } else if (key !== '') {
        text = 'The game has no mission with this ID, so no progress will reach it.';
    } else if (ctx.world !== null && Number.isNaN(ctx.grade)) {
        text = 'Choose Grade 5 or Grade 6 to fill in a game mission ID: each game mission is for one grade.';
    } else if (ctx.world !== null) {
        text = `Every Grade ${ctx.grade} ${ctx.world} game mission is already used by another mission.`;
    }

    line.textContent = text;
    line.hidden = text === '';
}

function refresh(form) {
    const input = form.querySelector('[data-game-key-input]');

    if (!input) {
        return;
    }

    const current = input.value.trim();
    const followsSuggestion = current === '' || current === (input.dataset.gameKeySuggested ?? '');

    if (followsSuggestion) {
        const next = suggestion(context(form));
        input.value = next;
        input.dataset.gameKeySuggested = next;
    }

    status(form, input);
}

function handle(event) {
    const field = event.target;

    if (!(field instanceof HTMLElement) || !field.matches('select[name="subject_id"], select[name="grade_level"], [data-game-key-input]')) {
        return;
    }

    const form = field.closest('[data-mission-game-key]');

    if (!form) {
        return;
    }

    if (field.matches('[data-game-key-input]')) {
        const input = /** @type {HTMLInputElement} */ (field);
        const upper = input.value.toUpperCase();

        if (upper !== input.value) {
            const caret = input.selectionStart;
            input.value = upper;
            input.setSelectionRange(caret, caret);
        }

        if (input.value.trim() === '') {
            input.dataset.gameKeySuggested = '';
        }

        status(form, input);

        return;
    }

    refresh(form);
}

export default function boot() {
    document.querySelectorAll('[data-mission-game-key]').forEach((form) => {
        const input = form.querySelector('[data-game-key-input]');

        if (!input) {
            return;
        }

        if (input.value.trim() === '') {
            refresh(form);
        } else {
            status(form, input);
        }
    });

    document.addEventListener('input', handle);
    document.addEventListener('change', handle);
}
