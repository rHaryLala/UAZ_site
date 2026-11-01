// Éditeur visuel des pages (coque) : barre du haut, panneau (Ajouter / Structure / Réglages), historique, brouillon automatique, publication.
// L'état de la page (titre, résumé, blocs) vit ici ; la page affichée dans l'iframe (visual-canvas.js) le dessine et signale les modifications de texte.
// Le serveur (App\Http\Controllers\Admin\VisualEditorController) dessine la page, valide et enregistre : le navigateur n'est jamais cru sur parole.
(() => {
    const boot = JSON.parse(document.getElementById('ve-boot').textContent);
    const types = boot.types;
    const urls = boot.urls;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

    const state = JSON.parse(JSON.stringify(boot.state));
    const media = { ...(boot.media || {}) };
    let selected = -1;
    let pendingInsert = null; // position d'insertion demandée par un « + » de la page
    let hasDraft = boot.hasDraft;
    let dirty = false;
    let saving = false;
    let renderSeq = 0;
    let timers = { render: 0, save: 0, settings: 0 };

    const frame = $('[data-ve-frame]');
    const statusEl = $('[data-ve-status]');
    const toastEl = $('[data-ve-toast]');

    /* ───────── Utilitaires ───────── */
    const h = (tag, attrs = {}, ...children) => {
        const node = document.createElement(tag);
        Object.entries(attrs).forEach(([k, v]) => {
            if (v === false || v === null || v === undefined) return;
            if (k === 'class') node.className = v;
            else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
            else node.setAttribute(k, v === true ? '' : v);
        });
        children.flat().forEach((c) => c !== null && c !== undefined && c !== false && node.append(c.nodeType ? c : document.createTextNode(c)));

        return node;
    };
    const svg = (paths, size = 18) => {
        const wrap = document.createElement('span');
        wrap.className = 've-svg';
        wrap.innerHTML = `<svg viewBox="0 0 24 24" width="${size}" height="${size}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;

        return wrap;
    };
    const clone = (v) => JSON.parse(JSON.stringify(v));
    const get = (obj, path) => path.reduce((o, k) => (o == null ? o : o[k]), obj);
    const set = (obj, path, value) => {
        const last = path[path.length - 1];
        get(obj, path.slice(0, -1))[last] = value;
    };

    function toast(message, action = null, tone = 'info') {
        toastEl.replaceChildren(h('span', {}, message));
        if (action) toastEl.append(h('button', { type: 'button', class: 've-toast__btn', onclick: () => { action.run(); toastEl.hidden = true; } }, action.label));
        toastEl.dataset.tone = tone;
        toastEl.hidden = false;
        clearTimeout(toast.t);
        toast.t = setTimeout(() => (toastEl.hidden = true), action ? 7000 : 4500);
    }

    async function api(method, url, body) {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: body ? JSON.stringify(body) : undefined,
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            const first = json.errors ? Object.values(json.errors).flat()[0] : null;
            const error = new Error(first || json.message || `Erreur ${response.status}`);
            error.status = response.status;
            throw error;
        }

        return json;
    }

    const payload = () => ({ title: state.title, summary: state.summary, blocks: state.blocks });

    /* ───────── Historique ───────── */
    const history = { stack: [JSON.stringify(state)], index: 0, key: null, at: 0 };
    window.__ve = { state, get selected() { return selected; }, history }; // lecture seule pour les tests de bout en bout

    function remember(coalesceKey = null) {
        const snap = JSON.stringify(state);
        if (snap === history.stack[history.index]) return;
        if (coalesceKey && history.key === coalesceKey && Date.now() - history.at < 900) {
            history.stack[history.index] = snap;
        } else {
            history.stack.splice(history.index + 1);
            history.stack.push(snap);
            if (history.stack.length > 120) history.stack.shift();
            history.index = history.stack.length - 1;
        }
        history.key = coalesceKey;
        history.at = Date.now();
        syncHistoryButtons();
    }

    function travel(step) {
        const target = history.index + step;
        if (target < 0 || target >= history.stack.length) return;
        history.index = target;
        history.key = null;
        const snap = JSON.parse(history.stack[target]);
        state.title = snap.title;
        state.summary = snap.summary;
        state.blocks = snap.blocks;
        if (selected >= state.blocks.length) selected = state.blocks.length - 1;
        syncHistoryButtons();
        afterChange({ structural: true, remember: false });
    }

    function syncHistoryButtons() {
        $('[data-ve-undo]').disabled = history.index === 0;
        $('[data-ve-redo]').disabled = history.index >= history.stack.length - 1;
    }

    /* ───────── Rendu de la page ───────── */
    async function renderCanvas() {
        const seq = ++renderSeq;
        try {
            const json = await api('POST', urls.render, payload());
            if (seq !== renderSeq) return; // une demande plus récente est partie
            Object.assign(media, json.media || {});
            frame.contentWindow.VE.mount(json.html, selected);
        } catch (error) {
            toast(`La page n'a pas pu être dessinée : ${error.message}`, null, 'error');
        }
    }

    /** Point d'entrée de toute modification : historique, nouveau dessin, panneaux, enregistrement du brouillon. */
    function afterChange({ structural = false, remember: rememberIt = true, key = null, render = true } = {}) {
        if (rememberIt) remember(key);
        markDirty();
        if (render) {
            clearTimeout(timers.render);
            timers.render = setTimeout(renderCanvas, structural ? 0 : 350);
        }
        renderStructure();
        renderTitle();
        if (structural) renderSettings(true);
    }

    function markDirty() {
        dirty = true;
        setStatus('Modifications non enregistrées…');
        clearTimeout(timers.save);
        timers.save = setTimeout(saveDraft, 1400);
    }

    async function saveDraft() {
        if (saving) {
            timers.save = setTimeout(saveDraft, 600);

            return true;
        }
        saving = true;
        setStatus('Enregistrement…');
        try {
            const json = await api('PUT', urls.draft, payload());
            dirty = false;
            hasDraft = true;
            $('[data-ve-discard]').hidden = false;
            setStatus(`Brouillon enregistré à ${new Date(json.saved_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`);

            return true;
        } catch (error) {
            setStatus('Enregistrement impossible', 'error');
            toast(`Le brouillon n'a pas pu être enregistré : ${error.message}`, null, 'error');

            return false;
        } finally {
            saving = false;
        }
    }

    function setStatus(text, tone = 'info') {
        statusEl.textContent = text;
        statusEl.dataset.tone = tone;
    }

    function renderTitle() {
        $('[data-ve-title]').textContent = state.title || 'Sans titre';
    }

    /* ───────── Opérations sur les blocs ───────── */
    const fresh = (type) => ({ type, data: clone(types[type].default) });

    function insertBlock(type, position) {
        const at = position === null || position === undefined ? (selected >= 0 ? selected + 1 : state.blocks.length) : position;
        state.blocks.splice(at, 0, fresh(type));
        selected = at;
        pendingInsert = null;
        afterChange({ structural: true });
        setTab('settings');
        toast(`Bloc « ${types[type].label} » ajouté.`);
    }

    function move(index, delta) {
        const to = index + delta;
        if (to < 0 || to >= state.blocks.length) return;
        [state.blocks[index], state.blocks[to]] = [state.blocks[to], state.blocks[index]];
        selected = to;
        afterChange({ structural: true });
    }

    function duplicate(index) {
        state.blocks.splice(index + 1, 0, clone(state.blocks[index]));
        selected = index + 1;
        afterChange({ structural: true });
    }

    function removeBlock(index) {
        const [removed] = state.blocks.splice(index, 1);
        selected = Math.min(index, state.blocks.length - 1);
        afterChange({ structural: true });
        toast('Bloc supprimé.', { label: 'Annuler', run: () => travel(-1) });

        return removed;
    }

    function select(index) {
        selected = index;
        frame.contentWindow?.VE?.select(index, false);
        renderStructure();
        renderSettings(true);
        if (index >= 0) setTab('settings');
    }

    /* ───────── Pont avec la page affichée ───────── */
    function installHost() {
        const VE = frame.contentWindow.VE;
        VE.host = {
            types,
            getPage: () => ({ title: state.title, summary: state.summary }),
            getBlock: (i) => state.blocks[i],
            select: (i) => select(i),
            pageText(field, value) {
                state[field] = value;
                afterChange({ key: `page-${field}`, render: false });
                clearTimeout(timers.settings);
                timers.settings = setTimeout(renderSettings, 400);
            },
            text(i, path, value) {
                set(state.blocks[i].data, path, value);
                afterChange({ key: `text-${i}-${path.join('.')}`, render: false });
                clearTimeout(timers.settings);
                timers.settings = setTimeout(() => !$('[data-ve-pane="settings"]').contains(document.activeElement) && renderSettings(), 500);
            },
            rich(i, path, html) {
                set(state.blocks[i].data, path, html);
                afterChange({ key: `rich-${i}`, render: false });
            },
            action(name, i) {
                if (name === 'up') move(i, -1);
                else if (name === 'down') move(i, 1);
                else if (name === 'duplicate') duplicate(i);
                else if (name === 'delete') removeBlock(i);
                else if (name === 'addAfter') openLibrary(i + 1);
                else if (name === 'addBefore') openLibrary(i);
            },
            key: (event) => shortcut(event),
        };
    }

    function shortcut(event) {
        const mod = event.ctrlKey || event.metaKey;
        if (!mod) return false;
        const key = event.key.toLowerCase();
        if (key === 'z' && !event.shiftKey) travel(-1);
        else if (key === 'y' || (key === 'z' && event.shiftKey)) travel(1);
        else if (key === 's') saveDraft();
        else return false;

        return true;
    }

    /* ───────── Panneaux ───────── */
    function setTab(name) {
        $$('[data-ve-tab]').forEach((t) => {
            t.classList.toggle('is-on', t.dataset.veTab === name);
            t.setAttribute('aria-selected', String(t.dataset.veTab === name));
        });
        $$('[data-ve-pane]').forEach((p) => (p.hidden = p.dataset.vePane !== name));
    }

    function openLibrary(position) {
        pendingInsert = position;
        renderLibrary();
        setTab('add');
    }

    function renderLibrary() {
        const pane = $('[data-ve-pane="add"]');
        const where = pendingInsert !== null ? pendingInsert : selected >= 0 ? selected + 1 : state.blocks.length;
        pane.replaceChildren(
            h('p', { class: 've-pane__intro' }, where >= state.blocks.length ? 'Le bloc sera ajouté à la fin de la page.' : `Le bloc sera inséré en position ${where + 1}.`),
            h(
                'div',
                { class: 've-library' },
                Object.entries(types).map(([type, def]) =>
                    h(
                        'button',
                        { type: 'button', class: 've-card', onclick: () => insertBlock(type, where) },
                        svg(def.icon, 26),
                        h('strong', {}, def.label),
                        h('span', {}, def.hint),
                    ),
                ),
            ),
        );
    }

    const excerpt = (block) => {
        const def = types[block.type];
        const find = (fields, data) => {
            for (const f of fields) {
                const v = data?.[f.name];
                if ((f.type === 'text' || f.type === 'textarea') && typeof v === 'string' && v.trim()) return v.trim();
                if (f.type === 'rich' && typeof v === 'string' && v.trim()) return v.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                if (f.type === 'repeater' && Array.isArray(v) && v[0]) {
                    const inner = find(f.fields, v[0]);
                    if (inner) return inner;
                }
            }

            return '';
        };

        return find(def.fields, block.data).slice(0, 48);
    };

    function renderStructure() {
        const pane = $('[data-ve-pane="structure"]');
        if (!state.blocks.length) {
            pane.replaceChildren(h('p', { class: 've-pane__intro' }, 'Aucun bloc pour le moment. Ajoutez-en un depuis l\'onglet « Ajouter ».'));

            return;
        }
        let dragFrom = null;
        pane.replaceChildren(
            h('p', { class: 've-pane__intro' }, 'Glissez les blocs pour changer leur ordre.'),
            h(
                'ol',
                { class: 've-list' },
                state.blocks.map((block, i) =>
                    h(
                        'li',
                        {
                            class: `ve-item${i === selected ? ' is-on' : ''}`,
                            draggable: 'true',
                            ondragstart: (e) => { dragFrom = i; e.dataTransfer.effectAllowed = 'move'; e.currentTarget.classList.add('is-drag'); },
                            ondragend: (e) => e.currentTarget.classList.remove('is-drag'),
                            ondragover: (e) => { e.preventDefault(); e.currentTarget.classList.add('is-over'); },
                            ondragleave: (e) => e.currentTarget.classList.remove('is-over'),
                            ondrop: (e) => {
                                e.preventDefault();
                                e.currentTarget.classList.remove('is-over');
                                if (dragFrom === null || dragFrom === i) return;
                                const [moved] = state.blocks.splice(dragFrom, 1);
                                state.blocks.splice(i, 0, moved);
                                selected = i;
                                dragFrom = null;
                                afterChange({ structural: true });
                            },
                        },
                        h('span', { class: 've-item__grip', 'aria-hidden': 'true' }, '⋮⋮'),
                        h('button', { type: 'button', class: 've-item__main', onclick: () => select(i) }, svg(types[block.type].icon, 18), h('span', {}, h('strong', {}, types[block.type].label), h('small', {}, excerpt(block) || '—'))),
                        h('span', { class: 've-item__tools' },
                            h('button', { type: 'button', class: 've-mini', title: 'Monter', 'aria-label': `Monter le bloc ${i + 1}`, disabled: i === 0, onclick: () => move(i, -1) }, '↑'),
                            h('button', { type: 'button', class: 've-mini', title: 'Descendre', 'aria-label': `Descendre le bloc ${i + 1}`, disabled: i === state.blocks.length - 1, onclick: () => move(i, 1) }, '↓'),
                        ),
                    ),
                ),
            ),
        );
    }

    /* ───────── Réglages ───────── */
    function renderSettings(force = false) {
        const pane = $('[data-ve-pane="settings"]');
        const focused = document.activeElement;
        if (!force && pane.contains(focused) && focused.matches('input, textarea')) return; // on ne reconstruit pas pendant la saisie

        if (selected < 0 || !state.blocks[selected]) {
            pane.replaceChildren(
                h('p', { class: 've-pane__intro' }, 'En-tête de la page'),
                field({ name: 'title', type: 'text', label: 'Titre de la page', max: 200 }, () => state.title, (v) => (state.title = v), 'page'),
                field({ name: 'summary', type: 'textarea', label: 'Résumé (sous le titre)', max: 500 }, () => state.summary, (v) => (state.summary = v), 'page'),
                h('p', { class: 've-hint' }, 'Le titre et le résumé se modifient aussi directement sur la page. Sélectionnez un bloc pour régler son contenu.'),
            );

            return;
        }
        const block = state.blocks[selected];
        const def = types[block.type];
        pane.replaceChildren(
            h('div', { class: 've-block-head' }, svg(def.icon, 22), h('div', {}, h('strong', {}, def.label), h('small', {}, `Bloc ${selected + 1} sur ${state.blocks.length}`))),
            h('div', { class: 've-actions' },
                h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => move(selected, -1), disabled: selected === 0 }, '↑ Monter'),
                h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => move(selected, 1), disabled: selected === state.blocks.length - 1 }, '↓ Descendre'),
                h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => duplicate(selected) }, 'Dupliquer'),
                h('button', { type: 'button', class: 've-btn ve-btn--sm ve-btn--danger', onclick: () => removeBlock(selected) }, 'Supprimer'),
            ),
            ...def.fields.map((f) => fieldFor(f, block.data, [f.name], selected)),
        );
    }

    /** Champ du formulaire pour une donnée du bloc (chemin = liste de clés dans block.data). */
    function fieldFor(f, data, path, blockIndex) {
        const read = () => get(data, path);
        const write = (v, structural = false) => {
            set(data, path, v);
            afterChange({ structural, key: `field-${blockIndex}-${path.join('.')}` });
        };
        if (f.type === 'repeater') return repeater(f, data, path, blockIndex);

        return field(f, read, write, `b${blockIndex}`);
    }

    function field(f, read, write, scope) {
        const id = `ve-f-${scope}-${f.name}-${Math.random().toString(36).slice(2, 7)}`;
        const label = h('label', { class: 've-label', for: id }, f.label, f.required ? h('b', { title: 'Obligatoire' }, ' *') : null);
        let control;

        if (f.type === 'text' || f.type === 'url') {
            control = h('input', { id, type: 'text', maxlength: f.max, value: read() ?? '', placeholder: f.type === 'url' ? 'https://… ou /chemin' : null, oninput: (e) => write(e.target.value) });
        } else if (f.type === 'textarea') {
            control = h('textarea', { id, rows: 3, maxlength: f.max, oninput: (e) => write(e.target.value) }, read() ?? '');
        } else if (f.type === 'number') {
            control = h('input', { id, type: 'number', min: 0, value: read() ?? '', oninput: (e) => write(e.target.value === '' ? null : Number(e.target.value)) });
        } else if (f.type === 'select') {
            control = h('select', { id, onchange: (e) => write(e.target.value, false) }, Object.entries(f.options).map(([v, l]) => h('option', { value: v, selected: read() === v }, l)));
        } else if (f.type === 'rich') {
            control = h('p', { class: 've-hint', id }, 'Texte mis en forme : cliquez dessus sur la page pour l\'écrire (gras, titres, listes, liens…).');
        } else if (f.type === 'media') {
            control = mediaControl(f, read, (v) => write(v, true), id);
        }

        return h('div', { class: 've-field' }, label, control, f.help ? h('small', { class: 've-hint' }, f.help) : null);
    }

    function repeater(f, data, path, blockIndex) {
        const items = () => get(data, path) || [];
        const blank = () => Object.fromEntries(f.fields.map((sub) => [sub.name, sub.type === 'number' ? 0 : sub.type === 'media' ? null : '']));
        const changed = () => afterChange({ structural: true });

        return h(
            'div',
            { class: 've-repeater' },
            h('p', { class: 've-label' }, f.label, h('span', { class: 've-count' }, ` (${items().length})`)),
            items().map((item, i) =>
                h(
                    'details',
                    { class: 've-rep-item', open: items().length <= 3 },
                    h('summary', {}, h('span', {}, (typeof item[f.itemLabel] === 'string' && item[f.itemLabel]) || `${f.item} ${i + 1}`)),
                    h('div', { class: 've-rep-tools' },
                        h('button', { type: 'button', class: 've-mini', disabled: i === 0, 'aria-label': 'Monter', onclick: () => { const a = items(); [a[i - 1], a[i]] = [a[i], a[i - 1]]; changed(); } }, '↑'),
                        h('button', { type: 'button', class: 've-mini', disabled: i === items().length - 1, 'aria-label': 'Descendre', onclick: () => { const a = items(); [a[i + 1], a[i]] = [a[i], a[i + 1]]; changed(); } }, '↓'),
                        h('button', { type: 'button', class: 've-mini ve-mini--danger', disabled: items().length <= f.min, 'aria-label': 'Supprimer', onclick: () => { items().splice(i, 1); changed(); } }, '✕'),
                    ),
                    f.fields.map((sub) => fieldFor(sub, data, [...path, i, sub.name], blockIndex)),
                ),
            ),
            h('button', { type: 'button', class: 've-btn ve-btn--sm', disabled: items().length >= f.max, onclick: () => { if (!Array.isArray(get(data, path))) set(data, path, []); get(data, path).push(blank()); changed(); } }, `+ Ajouter ${f.item.toLowerCase() === 'chiffre' ? 'un' : 'une'} ${f.item.toLowerCase()}`),
        );
    }

    /* ───────── Médiathèque ───────── */
    const dialog = $('[data-ve-media]');
    const mediaState = { onPick: null, any: false, q: '', page: 1, more: false, timer: 0 };

    function mediaControl(f, read, write, id) {
        const current = read();
        const info = current ? media[current] : null;

        return h(
            'div',
            { class: 've-media-field', id },
            info?.thumb ? h('img', { src: info.thumb, alt: '' }) : h('span', { class: 've-media-field__empty' }, current ? `Fichier n° ${current}` : 'Aucun fichier'),
            h('div', {},
                h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => openMedia(f.any ?? false, (item) => { media[item.id] = item; write(item.id); }) }, current ? 'Changer' : 'Choisir'),
                current ? h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => write(null) }, 'Retirer') : null,
            ),
        );
    }

    function openMedia(any, onPick) {
        Object.assign(mediaState, { any, onPick, q: '', page: 1 });
        $('[data-ve-media-search]').value = '';
        $('[data-ve-media-upload]').hidden = true;
        dialog.showModal();
        loadMedia(true);
    }

    async function loadMedia(reset) {
        if (reset) {
            mediaState.page = 1;
            $('[data-ve-media-grid]').replaceChildren(h('p', { class: 've-hint' }, 'Chargement…'));
        }
        try {
            const params = new URLSearchParams({ q: mediaState.q, type: mediaState.any ? 'all' : 'image', page: mediaState.page });
            const response = await fetch(`${urls.media}?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const json = await response.json();
            const grid = $('[data-ve-media-grid]');
            if (reset) grid.replaceChildren();
            if (reset && !json.data.length) grid.append(h('p', { class: 've-hint' }, 'Aucun fichier trouvé.'));
            json.data.forEach((item) =>
                grid.append(h('button', { type: 'button', class: 've-thumb', title: item.title, onclick: () => { dialog.close(); mediaState.onPick?.(item); } }, h('img', { src: item.thumb, alt: item.alt || item.title, loading: 'lazy' }), h('span', {}, item.title))),
            );
            mediaState.more = json.has_more;
            mediaState.page += 1;
            $('[data-ve-media-more]').hidden = !json.has_more;
        } catch (error) {
            toast('La médiathèque est momentanément indisponible.', null, 'error');
        }
    }

    $('[data-ve-media-search]').addEventListener('input', (e) => {
        clearTimeout(mediaState.timer);
        mediaState.timer = setTimeout(() => { mediaState.q = e.target.value; loadMedia(true); }, 280);
    });
    $('[data-ve-media-more]').addEventListener('click', () => loadMedia(false));
    let pendingFile = null;
    $('[data-ve-media-file]').addEventListener('change', (e) => {
        pendingFile = e.target.files[0] || null;
        $('[data-ve-media-upload]').hidden = !pendingFile;
        $('[data-ve-media-upload-name]').textContent = pendingFile ? `Fichier : ${pendingFile.name}` : '';
    });
    $('[data-ve-media-upload]').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!pendingFile) return;
        const body = new FormData();
        body.append('files[]', pendingFile);
        body.append('alts[0]', $('[data-ve-media-alt]').value.trim());
        try {
            const response = await fetch(urls.upload, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            const json = await response.json();
            if (!response.ok) throw new Error(json.message || 'Envoi impossible.');
            dialog.close();
            mediaState.onPick?.(json.data[0]);
            $('[data-ve-media-alt]').value = '';
            $('[data-ve-media-file]').value = '';
            pendingFile = null;
        } catch (error) {
            toast(error.message, null, 'error');
        }
    });

    /* ───────── Barre du haut ───────── */
    $$('[data-ve-tab]').forEach((tab) => tab.addEventListener('click', () => {
        if (tab.dataset.veTab === 'add') { pendingInsert = null; renderLibrary(); }
        setTab(tab.dataset.veTab);
    }));

    $$('[data-ve-device]').forEach((b) => b.addEventListener('click', () => {
        $('[data-ve]').dataset.device = b.dataset.veDevice;
        $$('[data-ve-device]').forEach((o) => o.classList.toggle('is-on', o === b));
        setTimeout(() => frame.contentWindow?.VE?.place(), 350);
    }));

    $('[data-ve-undo]').addEventListener('click', () => travel(-1));
    $('[data-ve-redo]').addEventListener('click', () => travel(1));
    document.addEventListener('keydown', (e) => shortcut(e) && e.preventDefault());

    $('[data-ve-preview]').addEventListener('click', async () => {
        if (await saveDraft()) window.open(urls.preview, '_blank', 'noopener');
    });

    $('[data-ve-discard]').addEventListener('click', async () => {
        if (!window.confirm('Abandonner le brouillon et revenir à la page publiée ? Les modifications non publiées seront perdues.')) return;
        try {
            await api('DELETE', urls.draft);
            dirty = false;
            window.location.reload();
        } catch (error) {
            toast(error.message, null, 'error');
        }
    });

    const publishBtn = $('[data-ve-publish]');
    if (!boot.canPublish) {
        publishBtn.disabled = true;
        publishBtn.title = 'Vous n\'avez pas le droit de publier : votre brouillon est conservé pour un relecteur.';
        publishBtn.textContent = 'Brouillon';
    }
    publishBtn.addEventListener('click', async () => {
        if (!boot.canPublish) return;
        const live = boot.status === 'published';
        if (!window.confirm(live ? 'Publier ces modifications ? Elles seront visibles tout de suite par les visiteurs.' : 'Appliquer ces modifications à la page ?')) return;
        publishBtn.disabled = true;
        try {
            const json = await api('POST', urls.publish, payload());
            dirty = false;
            hasDraft = false;
            $('[data-ve-discard]').hidden = true;
            history.stack = [JSON.stringify(state)];
            history.index = 0;
            syncHistoryButtons();
            setStatus('Publié à l\'instant', 'ok');
            toast('Modifications publiées.', { label: 'Voir la page', run: () => window.open(json.url, '_blank', 'noopener') });
        } catch (error) {
            toast(error.message, null, 'error');
        } finally {
            publishBtn.disabled = false;
        }
    });

    window.addEventListener('beforeunload', (e) => {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    /* ───────── Démarrage ───────── */
    $('[data-ve-discard]').hidden = !hasDraft;
    renderLibrary();
    renderStructure();
    renderSettings();
    renderTitle();
    syncHistoryButtons();
    setStatus(hasDraft ? 'Brouillon en cours (non publié)' : 'Page publiée, aucune modification');

    let started = false;
    const start = () => {
        if (started) return;
        if (!frame.contentWindow?.VE) return setTimeout(start, 50);
        started = true;
        installHost();
        renderCanvas();
    };
    frame.addEventListener('load', start);
    if (frame.contentDocument?.readyState === 'complete' && frame.contentWindow?.VE) start();
})();
