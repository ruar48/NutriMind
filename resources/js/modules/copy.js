/**
 * Copy to clipboard.
 *
 * API: <button data-copy="text to copy"> or <button data-copy-target="#element-id"> (copies its text or
 * value). Optional data-copy-feedback="Copied!". The label inside [data-copy-label] swaps to the feedback
 * for 2 s and [data-copy-status] (a polite live region) announces it. Falls back to execCommand outside
 * secure contexts.
 */
const RESET_MS = 2000;
const resetTimers = new WeakMap();

async function writeText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);

        return;
    }

    const scratch = document.createElement('textarea');
    scratch.value = text;
    scratch.setAttribute('readonly', '');
    scratch.style.position = 'fixed';
    scratch.style.opacity = '0';
    document.body.append(scratch);
    scratch.select();
    const copied = document.execCommand('copy');
    scratch.remove();

    if (!copied) {
        throw new Error('Copy command was rejected');
    }
}

function sourceText(button) {
    if (button.hasAttribute('data-copy')) {
        return button.getAttribute('data-copy');
    }

    const target = document.querySelector(button.dataset.copyTarget);

    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
        return target.value;
    }

    return target?.textContent.trim() ?? '';
}

function feedback(button, succeeded) {
    const label = button.querySelector('[data-copy-label]');
    const status = button.querySelector('[data-copy-status]');
    const message = succeeded ? button.dataset.copyFeedback || 'Copied!' : 'Copy failed';

    if (label && !label.hasAttribute('data-copy-original')) {
        label.setAttribute('data-copy-original', label.textContent);
    }

    if (label) {
        label.textContent = message;
    }

    if (status) {
        status.textContent = message;
    }

    button.setAttribute('data-copied', succeeded ? 'true' : 'false');

    window.clearTimeout(resetTimers.get(button));
    resetTimers.set(
        button,
        window.setTimeout(() => {
            if (label) {
                label.textContent = label.getAttribute('data-copy-original');
            }

            if (status) {
                status.textContent = '';
            }

            button.removeAttribute('data-copied');
        }, RESET_MS),
    );
}

export default function boot() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy], [data-copy-target]');

        if (!button) {
            return;
        }

        event.preventDefault();

        try {
            await writeText(sourceText(button));
            feedback(button, true);
        } catch {
            feedback(button, false);
        }
    });
}
