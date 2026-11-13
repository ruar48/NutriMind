/**
 * Conditional field groups.
 *
 * API: <div data-show-when="type:multiple_choice,true_false"> … </div> is visible only while the field named
 * `type` (in the same form) has one of the listed values; prefix the values with ! to negate
 * (data-show-when="type:!identification"). Hidden groups get the `hidden` attribute AND every input, select and
 * textarea inside is disabled, so it is neither validated by the browser nor submitted. Fields that were
 * disabled in the markup stay disabled. Render the correct initial `hidden` state on the server to avoid a flash.
 */
const CONTROLS = 'input, select, textarea';

function parse(spec) {
    const separator = spec.indexOf(':');
    const field = spec.slice(0, separator).trim();
    let values = spec.slice(separator + 1).trim();
    const negate = values.startsWith('!');

    if (negate) {
        values = values.slice(1);
    }

    return {
        field,
        negate,
        values: values
            .split(',')
            .map((value) => value.trim())
            .filter((value) => value !== ''),
    };
}

function currentValues(scope, field) {
    return [...scope.querySelectorAll(`[name="${CSS.escape(field)}"]`)]
        .filter((control) => !(control.type === 'checkbox' || control.type === 'radio') || control.checked)
        .map((control) => control.value);
}

function evaluate(group) {
    const { field, negate, values } = parse(group.dataset.showWhen);
    const scope = group.closest('form') ?? document;
    const matches = currentValues(scope, field).some((value) => values.includes(value));
    const visible = negate ? !matches : matches;

    group.hidden = !visible;

    group.querySelectorAll(CONTROLS).forEach((control) => {
        if (!control.hasAttribute('data-show-when-initial')) {
            control.setAttribute('data-show-when-initial', control.disabled ? 'disabled' : 'enabled');
        }

        control.disabled = !visible || control.getAttribute('data-show-when-initial') === 'disabled';
    });
}

const evaluateAll = () => document.querySelectorAll('[data-show-when]').forEach(evaluate);

export default function boot() {
    evaluateAll();

    const onChange = (event) => {
        if (event.target instanceof Element && event.target.name) {
            evaluateAll();
        }
    };

    document.addEventListener('change', onChange);
    document.addEventListener('input', onChange);
}
