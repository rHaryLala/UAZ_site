// Éditeur de lettres d'information (coque) : panneau (Ajouter / Structure / Bloc / Lettre), historique, enregistrement automatique, essai et envoi.
// L'état de la lettre (objet, aperçu, blocs, thème) vit ici. Le plan de travail (iframe, newsletter-canvas.js) affiche le courrier dessiné par le serveur
// (App\Support\NewsletterRenderer, le même code que l'envoi) et signale ce qu'on y fait. Le serveur valide tout : le navigateur n'est jamais cru sur parole.
(() => {
    const boot = JSON.parse(document.getElementById('nl-boot').textContent);
    const { types, urls, palette } = boot;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const clone = (value) => JSON.parse(JSON.stringify(value));

    const locked = boot.locked;
    let state = clone(boot.state);
    const media = { ...(boot.media || {}) };
    let selected = -1;
    let insertAt = null; // position demandée par le « + » d'un bloc
    let tab = 'add';
    let history = [JSON.stringify(state)];
    let cursor = 0;
    let dirty = false;
    let saving = false;
    let renderSeq = 0;
    const timers = { render: 0, save: 0 };

    let frame = $('[data-nl-frame]');
    const frameWrap = $('[data-nl-frame-wrap]');
    const statusEl = $('[data-nl-status]');
    const toastEl = $('[data-nl-toast]');

    /* ───────── Utilitaires ───────── */
    const h = (tag, attrs = {}, ...children) => {
        const node = document.createElement(tag);
        Object.entries(attrs).forEach(([key, value]) => {
            if (value === false || value === null || value === undefined) return;
            if (key === 'class') node.className = value;
            else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
            else node.setAttribute(key, value === true ? '' : value);
        });
        children.flat().forEach((child) => child !== null && child !== undefined && child !== false && node.append(child.nodeType ? child : document.createTextNode(child)));

        return node;
    };
    const newId = () => Math.random().toString(36).slice(2, 12).padEnd(10, '0');
    const label = (type) => types[type]?.label ?? type;
    const status = (text) => {
        if (statusEl) statusEl.textContent = text;
    };
    const toast = (message, tone = 'info') => {
        toastEl.textContent = message;
        toastEl.dataset.tone = tone;
        toastEl.hidden = false;
        clearTimeout(toast.t);
        toast.t = setTimeout(() => (toastEl.hidden = true), 4500);
    };

    async function api(url, method, body) {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: body ? JSON.stringify(body) : undefined,
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            const first = json.errors ? Object.values(json.errors)[0]?.[0] : null;
            throw new Error(json.message || first || `Erreur ${response.status}`);
        }

        return json;
    }

    const payload = () => ({ subject: state.subject, preheader: state.preheader, blocks: state.blocks, theme: state.theme });

    /* ───────── Plan de travail ───────── */
    async function renderFrame() {
        const seq = ++renderSeq;
        const scroll = frame.contentWindow?.scrollY ?? 0;

        try {
            const result = await api(urls.render, 'POST', { ...payload(), selected });
            if (seq !== renderSeq) return; // une réponse plus récente est en route
            Object.assign(media, result.media || {});
            const next = frame.cloneNode(false);
            next.removeAttribute('srcdoc');
            next.removeAttribute('data-nl-frame'); // le repère passe au nouveau plan de travail seulement une fois en place
            next.style.cssText = 'position:absolute;inset:0;visibility:hidden;';
            next.addEventListener(
                'load',
                () => {
                    if (seq !== renderSeq) return next.remove();
                    next.contentWindow?.scrollTo(0, scroll);
                    next.style.cssText = '';
                    frame.remove();
                    next.setAttribute('data-nl-frame', '');
                    frame = next;
                },
                { once: true },
            );
            frameWrap.style.position = 'relative';
            frameWrap.append(next);
            next.srcdoc = result.html;
        } catch (error) {
            toast('Le plan de travail n\'a pas pu être mis à jour : ' + error.message, 'error');
        }
    }

    const scheduleRender = (delay = 120) => {
        clearTimeout(timers.render);
        timers.render = setTimeout(renderFrame, delay);
    };

    /* ───────── Enregistrement automatique et historique ───────── */
    function scheduleSave() {
        if (locked) return;
        dirty = true;
        status('Modifications non enregistrées…');
        clearTimeout(timers.save);
        timers.save = setTimeout(save, 900);
    }

    async function save() {
        if (saving) {
            scheduleSave();

            return;
        }
        saving = true;
        clearTimeout(timers.save);
        try {
            const result = await api(urls.save, 'PUT', payload());
            dirty = false;
            status('Enregistré à ' + result.saved_at);
        } catch (error) {
            status('Échec de l\'enregistrement');
            toast('Enregistrement impossible : ' + error.message, 'error');
        } finally {
            saving = false;
        }
    }

    function pushHistory() {
        const snapshot = JSON.stringify(state);
        if (snapshot === history[cursor]) return;
        history = history.slice(0, cursor + 1);
        history.push(snapshot);
        if (history.length > 80) history.shift();
        cursor = history.length - 1;
        updateHistoryButtons();
    }

    function updateHistoryButtons() {
        const undo = $('[data-nl-undo]');
        const redo = $('[data-nl-redo]');
        if (undo) undo.disabled = cursor === 0;
        if (redo) redo.disabled = cursor >= history.length - 1;
    }

    /** Une modification terminée : on garde un point de retour, on enregistre et on redessine. */
    function commit({ render = true, panels = true } = {}) {
        pushHistory();
        scheduleSave();
        if (render) scheduleRender();
        if (panels) renderPanels();
    }

    function travel(step) {
        const target = cursor + step;
        if (target < 0 || target >= history.length) return;
        cursor = target;
        state = JSON.parse(history[cursor]);
        selected = Math.min(selected, state.blocks.length - 1);
        updateHistoryButtons();
        scheduleSave();
        renderFrame();
        renderPanels();
        $('[data-nl-subject]').value = state.subject;
    }

    /* ───────── Opérations sur les blocs ───────── */
    const defaultsOf = (type) => clone(types[type].defaults);

    function addBlock(type, position) {
        if (state.blocks.length >= 60) return toast('Une lettre ne peut pas dépasser 60 blocs.', 'error');
        const at = position ?? (selected >= 0 ? selected + 1 : state.blocks.length);
        state.blocks.splice(at, 0, { id: newId(), type, data: defaultsOf(type) });
        selected = at;
        insertAt = null;
        tab = 'block';
        commit();
        // Un bloc d'image sans image : on propose tout de suite la médiathèque.
        if (['image', 'feature'].includes(type)) setTimeout(() => chooseMedia(at), 250);
    }

    function moveBlock(from, to) {
        if (from === to || to < 0 || to >= state.blocks.length) return;
        const [block] = state.blocks.splice(from, 1);
        state.blocks.splice(to, 0, block);
        selected = to;
        commit();
    }

    function duplicateBlock(index) {
        const copy = clone(state.blocks[index]);
        copy.id = newId();
        state.blocks.splice(index + 1, 0, copy);
        selected = index + 1;
        commit();
    }

    function deleteBlock(index) {
        const removed = state.blocks.splice(index, 1)[0];
        selected = Math.min(index, state.blocks.length - 1);
        commit();
        toast('Bloc « ' + label(removed.type) + ' » supprimé. Ctrl+Z pour l\'annuler.');
    }

    /* ───────── Messages du plan de travail ───────── */
    window.addEventListener('message', (event) => {
        if (event.source !== frame.contentWindow || !event.data?.nl) return;
        const { op, index, field, value, from, to } = event.data;

        if (op === 'select') {
            selected = index;
            if (tab === 'add' || tab === 'block') tab = index >= 0 ? 'block' : 'add';
            renderPanels();
        } else if (op === 'edit') {
            const block = state.blocks[index];
            if (block && field in block.data) {
                block.data[field] = value;
                scheduleSave();
                renderStructure();
            }
        } else if (op === 'commit') {
            pushHistory();
        } else if (op === 'up') moveBlock(index, index - 1);
        else if (op === 'down') moveBlock(index, index + 1);
        else if (op === 'move') moveBlock(from, to);
        else if (op === 'dup') duplicateBlock(index);
        else if (op === 'del') deleteBlock(index);
        else if (op === 'add') {
            insertAt = index + 1;
            tab = 'add';
            renderPanels();
            toast('Choisissez le bloc à ajouter dans le panneau de gauche.');
        } else if (op === 'media') chooseMedia(index);
        else if (op === 'undo') travel(-1);
        else if (op === 'redo') travel(1);
    });

    /* ───────── Panneau ───────── */
    function showTab(name) {
        tab = name;
        $$('[data-nl-tab]').forEach((button) => {
            const on = button.dataset.nlTab === name;
            button.classList.toggle('is-on', on);
            button.setAttribute('aria-selected', String(on));
        });
        $$('[data-nl-pane]').forEach((pane) => (pane.hidden = pane.dataset.nlPane !== name));
    }

    function renderPanels() {
        if (locked) return;
        renderAdd();
        renderStructure();
        renderBlock();
        renderLetter();
        showTab(tab);
    }

    function renderAdd() {
        const pane = $('[data-nl-pane="add"]');
        const where = insertAt !== null ? 'à la position ' + (insertAt + 1) : selected >= 0 ? 'sous le bloc sélectionné' : 'à la fin de la lettre';
        pane.replaceChildren(
            h('p', { class: 've-pane__intro' }, 'Cliquez sur un bloc pour l\'ajouter ' + where + '.'),
            h(
                'div',
                { class: 've-library' },
                Object.entries(types).map(([type, meta]) =>
                    h('button', { type: 'button', class: 've-card', onclick: () => addBlock(type, insertAt ?? undefined) }, h('strong', {}, meta.label), h('span', { class: 've-hint' }, meta.hint)),
                ),
            ),
        );
    }

    const plain = (html) => (html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    const preview = (block) => {
        const d = block.data;
        const text = { heading: d.text, text: plain(d.html), button: d.label, quote: d.text, feature: d.title, news: d.title }[block.type];

        return text ? text.slice(0, 38) : '';
    };

    function renderStructure() {
        const pane = $('[data-nl-pane="structure"]');
        if (!pane) return;
        let dragged = -1;
        const list = h(
            'ol',
            { class: 've-list' },
            state.blocks.map((block, index) =>
                h(
                    'li',
                    {
                        class: 've-item' + (index === selected ? ' is-on' : ''),
                        draggable: 'true',
                        ondragstart: (event) => {
                            dragged = index;
                            event.dataTransfer.effectAllowed = 'move';
                            event.currentTarget.classList.add('is-drag');
                        },
                        ondragend: (event) => event.currentTarget.classList.remove('is-drag'),
                        ondragover: (event) => {
                            event.preventDefault();
                            event.currentTarget.classList.add('is-over');
                        },
                        ondragleave: (event) => event.currentTarget.classList.remove('is-over'),
                        ondrop: (event) => {
                            event.preventDefault();
                            if (dragged >= 0) moveBlock(dragged, index);
                        },
                    },
                    h('span', { class: 've-item__grip', 'aria-hidden': 'true' }, '⠿'),
                    h(
                        'button',
                        { type: 'button', class: 've-item__main', onclick: () => ((selected = index), (tab = 'block'), renderPanels(), scheduleRender(0)) },
                        h('strong', {}, label(block.type)),
                        h('span', { class: 've-hint' }, preview(block)),
                    ),
                    h('button', { type: 'button', class: 've-mini', title: 'Monter', 'aria-label': 'Monter', disabled: index === 0, onclick: () => moveBlock(index, index - 1) }, '↑'),
                    h('button', { type: 'button', class: 've-mini', title: 'Descendre', 'aria-label': 'Descendre', disabled: index === state.blocks.length - 1, onclick: () => moveBlock(index, index + 1) }, '↓'),
                    h('button', { type: 'button', class: 've-mini', title: 'Supprimer', 'aria-label': 'Supprimer', onclick: () => deleteBlock(index) }, '🗑'),
                ),
            ),
        );
        pane.replaceChildren(h('p', { class: 've-pane__intro' }, state.blocks.length ? 'Glissez pour changer l\'ordre.' : 'La lettre est vide.'), list);
    }

    /* ───────── Champs de réglage ───────── */
    const typed = (type, key, raw) => (typeof types[type].defaults[key] === 'number' ? Number(raw) : raw);

    function setData(block, key, value, { final = true } = {}) {
        block.data[key] = value;
        if (final) commit({ panels: false });
        else {
            scheduleSave();
            scheduleRender(160);
        }
    }

    function segmented(options, current, onPick) {
        return h(
            'div',
            { class: 'nl-seg', role: 'group' },
            Object.entries(options).map(([value, text]) => h('button', { type: 'button', 'aria-pressed': String(String(current) === String(value)), onclick: () => onPick(value) }, text)),
        );
    }

    function colorControl(current, onPick, emptyLabel = null, colors = palette) {
        const wrap = h('div', { class: 'nl-swatches' });
        if (emptyLabel) wrap.append(h('button', { type: 'button', class: 'nl-swatch nl-swatch--none', title: emptyLabel, 'aria-label': emptyLabel, 'aria-pressed': String(current === ''), onclick: () => onPick('') }));
        colors.forEach((color) => wrap.append(h('button', { type: 'button', class: 'nl-swatch', style: 'background:' + color, title: color, 'aria-label': color, 'aria-pressed': String(current === color), onclick: () => onPick(color) })));
        wrap.append(h('input', { type: 'color', class: 'nl-color-input', value: current || '#232e66', 'aria-label': 'Autre couleur', title: 'Autre couleur', onchange: (event) => onPick(event.target.value) }));

        return wrap;
    }

    function rangeControl(spec, current, onLive, onFinal) {
        const out = h('output', {}, current + spec.unit);
        const input = h('input', {
            type: 'range', min: spec.min, max: spec.max, value: current, 'aria-label': spec.label,
            oninput: (event) => ((out.textContent = event.target.value + spec.unit), onLive(Number(event.target.value))),
            onchange: (event) => onFinal(Number(event.target.value)),
        });

        return h('div', { class: 'nl-range' }, input, out);
    }

    function field(spec, block) {
        const value = block.data[spec.key];
        let control;

        if (spec.kind === 'select') control = segmented(spec.options, value, (picked) => (setData(block, spec.key, typed(block.type, spec.key, picked)), renderBlock()));
        else if (spec.kind === 'color') control = colorControl(value, (picked) => (setData(block, spec.key, picked), renderBlock()), spec.empty ?? null);
        else if (spec.kind === 'range') control = rangeControl(spec, value, (v) => setData(block, spec.key, v, { final: false }), (v) => setData(block, spec.key, v));
        else if (spec.kind === 'toggle') control = h('label', { class: 'nl-toggle' }, h('input', { type: 'checkbox', checked: !!value, onchange: (event) => setData(block, spec.key, event.target.checked ? '1' : '') }), 'Oui');
        else if (spec.kind === 'url') control = h('input', { type: 'url', class: 'nl-input', value, placeholder: 'https://…', oninput: (event) => setData(block, spec.key, event.target.value, { final: false }), onchange: (event) => setData(block, spec.key, event.target.value) });
        else if (spec.kind === 'media') {
            const asset = media[value];
            control = h(
                'div',
                { class: 've-media-field' },
                asset ? h('img', { src: asset.thumb, alt: asset.alt }) : h('span', { class: 've-media-field__empty' }, 'Aucune image'),
                h('div', {}, h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => chooseMedia(state.blocks.indexOf(block)) }, asset ? 'Changer' : 'Choisir une image'), asset ? h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => (setData(block, spec.key, 0), renderBlock()) }, 'Retirer') : null),
            );
        }

        return h('div', { class: 've-field' }, h('span', { class: 've-label' }, spec.label), control);
    }

    function renderBlock() {
        const pane = $('[data-nl-pane="block"]');
        if (!pane) return;
        const block = state.blocks[selected];

        if (!block) {
            pane.replaceChildren(h('p', { class: 've-pane__intro' }, 'Cliquez sur un bloc du plan de travail pour régler son aspect.'));

            return;
        }

        const meta = types[block.type];
        const inline = Object.keys(meta.inline ?? {}).length > 0;
        pane.replaceChildren(
            ...[
                h('div', { class: 've-block-head' }, h('strong', {}, meta.label)),
                inline ? h('p', { class: 'nl-hint' }, 'Le texte se modifie directement sur le plan de travail : cliquez dessus.') : null,
                ...meta.fields.map((spec) => field(spec, block)),
                h('div', { class: 've-actions' },
                    h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => duplicateBlock(selected) }, 'Dupliquer'),
                    h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => deleteBlock(selected) }, 'Supprimer')),
            ].filter(Boolean),
        );
    }

    function renderLetter() {
        const pane = $('[data-nl-pane="letter"]');
        if (!pane) return;
        const theme = state.theme;
        const setTheme = (key, value) => ((theme[key] = value), commit({ panels: false }), renderLetter());
        const colorRow = (key, title, colors) => h('div', { class: 've-field' }, h('span', { class: 've-label' }, title), colorControl(theme[key], (picked) => setTheme(key, picked), null, colors));

        pane.replaceChildren(
            ...[
            h('div', { class: 've-field' },
                h('label', { class: 've-label', for: 'nl-preheader' }, 'Texte d\'aperçu'),
                h('input', { id: 'nl-preheader', class: 'nl-input', maxlength: 190, value: state.preheader, placeholder: 'Une ligne affichée à côté de l\'objet', oninput: (event) => ((state.preheader = event.target.value), scheduleSave()), onchange: () => commit({ panels: false }) }),
                h('p', { class: 've-hint' }, 'Visible dans la liste des messages, avant l\'ouverture.')),
            h('div', { class: 'nl-section' }, 'Couleurs'),
            colorRow('background', 'Fond autour de la lettre', ['#eef0f6', '#fbf9f4', '#e1ebff', '#131a3f', '#ffffff']),
            colorRow('card', 'Fond de la lettre', ['#ffffff', '#fbf9f4', '#f4f6fb', '#e1ebff']),
            colorRow('text', 'Texte', ['#1d2233', '#23272d', '#131a3f', '#4b5160']),
            colorRow('accent', 'Titres et liens', ['#232e66', '#131a3f', '#8a724b', '#b4452f', '#1f8a5b']),
            colorRow('gold', 'Filets et détails', ['#8a724b', '#e0b957', '#232e66', '#9aa1b5']),
            h('div', { class: 'nl-section' }, 'Typographie'),
            h('div', { class: 've-field' }, h('span', { class: 've-label' }, 'Police des titres'), segmented({ serif: 'Avec empattements', sans: 'Sans empattements' }, theme.font, (value) => setTheme('font', value))),
            h('div', { class: 'nl-section' }, 'Bandeau du haut'),
            h('div', { class: 've-field' }, h('label', { class: 'nl-toggle' }, h('input', { type: 'checkbox', checked: !!theme.header, onchange: (event) => setTheme('header', event.target.checked ? '1' : '') }), 'Afficher le logo de l\'UAZ')),
            theme.header ? colorRow('header_color', 'Couleur du bandeau', ['#131a3f', '#232e66', '#8a724b', '#1d2233', '#ffffff']) : null,
            ].filter(Boolean),
        );
    }

    /* ───────── Médiathèque ───────── */
    const mediaDialog = $('[data-nl-media]');
    const grid = $('[data-nl-media-grid]');
    const more = $('[data-nl-media-more]');
    const uploadForm = $('[data-nl-media-upload]');
    let mediaTarget = -1;
    let mediaPage = 1;
    let mediaQuery = '';
    let pendingFile = null;

    async function loadMedia(reset = true) {
        if (reset) {
            mediaPage = 1;
            grid.replaceChildren();
        }
        const url = new URL(urls.media, location.origin);
        url.search = new URLSearchParams({ q: mediaQuery, type: 'image', page: mediaPage }).toString();
        const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const json = await response.json();
        json.data.forEach((asset) => {
            media[asset.id] = asset;
            grid.append(h('button', { type: 'button', class: 've-thumb', onclick: () => pickMedia(asset), title: asset.title }, h('img', { src: asset.thumb, alt: asset.alt, loading: 'lazy' }), h('span', {}, asset.title)));
        });
        more.hidden = !json.has_more;
    }

    function chooseMedia(index) {
        if (locked || index < 0) return;
        mediaTarget = index;
        pendingFile = null;
        uploadForm.hidden = true;
        mediaDialog.showModal();
        loadMedia();
    }

    function pickMedia(asset) {
        const block = state.blocks[mediaTarget];
        if (block) {
            media[asset.id] = asset;
            block.data.media = asset.id;
            selected = mediaTarget;
            mediaDialog.close();
            commit();
        }
    }

    $('[data-nl-media-search]')?.addEventListener('input', (event) => {
        clearTimeout(timers.search);
        timers.search = setTimeout(() => ((mediaQuery = event.target.value.trim()), loadMedia()), 250);
    });
    more?.addEventListener('click', () => (mediaPage++, loadMedia(false)));
    $('[data-nl-media-file]')?.addEventListener('change', (event) => {
        pendingFile = event.target.files[0] ?? null;
        event.target.value = '';
        uploadForm.hidden = !pendingFile;
        if (pendingFile) $('[data-nl-media-upload-name]').textContent = pendingFile.name;
    });
    uploadForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const body = new FormData();
        body.append('files[]', pendingFile);
        body.append('alts[0]', $('[data-nl-media-alt]').value.trim());
        try {
            const response = await fetch(urls.upload, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            const json = await response.json();
            if (!response.ok) throw new Error(json.message || 'Envoi impossible.');
            $('[data-nl-media-alt]').value = '';
            uploadForm.hidden = true;
            pickMedia(json.data[0]);
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    /* ───────── Barre du haut : écran, historique, essai, envoi ───────── */
    $$('[data-nl-device]').forEach((button) =>
        button.addEventListener('click', () => {
            $('[data-nl]').dataset.device = button.dataset.nlDevice;
            $$('[data-nl-device]').forEach((other) => other.classList.toggle('is-on', other === button));
        }),
    );
    $('[data-nl-undo]')?.addEventListener('click', () => travel(-1));
    $('[data-nl-redo]')?.addEventListener('click', () => travel(1));
    $$('[data-nl-tab]').forEach((button) => button.addEventListener('click', () => ((tab = button.dataset.nlTab), showTab(tab))));
    $('[data-nl-subject]')?.addEventListener('input', (event) => ((state.subject = event.target.value), scheduleSave()));
    $('[data-nl-subject]')?.addEventListener('change', () => pushHistory());
    $$('[data-nl-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog').close()));

    document.addEventListener('keydown', (event) => {
        if (event.target.closest('input, textarea, select, dialog')) return;
        const mod = event.ctrlKey || event.metaKey;
        if (mod && event.key.toLowerCase() === 'z') (event.preventDefault(), travel(event.shiftKey ? 1 : -1));
        else if (mod && event.key.toLowerCase() === 'y') (event.preventDefault(), travel(1));
    });

    // Essai
    const testDialog = $('[data-nl-dialog="test"]');
    $('[data-nl-open-test]')?.addEventListener('click', () => {
        $('[data-nl-test-error]').hidden = true;
        testDialog.showModal();
    });
    $('[data-nl-test-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = $('[data-nl-test-submit]');
        const error = $('[data-nl-test-error]');
        submit.disabled = true;
        error.hidden = true;
        try {
            const result = await api(urls.test, 'POST', { ...payload(), to: $('[data-nl-test-to]').value });
            testDialog.close();
            toast(result.message);
        } catch (e) {
            error.textContent = e.message;
            error.hidden = false;
        } finally {
            submit.disabled = false;
        }
    });

    // Envoi à tous les abonnés
    const sendDialog = $('[data-nl-dialog="send"]');
    $('[data-nl-open-send]')?.addEventListener('click', () => {
        const error = $('[data-nl-send-error]');
        const submit = $('[data-nl-send-submit]');
        error.hidden = true;
        $('[data-nl-send-confirm]').checked = false;
        if (!state.subject.trim()) {
            toast('Donnez d\'abord un objet à la lettre (en haut de l\'écran).', 'error');
            $('[data-nl-subject]').focus();

            return;
        }
        submit.disabled = boot.audience === 0;
        $('[data-nl-send-text]').textContent = boot.audience === 0
            ? 'Aucun abonné actif pour le moment : la lettre ne partirait à personne.'
            : 'La lettre « ' + state.subject + ' » va être envoyée à ' + boot.audience + ' abonné' + (boot.audience > 1 ? 's' : '') + ' actif' + (boot.audience > 1 ? 's' : '') + ' (adresse confirmée, non désinscrit).';
        sendDialog.showModal();
    });
    $('[data-nl-send-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = $('[data-nl-send-submit]');
        const error = $('[data-nl-send-error]');
        submit.disabled = true;
        error.hidden = true;
        try {
            const result = await api(urls.send, 'POST', payload());
            dirty = false;
            sendDialog.close();
            toast(result.message + ' Retour à la liste…');
            setTimeout(() => (location.href = result.list), 1200);
        } catch (e) {
            error.textContent = e.message;
            error.hidden = false;
            submit.disabled = false;
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirty) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    /* ───────── Démarrage ───────── */
    updateHistoryButtons();
    if (!locked) {
        status('Prêt');
        renderPanels();
        $('[data-nl-subject]')?.focus({ preventScroll: true });
    }
    renderFrame();
})();
