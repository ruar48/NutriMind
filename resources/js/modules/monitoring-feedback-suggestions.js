/**
 * Feedback suggestions (teacher › feedback form).
 *
 * API: a button with data-monitoring-suggestion, data-monitoring-suggestion-form="form-id",
 * data-monitoring-suggestion-type="praise|encouragement|guidance|concern" and
 * data-monitoring-suggestion-message="…". Clicking it puts the message in the form's textarea[name="message"]
 * (firing an input event so the character counter updates), selects the matching input[name="type"] option and
 * focuses the textarea. Without JS the buttons are hidden (data-js-only) and the suggested text stays readable.
 */
import { showToast } from './toasts.js';

function applySuggestion(button) {
    const form = document.getElementById(button.dataset.monitoringSuggestionForm ?? '');

    if (!form) {
        return;
    }

    const message = form.querySelector('textarea[name="message"]');

    if (message) {
        message.value = button.dataset.monitoringSuggestionMessage ?? '';
        message.dispatchEvent(new Event('input', { bubbles: true }));
    }

    const type = button.dataset.monitoringSuggestionType ?? '';
    const option = type === '' ? null : form.querySelector(`input[name="type"][value="${CSS.escape(type)}"]`);

    if (option) {
        option.checked = true;
        option.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (message) {
        message.focus();
        message.setSelectionRange(message.value.length, message.value.length);
    }

    showToast('Suggestion added to your message. Make it personal before you send it.', 'success');
}

export default function boot() {
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-monitoring-suggestion]') : null;

        if (!button) {
            return;
        }

        event.preventDefault();
        applySuggestion(button);
    });
}
