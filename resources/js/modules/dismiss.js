/**
 * Dismissible blocks and the announcement banner.
 *
 * API: a [data-dismiss] button removes its closest [data-dismissible] element (alerts).
 * The announcement banner is <div data-announcement="hash"> with a [data-announcement-dismiss] button; the
 * hash is remembered in localStorage so the same text stays hidden (the inline <head> script hides it
 * before paint on later visits). A new announcement text has a new hash and shows again.
 */
export const ANNOUNCEMENT_STORAGE_KEY = 'nutrimind.announcement.dismissed';

export default function boot() {
    document.addEventListener('click', (event) => {
        const dismissButton = event.target.closest('[data-dismiss]');

        if (dismissButton) {
            dismissButton.closest('[data-dismissible]')?.remove();

            return;
        }

        const announcementButton = event.target.closest('[data-announcement-dismiss]');
        const banner = announcementButton?.closest('[data-announcement]');

        if (!banner) {
            return;
        }

        try {
            window.localStorage.setItem(ANNOUNCEMENT_STORAGE_KEY, banner.dataset.announcement);
        } catch {
            // Without storage the banner is hidden for this page view only.
        }

        banner.hidden = true;
    });
}
