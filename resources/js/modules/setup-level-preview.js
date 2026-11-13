/**
 * Level preview (admin settings, gamification section).
 *
 * API: in form[data-setup-settings-form], [data-setup-level-preview] (data-sample-points="1250") recomputes the
 * sample learner's level ([data-setup-preview-level]) and the points of the highest level
 * ([data-setup-preview-top]) from [data-setup-points-per-level] and [data-setup-max-level], using the same rule
 * as the server: level = min(highest level, floor(points / points per level) + 1).
 */
const numberFormat = new Intl.NumberFormat('en-US');

function refresh(form) {
    const preview = form.querySelector('[data-setup-level-preview]');
    const pointsPerLevel = Number(form.querySelector('[data-setup-points-per-level]')?.value);
    const maxLevel = Number(form.querySelector('[data-setup-max-level]')?.value);

    if (!preview || !Number.isInteger(pointsPerLevel) || pointsPerLevel < 1 || !Number.isInteger(maxLevel) || maxLevel < 1) {
        return;
    }

    const samplePoints = Number(preview.dataset.samplePoints ?? 0);
    const level = Math.min(maxLevel, Math.floor(samplePoints / pointsPerLevel) + 1);
    const levelTarget = preview.querySelector('[data-setup-preview-level]');
    const topTarget = preview.querySelector('[data-setup-preview-top]');

    if (levelTarget) {
        levelTarget.textContent = `Level ${level}`;
    }

    if (topTarget) {
        topTarget.textContent = `${numberFormat.format((maxLevel - 1) * pointsPerLevel)} XP`;
    }
}

export default function boot() {
    document.addEventListener('input', (event) => {
        const form = event.target.closest?.('form[data-setup-settings-form]');

        if (form && event.target.matches('[data-setup-points-per-level], [data-setup-max-level]')) {
            refresh(form);
        }
    });
}
