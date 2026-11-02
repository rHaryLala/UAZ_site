// Simulateur de frais académiques (resources/views/pages/fees.blade.php, données : config/fees.php via App\Support\FeeCatalog).
// Le visiteur choisit niveau, département, année, régime et période ; le relevé, la répartition et la comparaison interne / externe se mettent à jour.
// Aucun calcul de tarif ici : les lignes viennent du catalogue, le total est leur somme. Fonctionne sans animation ; les effets GSAP
// (resources/js/animations/fees.js) écoutent l'événement « uaz:fees-render ». Sans JavaScript, la section est masquée et les tableaux restent lisibles.
import { formatAr } from './format-ar.js';
import { t } from './i18n.js';

const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;

    return node;
};

/**
 * Regroupe les lignes de plusieurs semestres par intitulé (ordre d'apparition conservé) et additionne les montants.
 * Une ligne présente dans les deux semestres est un cumul : sa remarque de semestre (« par semestre », « 115 jours », « 30 crédits »)
 * ne serait plus vraie, elle est remplacée par « Cumul des deux semestres ».
 */
export function mergeLines(semesters) {
    const merged = new Map();
    semesters.forEach((semester) =>
        semester.lines.forEach((line) => {
            const known = merged.get(line.label);
            if (known) {
                known.amount += line.amount;
                known.note = t('Cumul des deux semestres');
            } else {
                merged.set(line.label, { ...line });
            }
        }),
    );

    return [...merged.values()];
}

export function sumLines(lines) {
    return lines.reduce((total, line) => total + line.amount, 0);
}

/** Semestres disponibles d'un parcours : { 1: {lines, total}, 2: {...} } → liste des numéros. */
export const availableSemesters = (byNumber) => Object.keys(byNumber ?? {}).map(Number).sort();

export function initFeesSim() {
    const root = document.querySelector('[data-fee-sim]');
    const dataNode = document.querySelector('[data-fee-catalog]');
    if (!root || !dataNode) return;

    const { catalog, groups, years, departments, tracks, test } = JSON.parse(dataNode.textContent);
    const controls = root.querySelector('[data-fee-controls]');
    const linesList = root.querySelector('[data-fee-lines]');
    const totalNode = root.querySelector('[data-fee-total]');
    const summaryNode = root.querySelector('[data-fee-summary]');
    const barNode = root.querySelector('[data-fee-bar]');
    const legendNode = root.querySelector('[data-fee-legend]');
    const compareBox = root.querySelector('[data-fee-compare]');
    const compareRows = root.querySelector('[data-fee-compare-rows]');
    const testNode = root.querySelector('[data-fee-test]');
    const liveNode = root.querySelector('[data-fee-live]');
    const viewHint = root.querySelector('[data-fee-view-hint]');
    const receipt = root.querySelector('[data-fee-receipt]');

    const value = (name) => controls.querySelector(`input[name="${name}"]:checked`)?.value;
    let previousTotal = null;

    /** Semestres du parcours choisi. */
    function semestersFor(level, choice) {
        if (level === 'master') return catalog.master[choice.track] ?? {};

        return catalog.licence[choice.dept]?.[choice.year]?.[choice.status] ?? {};
    }

    function summary(level, choice, view) {
        const period = view === 'all' ? t('Année complète') : t('Semestre :number', { number: view });
        if (level === 'master') return `${t('Master')} · ${tracks[choice.track]} · ${period}`;

        return `${t('Licence')} · ${departments[choice.dept]} · ${years[choice.year]} · ${choice.status === 'interne' ? t('Interne') : t('Externe')} · ${period}`;
    }

    /** Lignes et total de la période choisie, ou null si elle n'existe pas pour ce parcours. */
    function resolve(semesters, view) {
        const numbers = availableSemesters(semesters);
        if (!numbers.length) return null;
        if (view !== 'all') return semesters[view] ? { lines: semesters[view].lines, total: semesters[view].total } : null;
        const lines = mergeLines(numbers.map((n) => semesters[n]));

        return { lines, total: sumLines(lines) };
    }

    function render() {
        const level = value('fee-level');
        const choice = { dept: value('fee-dept'), year: value('fee-year'), status: value('fee-status'), track: value('fee-track') };
        const semesters = semestersFor(level, choice);
        const numbers = availableSemesters(semesters);

        // Panneaux propres à chaque niveau.
        controls.querySelectorAll('[data-fee-for]').forEach((panel) => (panel.hidden = panel.dataset.feeFor !== level));

        // Périodes proposées : celles qui existent pour ce parcours (le M2 n'a que le semestre 2).
        let view = value('fee-view');
        controls.querySelectorAll('input[name="fee-view"]').forEach((input) => {
            // « Année complète » n'a de sens que si les deux semestres existent (le M2 n'a que le second).
            const exists = input.value === 'all' ? numbers.length > 1 : numbers.includes(Number(input.value));
            input.disabled = !exists;
            input.closest('label').classList.toggle('is-disabled', !exists);
        });
        if (numbers.length < 2) {
            const only = numbers[0];
            const target = controls.querySelector(`input[name="fee-view"][value="${only}"]`);
            if (view === 'all' && target) {
                target.checked = true;
                view = String(only);
            }
        }
        if (view !== 'all' && !numbers.includes(Number(view))) {
            const fallback = controls.querySelector('input[name="fee-view"][value="all"]:not(:disabled)') ?? controls.querySelector('input[name="fee-view"]:not(:disabled)');
            if (fallback) {
                fallback.checked = true;
                view = fallback.value;
            }
        }
        viewHint.hidden = numbers.length > 1;
        viewHint.textContent = numbers.length > 1 ? '' : t('Seul le semestre :number figure sur le tableau officiel.', { number: numbers[0] });

        const current = resolve(semesters, view);
        if (!current) return;

        // Relevé
        linesList.replaceChildren(
            ...current.lines.map((line) => {
                const item = el('li', 'fee-line');
                item.dataset.feeLine = '';
                item.dataset.group = line.group;
                const label = el('span', 'fee-line__label');
                label.append(el('span', 'fee-line__name', line.label));
                if (line.note) label.append(el('small', 'fee-line__note', line.note));
                item.append(label, el('span', 'fee-line__dots'), el('span', 'fee-line__amount', formatAr(line.amount)));

                return item;
            }),
        );
        summaryNode.textContent = summary(level, choice, view);
        totalNode.dataset.value = String(current.total);
        totalNode.textContent = formatAr(current.total);

        // Répartition par groupe
        const byGroup = new Map();
        current.lines.forEach((line) => byGroup.set(line.group, (byGroup.get(line.group) ?? 0) + line.amount));
        const entries = [...byGroup.entries()].filter(([, amount]) => amount > 0);
        barNode.replaceChildren(
            ...entries.map(([group, amount]) => {
                const segment = el('span', 'fee-mix__seg');
                segment.dataset.feeSeg = '';
                segment.dataset.group = group;
                segment.style.flexGrow = String(amount);
                segment.title = `${groups[group]} : ${formatAr(amount)} Ar`;

                return segment;
            }),
        );
        barNode.setAttribute('aria-label', t('Répartition : :parts', { parts: entries.map(([group, amount]) => `${groups[group]} ${Math.round((amount / current.total) * 100)} %`).join(', ') }));
        legendNode.replaceChildren(
            ...entries.map(([group, amount]) => {
                const item = el('li', 'fee-mix__item');
                item.dataset.group = group;
                item.append(el('i'), el('span', null, groups[group]), el('strong', null, `${Math.round((amount / current.total) * 100)} %`));

                return item;
            }),
        );

        // Interne ou externe : comparaison à période égale (Licence seulement).
        if (level === 'licence') {
            const other = {};
            ['interne', 'externe'].forEach((status) => (other[status] = resolve(catalog.licence[choice.dept][choice.year][status], view)?.total ?? 0));
            const max = Math.max(other.interne, other.externe, 1);
            compareRows.replaceChildren(
                ...['interne', 'externe'].map((status) => {
                    const row = el('div', `fee-cmp${status === choice.status ? ' is-current' : ''}`);
                    const bar = el('span', 'fee-cmp__bar');
                    bar.dataset.feeCmp = '';
                    bar.style.width = `${(other[status] / max) * 100}%`;
                    row.append(el('span', 'fee-cmp__label', status === 'interne' ? t('Interne') : t('Externe')), bar, el('strong', 'fee-cmp__value', `${formatAr(other[status])} Ar`));

                    return row;
                }),
            );
            compareBox.hidden = false;
        } else {
            compareBox.hidden = true;
        }

        testNode.hidden = level !== 'licence' || !test;
        liveNode.textContent = `${summary(level, choice, view)} : total ${formatAr(current.total)} ariary.`;
        receipt.dataset.rendered = '';

        window.dispatchEvent(new CustomEvent('uaz:fees-render', { detail: { total: current.total, previous: previousTotal } }));
        previousTotal = current.total;
    }

    controls.addEventListener('change', render);
    controls.addEventListener('submit', (event) => event.preventDefault());
    root.hidden = false;
    render();
}

/** Boutons « Copier » des moyens de paiement. */
export function initFeeCopy() {
    document.querySelectorAll('[data-fee-copy]').forEach((button) => {
        const label = button.textContent;
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(button.dataset.feeCopy);
                button.textContent = t('Copié');
            } catch (error) {
                button.textContent = t('Sélectionnez le numéro');
            }
            window.setTimeout(() => (button.textContent = label), 1800);
        });
    });
}
