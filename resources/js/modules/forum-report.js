/**
 * Forum report forms.
 *
 * API: select[data-forum-report-reason] becomes required once JS runs, so the browser asks for a reason inside the
 * open report dialog. Without JS the field stays optional in the browser (a required field inside a closed dialog
 * would block the form) and the server validates it instead.
 */
export default function boot() {
    document.querySelectorAll('select[data-forum-report-reason]').forEach((select) => {
        select.required = true;
    });
}
