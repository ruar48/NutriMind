/**
 * Password reveal.
 *
 * API: <button type="button" data-password-toggle aria-controls="input-id" aria-pressed="false"
 * data-label-show="Show password" data-label-hide="Hide password">. Toggles the input between password and
 * text, keeps aria-pressed/aria-label in sync, and masks the field again when its form submits so password
 * managers never see a plain-text field.
 */
export default function boot() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-password-toggle]');

        if (!button) {
            return;
        }

        const input = document.getElementById(button.getAttribute('aria-controls'));

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(reveal));

        const label = reveal ? button.dataset.labelHide : button.dataset.labelShow;

        if (label) {
            button.setAttribute('aria-label', label);
        }

        input.focus();
    });

    document.addEventListener('submit', (event) => {
        event.target.querySelectorAll?.('[data-password-input]').forEach((input) => {
            input.type = 'password';
        });

        event.target.querySelectorAll?.('[data-password-toggle]').forEach((button) => {
            button.setAttribute('aria-pressed', 'false');
        });
    });
}
