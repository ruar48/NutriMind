/**
 * Printing.
 *
 * API: <button type="button" data-print> opens the browser print dialog. While printing, every chart's
 * "View as table" disclosure (details[data-chart-table]) is opened so the numbers reach the paper, and
 * restored afterwards. (The theme module switches the page to light colors for printing.)
 */
export default function boot() {
    let openedForPrint = [];

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-print]')) {
            event.preventDefault();
            window.print();
        }
    });

    window.addEventListener('beforeprint', () => {
        openedForPrint = [...document.querySelectorAll('details[data-chart-table]:not([open])')];
        openedForPrint.forEach((details) => {
            details.open = true;
        });
    });

    window.addEventListener('afterprint', () => {
        openedForPrint.forEach((details) => {
            details.open = false;
        });
        openedForPrint = [];
    });
}
