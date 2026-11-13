/**
 * Count-up numbers for KPI values.
 *
 * API: <span data-count-up="1284.5" data-count-decimals="1" data-count-prefix="" data-count-suffix="%">
 * 1,284.5%</span>. The server renders the final text (no JS = final value); the animation runs once when
 * the element scrolls into view, ends on the exact server text, and is skipped for reduced motion.
 */
const DURATION_MS = 900;
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

function animate(element) {
    const target = Number(element.dataset.countUp);

    if (!Number.isFinite(target)) {
        return;
    }

    const finalText = element.textContent;
    const decimals = Math.max(0, Math.min(2, Number(element.dataset.countDecimals ?? 0)));
    const prefix = element.dataset.countPrefix ?? '';
    const suffix = element.dataset.countSuffix ?? '';
    const formatter = new Intl.NumberFormat(document.documentElement.lang || undefined, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
    const startedAt = performance.now();

    const frame = (now) => {
        const progress = Math.min(1, (now - startedAt) / DURATION_MS);
        const eased = 1 - (1 - progress) ** 3;

        if (progress < 1) {
            element.textContent = `${prefix}${formatter.format(target * eased)}${suffix}`;
            window.requestAnimationFrame(frame);
        } else {
            element.textContent = finalText;
        }
    };

    window.requestAnimationFrame(frame);
}

export default function boot() {
    const elements = document.querySelectorAll('[data-count-up]');

    if (elements.length === 0 || reducedMotion.matches || !('IntersectionObserver' in window)) {
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    animate(entry.target);
                }
            });
        },
        { threshold: 0.4 },
    );

    elements.forEach((element) => observer.observe(element));
}
