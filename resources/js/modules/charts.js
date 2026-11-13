/**
 * Chart hover & focus layer. Charts are fully drawn on the server (inline SVG/HTML); this module only adds
 * the crosshair + tooltip (line, sparkline) and per-mark tooltips (bar, histogram, hbar, donut, stacked).
 *
 * Markup contract (rendered by the x-chart.* components):
 *   [data-chart="line|sparkline|bar|histogram|hbar|donut|stacked"]
 *     script[type="application/json"][data-chart-data]  payload (see COMPONENTS.md)
 *     [data-chart-plot]        focusable plot; ←/→ (or ↑/↓) step through positions, Home/End, Esc hides
 *       [data-chart-crosshair] vertical hairline (line, sparkline)
 *       [data-chart-hit]       pointer targets with data-index (bar-like charts)
 *       [data-chart-mark]      marks with data-index, highlighted via data-active
 *       [data-chart-tooltip]   the tooltip box
 *     [data-chart-legend-item] optional legend rows with data-index (hover highlights the part)
 * Labels are untrusted: every string is inserted with textContent.
 */
const element = (tag, className, text) => {
    const node = document.createElement(tag);

    if (className) {
        node.className = className;
    }

    if (text !== undefined) {
        node.textContent = text;
    }

    return node;
};

function formatValue(value, format) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }

    const number = Number(value).toLocaleString(document.documentElement.lang || undefined, { maximumFractionDigits: 1 });

    return format === 'percent' ? `${number}%` : number;
}

function renderTooltip(tooltip, title, rows, note) {
    tooltip.replaceChildren();

    if (title) {
        tooltip.append(element('p', 'mb-1.5 text-[11px] font-medium text-ink-500 dark:text-ink-400', title));
    }

    const list = element('ul', 'space-y-1');

    rows.forEach((row) => {
        const item = element('li', 'flex items-center gap-2 whitespace-nowrap');
        const key = element('span', 'h-0.5 w-3 shrink-0 rounded-full bg-(--viz)');
        key.dataset.viz = row.viz;
        item.append(key, element('span', 'text-sm font-semibold tabular-nums text-ink-900 dark:text-white', row.value));

        if (row.name) {
            item.append(element('span', 'text-xs text-ink-500 dark:text-ink-400', row.name));
        }

        list.append(item);
    });

    tooltip.append(list);

    if (note) {
        tooltip.append(element('p', 'mt-1.5 text-[11px] text-ink-500 dark:text-ink-400', note));
    }
}

function placeTooltip(tooltip, plot, x, y) {
    tooltip.hidden = false;

    const width = tooltip.offsetWidth;
    const height = tooltip.offsetHeight;
    const plotWidth = plot.clientWidth;

    let left = x + 14;

    if (left + width > plotWidth) {
        left = x - width - 14;
    }

    left = Math.max(-8, Math.min(left, plotWidth - width + 8));

    let top = y - height - 12;

    if (top < -8) {
        top = y + 14;
    }

    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${top}px`;
}

function bindKeyboard(plot, count, getIndex, show, hide) {
    plot.addEventListener('keydown', (event) => {
        const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];
        const index = getIndex();

        if (step !== undefined) {
            event.preventDefault();
            show(index === null ? (step > 0 ? 0 : count - 1) : Math.max(0, Math.min(count - 1, index + step)));
        } else if (event.key === 'Home') {
            event.preventDefault();
            show(0);
        } else if (event.key === 'End') {
            event.preventDefault();
            show(count - 1);
        } else if (event.key === 'Escape') {
            hide();
        }
    });
}

/** Line & sparkline: crosshair snaps to the nearest X; one tooltip lists every series. */
function seriesChart(chart, data) {
    const plot = chart.querySelector('[data-chart-plot]');
    const crosshair = chart.querySelector('[data-chart-crosshair]');
    const tooltip = chart.querySelector('[data-chart-tooltip]');
    const count = data.labels?.length ?? 0;

    if (!plot || !tooltip || count === 0) {
        return;
    }

    const range = data.max - data.min || 1;
    const xFor = (index) => (count === 1 ? 50 : (index / (count - 1)) * 100);
    const yFor = (value) => 100 - ((value - data.min) / range) * 100;
    const dots = data.series.map((series) => {
        const dot = element('span', 'pointer-events-none absolute size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-(--viz) ring-2 ring-(--chart-surface)');
        dot.dataset.viz = series.viz;
        dot.hidden = true;
        plot.append(dot);

        return dot;
    });
    let current = null;

    const show = (index) => {
        current = index;
        const x = xFor(index);
        let highest = 100;

        data.series.forEach((series, seriesIndex) => {
            const value = series.values[index];
            const dot = dots[seriesIndex];

            if (value === null || value === undefined) {
                dot.hidden = true;

                return;
            }

            const y = yFor(value);
            highest = Math.min(highest, y);
            dot.hidden = false;
            dot.style.left = `${x}%`;
            dot.style.top = `${y}%`;
        });

        if (crosshair) {
            crosshair.hidden = false;
            crosshair.style.left = `${x}%`;
        }

        renderTooltip(
            tooltip,
            data.labels[index],
            data.series.map((series) => ({ name: series.name, value: formatValue(series.values[index], data.format), viz: series.viz })),
        );
        placeTooltip(tooltip, plot, (x / 100) * plot.clientWidth, (highest / 100) * plot.clientHeight);
    };

    const hide = () => {
        current = null;
        tooltip.hidden = true;

        if (crosshair) {
            crosshair.hidden = true;
        }

        dots.forEach((dot) => {
            dot.hidden = true;
        });
    };

    const fromPointer = (event) => {
        const rect = plot.getBoundingClientRect();
        const ratio = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
        show(Math.round(ratio * (count - 1)));
    };

    plot.addEventListener('pointermove', fromPointer);
    plot.addEventListener('pointerdown', fromPointer);
    plot.addEventListener('pointerleave', hide);
    plot.addEventListener('focus', () => show(current ?? count - 1));
    plot.addEventListener('blur', hide);
    bindKeyboard(plot, count, () => current, show, hide);
}

/** Highlights marks sharing an index and dims the rest. */
function highlight(chart, index) {
    chart.toggleAttribute('data-hovering', index !== null);
    chart.querySelectorAll('[data-chart-mark], [data-chart-legend-item]').forEach((mark) => {
        mark.toggleAttribute('data-active', index !== null && Number(mark.dataset.index) === index);
    });
}

/** Vertical bars & histogram: each category column is the hit target; the tooltip lists its series. */
function groupChart(chart, data) {
    const plot = chart.querySelector('[data-chart-plot]');
    const tooltip = chart.querySelector('[data-chart-tooltip]');
    const hits = [...chart.querySelectorAll('[data-chart-hit]')];
    const count = data.labels?.length ?? 0;

    if (!plot || !tooltip || count === 0) {
        return;
    }

    let current = null;

    const show = (index) => {
        current = index;
        highlight(chart, index);

        const hit = hits.find((candidate) => Number(candidate.dataset.index) === index);
        const marks = [...chart.querySelectorAll(`[data-chart-mark][data-index="${index}"]`)];
        const plotRect = plot.getBoundingClientRect();
        const top = Math.min(plotRect.bottom, ...marks.map((mark) => mark.getBoundingClientRect().top));
        const hitRect = (hit ?? marks[0])?.getBoundingClientRect();

        renderTooltip(
            tooltip,
            data.labels[index],
            data.series.map((series) => ({ name: series.name, value: formatValue(series.values[index], data.format), viz: series.viz })),
        );

        if (hitRect) {
            placeTooltip(tooltip, plot, hitRect.left - plotRect.left + hitRect.width / 2, top - plotRect.top);
        }
    };

    const hide = () => {
        current = null;
        highlight(chart, null);
        tooltip.hidden = true;
    };

    hits.forEach((hit) => {
        hit.addEventListener('pointerenter', () => show(Number(hit.dataset.index)));
        hit.addEventListener('pointerdown', () => show(Number(hit.dataset.index)));
    });
    plot.addEventListener('pointerleave', hide);
    plot.addEventListener('focus', () => show(current ?? 0));
    plot.addEventListener('blur', hide);
    bindKeyboard(plot, count, () => current, show, hide);
}

/** Part-to-whole charts (hbar, donut, stacked): every part is its own target. */
function partsChart(chart, data) {
    const plot = chart.querySelector('[data-chart-plot]');
    const tooltip = chart.querySelector('[data-chart-tooltip]');
    const items = data.items ?? [];
    const count = items.length;

    if (!plot || !tooltip || count === 0) {
        return;
    }

    let current = null;

    const show = (index) => {
        current = index;
        highlight(chart, index);

        const item = items[index];
        const note = [item.share !== null && item.share !== undefined ? `${formatValue(item.share, 'percent')} of total` : null, item.sublabel]
            .filter(Boolean)
            .join(' · ');

        renderTooltip(tooltip, item.label, [{ name: '', value: formatValue(item.value, data.format), viz: item.viz }], note);

        const mark = chart.querySelector(`[data-chart-mark][data-index="${index}"]`);
        const plotRect = plot.getBoundingClientRect();

        if (mark?.dataset.anchorX) {
            placeTooltip(tooltip, plot, (Number(mark.dataset.anchorX) / 100) * plot.clientWidth, (Number(mark.dataset.anchorY) / 100) * plot.clientHeight);
        } else if (mark) {
            const rect = mark.getBoundingClientRect();
            placeTooltip(tooltip, plot, rect.left - plotRect.left + rect.width / 2, rect.top - plotRect.top);
        }
    };

    const hide = () => {
        current = null;
        highlight(chart, null);
        tooltip.hidden = true;
    };

    chart.querySelectorAll('[data-chart-mark], [data-chart-hit], [data-chart-legend-item]').forEach((target) => {
        target.addEventListener('pointerenter', () => show(Number(target.dataset.index)));
        target.addEventListener('pointerdown', () => show(Number(target.dataset.index)));
    });
    chart.addEventListener('pointerleave', hide);
    plot.addEventListener('focus', () => show(current ?? 0));
    plot.addEventListener('blur', hide);
    bindKeyboard(plot, count, () => current, show, hide);
}

const HANDLERS = {
    line: seriesChart,
    sparkline: seriesChart,
    bar: groupChart,
    histogram: groupChart,
    hbar: partsChart,
    donut: partsChart,
    stacked: partsChart,
};

export default function boot() {
    document.querySelectorAll('[data-chart]').forEach((chart) => {
        const payload = chart.querySelector('script[data-chart-data]');
        const handler = HANDLERS[chart.dataset.chart];

        if (!payload || !handler) {
            return;
        }

        try {
            handler(chart, JSON.parse(payload.textContent));
        } catch (error) {
            console.error('[nutrimind] chart payload could not be read', error);
        }
    });
}
