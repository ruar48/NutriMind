/**
 * Learner CSV upload: drop a file onto [data-imports-dropzone] to choose it.
 *
 * API: <div data-imports-dropzone> wraps one <input type="file">. While a file is dragged over the zone it gets
 * [data-dragging]; a dropped file becomes the input's file and a bubbling change event lets file-input.js show its
 * name. Without JS the regular file chooser still works.
 */
function zoneOf(event) {
    return event.target instanceof Element ? event.target.closest('[data-imports-dropzone]') : null;
}

function carriesFiles(event) {
    return Array.from(event.dataTransfer?.types ?? []).includes('Files');
}

export default function boot() {
    document.addEventListener('dragover', (event) => {
        const zone = zoneOf(event);

        if (!zone || !carriesFiles(event)) {
            return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
        zone.setAttribute('data-dragging', '');
    });

    document.addEventListener('dragleave', (event) => {
        const zone = zoneOf(event);

        if (zone && !(event.relatedTarget instanceof Node && zone.contains(event.relatedTarget))) {
            zone.removeAttribute('data-dragging');
        }
    });

    document.addEventListener('drop', (event) => {
        const zone = zoneOf(event);

        if (!zone || !carriesFiles(event)) {
            return;
        }

        event.preventDefault();
        zone.removeAttribute('data-dragging');

        const input = zone.querySelector('input[type="file"]');
        const file = event.dataTransfer.files?.[0];

        if (!(input instanceof HTMLInputElement) || !file) {
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
}
