/**
 * Character counters.
 *
 * API: <textarea id="x" data-max-length="500"> with <span data-char-count-for="x">0 / 500</span>. The
 * counter updates while typing and gets data-near-limit (>= 90 %) and data-over-limit (> max) for styling.
 * Pair it with the maxlength attribute or server validation; the counter itself never blocks typing.
 */
function update(field) {
    const max = Number(field.dataset.maxLength);

    if (!field.id || !Number.isFinite(max) || max <= 0) {
        return;
    }

    const length = field.value.length;

    document.querySelectorAll(`[data-char-count-for="${CSS.escape(field.id)}"]`).forEach((counter) => {
        counter.textContent = `${length.toLocaleString()} / ${max.toLocaleString()}`;
        counter.toggleAttribute('data-near-limit', length >= max * 0.9 && length <= max);
        counter.toggleAttribute('data-over-limit', length > max);
    });
}

export default function boot() {
    document.querySelectorAll('[data-max-length]').forEach(update);

    document.addEventListener('input', (event) => {
        if (event.target instanceof HTMLElement && event.target.matches('[data-max-length]')) {
            update(event.target);
        }
    });
}
