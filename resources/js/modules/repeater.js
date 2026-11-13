/**
 * Repeatable rows (quiz choices, list items).
 *
 * API:
 *   <div data-repeater="choices" data-repeater-min="2" data-repeater-max="6">
 *     <div data-repeater-items> …rows… </div>
 *     <template data-repeater-template> <div data-repeater-item> …uses __INDEX__… </div> </template>
 *     <button type="button" data-repeater-add>Add</button>
 *   </div>
 * Rows are [data-repeater-item] with a [data-repeater-remove] button. After every add/remove the rows are
 * re-indexed: name="choices[N]…", ids/for/aria-* "choices-N…", [data-repeater-value] gets value=N,
 * [data-repeater-number] shows N+1 and [data-repeater-letter] shows A, B, C…
 * Add is disabled at max, remove at min. Fires a bubbling `repeater:change` event with { count }.
 * Optional data-repeater-id overrides the id prefix (defaults to the name with brackets turned into dashes).
 * A new row focuses its [data-repeater-focus] element, else its first text-like field.
 */
const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const FOCUS_TARGETS = [
    '[data-repeater-focus]',
    'input[type="text"], input:not([type]), input[type="number"], input[type="email"], input[type="url"], textarea, select',
    'input:not([type="hidden"])',
];

const focusTargetIn = (row) => {
    for (const selector of FOCUS_TARGETS) {
        const target = row?.querySelector(selector);

        if (target) {
            return target;
        }
    }

    return null;
};
const ID_ATTRIBUTES = ['id', 'for', 'aria-describedby', 'aria-labelledby', 'aria-controls', 'data-char-count-for', 'data-image-preview', 'data-file-name-for'];

const escapeRegExp = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const idPrefixFor = (container) =>
    container.dataset.repeaterId ??
    container.dataset.repeater
        .replace(/\[/g, '-')
        .replace(/\]/g, '')
        .replace(/\./g, '-')
        .replace(/^-+|-+$/g, '');

const itemsOf = (container) =>
    [...container.querySelectorAll('[data-repeater-item]')].filter((item) => item.closest('[data-repeater]') === container);

function reindex(container) {
    const namePattern = new RegExp(`^(${escapeRegExp(container.dataset.repeater)})\\[(?:\\d+|__INDEX__)\\]`);
    const idPattern = new RegExp(`(^|\\s)(${escapeRegExp(idPrefixFor(container))})-(?:\\d+|__INDEX__)(?=$|[-_\\s])`, 'g');
    const items = itemsOf(container);

    items.forEach((item, index) => {
        item.querySelectorAll('[name]').forEach((field) => {
            field.setAttribute('name', field.getAttribute('name').replace(namePattern, `$1[${index}]`));
        });

        [item, ...item.querySelectorAll('*')].forEach((element) => {
            ID_ATTRIBUTES.forEach((attribute) => {
                const value = element.getAttribute(attribute);

                if (value) {
                    element.setAttribute(attribute, value.replace(idPattern, `$1$2-${index}`));
                }
            });
        });

        item.querySelectorAll('[data-repeater-value]').forEach((field) => {
            field.value = String(index);
        });

        item.querySelectorAll('[data-repeater-number]').forEach((label) => {
            label.textContent = String(index + 1);
        });

        item.querySelectorAll('[data-repeater-letter]').forEach((label) => {
            label.textContent = LETTERS[index] ?? String(index + 1);
        });
    });

    const min = Number(container.dataset.repeaterMin ?? 0);
    const max = Number(container.dataset.repeaterMax ?? Infinity);

    container.querySelectorAll('[data-repeater-add]').forEach((button) => {
        if (button.closest('[data-repeater]') === container) {
            button.disabled = items.length >= max;
        }
    });

    items.forEach((item) => {
        item.querySelectorAll('[data-repeater-remove]').forEach((button) => {
            button.disabled = items.length <= min;
        });
    });

    container.dispatchEvent(new CustomEvent('repeater:change', { bubbles: true, detail: { count: items.length } }));
}

function add(container) {
    const template = [...container.querySelectorAll('template[data-repeater-template]')].find(
        (candidate) => candidate.closest('[data-repeater]') === container,
    );
    const list = [...container.querySelectorAll('[data-repeater-items]')].find((candidate) => candidate.closest('[data-repeater]') === container);
    const max = Number(container.dataset.repeaterMax ?? Infinity);

    if (!template || !list || itemsOf(container).length >= max) {
        return;
    }

    const row = template.content.firstElementChild.cloneNode(true);
    list.append(row);
    reindex(container);
    focusTargetIn(row)?.focus();
}

function remove(item) {
    const container = item.closest('[data-repeater]');
    const items = itemsOf(container);
    const min = Number(container.dataset.repeaterMin ?? 0);

    if (items.length <= min) {
        return;
    }

    const index = items.indexOf(item);
    item.remove();
    reindex(container);

    const remaining = itemsOf(container);
    const focusTarget = focusTargetIn(remaining[Math.max(0, index - 1)]) ?? container.querySelector('[data-repeater-add]');
    focusTarget?.focus();
}

export default function boot() {
    document.querySelectorAll('[data-repeater]').forEach(reindex);

    document.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-repeater-add]');

        if (addButton) {
            event.preventDefault();
            add(addButton.closest('[data-repeater]'));

            return;
        }

        const removeButton = event.target.closest('[data-repeater-remove]');
        const item = removeButton?.closest('[data-repeater-item]');

        if (item) {
            event.preventDefault();
            remove(item);
        }
    });
}
