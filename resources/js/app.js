/**
 * NutriMind progressive-enhancement behaviors.
 *
 * Every file in ./modules is imported eagerly. A module wires its data-attribute API by exporting a
 * default `boot()` function, which runs once when the DOM is ready. Modules rely on event delegation
 * on `document`, so markup added later (repeater rows, cloned templates) keeps working.
 */
const modules = import.meta.glob('./modules/*.js', { eager: true });

function boot() {
    for (const [path, module] of Object.entries(modules)) {
        if (typeof module.default !== 'function') {
            continue;
        }

        try {
            module.default();
        } catch (error) {
            console.error(`[nutrimind] ${path} failed to boot`, error);
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
