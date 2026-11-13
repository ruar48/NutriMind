/**
 * Theme switch (light / dark / system), persisted in localStorage.
 *
 * API: <button data-theme-set="light|dark|system"> (aria-pressed marks the active choice). The inline <head>
 * script applies the stored theme before first paint; this module keeps it in sync with clicks, the OS
 * setting, other tabs and printing. <html data-theme-locked="light"> pins a page to one theme (print view).
 */
export const THEME_STORAGE_KEY = 'nutrimind.theme';

const PREFERENCES = ['light', 'dark', 'system'];
const systemDark = window.matchMedia('(prefers-color-scheme: dark)');

export function readThemePreference() {
    try {
        const stored = window.localStorage.getItem(THEME_STORAGE_KEY);

        return PREFERENCES.includes(stored) ? stored : 'system';
    } catch {
        return 'system';
    }
}

function storeThemePreference(preference) {
    try {
        if (preference === 'system') {
            window.localStorage.removeItem(THEME_STORAGE_KEY);
        } else {
            window.localStorage.setItem(THEME_STORAGE_KEY, preference);
        }
    } catch {
        // Storage can be unavailable (private mode); the choice then lasts for this page view.
    }
}

export function applyTheme(preference) {
    const root = document.documentElement;
    const locked = root.dataset.themeLocked;
    const isDark = locked ? locked === 'dark' : preference === 'dark' || (preference === 'system' && systemDark.matches);

    root.classList.toggle('dark', isDark);
    root.dataset.themePreference = preference;

    document.querySelectorAll('[data-theme-set]').forEach((option) => {
        option.setAttribute('aria-pressed', String(option.dataset.themeSet === preference));
    });

    document.querySelectorAll('meta[name="theme-color"]').forEach((meta) => {
        meta.setAttribute('content', isDark ? '#0d1410' : '#f7f6f1');
    });
}

export default function boot() {
    applyTheme(readThemePreference());

    document.addEventListener('click', (event) => {
        const option = event.target.closest('[data-theme-set]');

        if (!option) {
            return;
        }

        const preference = PREFERENCES.includes(option.dataset.themeSet) ? option.dataset.themeSet : 'system';

        storeThemePreference(preference);
        applyTheme(preference);
    });

    systemDark.addEventListener('change', () => {
        if (readThemePreference() === 'system') {
            applyTheme('system');
        }
    });

    window.addEventListener('storage', (event) => {
        if (event.key === THEME_STORAGE_KEY) {
            applyTheme(readThemePreference());
        }
    });

    window.addEventListener('beforeprint', () => {
        document.documentElement.classList.remove('dark');
    });

    window.addEventListener('afterprint', () => {
        applyTheme(readThemePreference());
    });
}
