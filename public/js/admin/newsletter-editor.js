// Éditeur de lettres d'information (coque) : panneau (Ajouter / Structure / Bloc / Lettre), historique, enregistrement automatique, essai et envoi.
// L'état de la lettre (objet, aperçu, blocs, thème) vit ici : les blocs forment un ARBRE (colonnes et bandeaux portent d'autres blocs, repérés par leur
// identifiant). Le plan de travail (iframe, newsletter-canvas.js) affiche le courrier dessiné par le serveur (App\Support\NewsletterRenderer, le même
// code que l'envoi) et signale ce qu'on y fait. Pour rester instantané, la lettre n'est jamais rechargée pour un simple changement de bloc : chaque
// bloc de premier niveau est dessiné à part, mis en cache selon son contenu, et seules les lignes modifiées sont remplacées. Les blocs du panneau
// « Ajouter » sont préparés d'avance : ajouter, dupliquer, déplacer, annuler s'affichent sans attendre le serveur. Le serveur valide tout.
(() => {
    const boot = JSON.parse(document.getElementById('nl-boot').textContent);
    const { types, urls, palette, library } = boot;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const clone = (value) => JSON.parse(JSON.stringify(value));

    const locked = boot.locked;
    let state = clone(boot.state);
    const media = { ...(boot.media || {}) };
    let selected = null; // identifiant du bloc sélectionné
    let headerSelected = false; // l'en-tête de la lettre est sélectionné (ses réglages s'affichent dans l'onglet « Bloc »)
    let insertTarget = null; // où ira le prochain bloc ajouté : { parent, col, index }
    let tab = 'add';
    let history = [JSON.stringify(state)];
    let cursor = 0;
    let dirty = false;
    let saving = false;
    let renderSeq = 0;
    let ready = false; // le plan de travail est en place et peut recevoir des lignes
    const timers = { save: 0, rows: 0 };

    let frame = $('[data-nl-frame]');
    const frameWrap = $('[data-nl-frame-wrap]');
    const statusEl = $('[data-nl-status]');
    const toastEl = $('[data-nl-toast]');
    const libraryByKey = new Map(library.map((entry) => [entry.kind + ':' + entry.key, entry]));

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
    const callFrame = (name, ...args) => {
        try {
            frame.contentWindow?.nl?.[name]?.(...args);
        } catch {
            /* le plan de travail est en cours de remplacement */
        }
    };

    /* ───────── L'arbre de blocs ───────── */
    const isContainer = (type) => !!types[type]?.container;

    function* walk(list = state.blocks) {
        for (const block of list) {
            yield block;
            for (const column of block.children ?? []) yield* walk(column);
        }
    }

    /** Où est ce bloc ? { block, list, index, parent, col } (parent = le conteneur, null au premier niveau). */
    function locate(id, list = state.blocks, parent = null, col = 0) {
        for (let index = 0; index < list.length; index++) {
            const block = list[index];
            if (block.id === id) return { block, list, index, parent, col };
            for (let c = 0; c < (block.children?.length ?? 0); c++) {
                const found = locate(id, block.children[c], block, c);
                if (found) return found;
            }
        }

        return null;
    }

    const find = (id) => locate(id)?.block ?? null;
    const depthOf = (id) => {
        let depth = 0;
        let at = locate(id);
        while (at?.parent) {
            depth++;
            at = locate(at.parent.id);
        }

        return depth;
    };
    const topOf = (id) => {
        let at = locate(id);
        while (at?.parent) at = locate(at.parent.id);

        return at?.block ?? null;
    };
    const countAll = (list = state.blocks) => [...walk(list)].length;
    const containsId = (block, id) => [...walk([block])].some((inner) => inner.id === id);

    /** Un bloc neuf à partir d'un modèle : identifiants frais, partout. */
    function renew(block) {
        block.id = newId();
        (block.children ?? []).forEach((column) => column.forEach(renew));

        return block;
    }

    /** Un conteneur n'est accepté qu'aux deux premiers niveaux (voir NewsletterBlocks::MAX_DEPTH). */
    function maxContainerDepth(block, depth) {
        let deepest = isContainer(block.type) ? depth : -1;
        (block.children ?? []).forEach((column) => column.forEach((inner) => (deepest = Math.max(deepest, maxContainerDepth(inner, depth + 1)))));

        return deepest;
    }

    const listOf = (target) => {
        if (!target.parent) return { list: state.blocks, depth: 0 };
        const parent = find(target.parent);
        if (!parent) return null;

        return { list: parent.children[target.col] ?? parent.children[0], depth: depthOf(parent.id) + 1 };
    };

    /** Pose un bloc à l'endroit demandé, si c'est permis. */
    function place(block, target) {
        const spot = listOf(target);
        if (!spot) return false;
        if (maxContainerDepth(block, spot.depth) > boot.maxDepth - 1) {
            toast('Des colonnes ou un bandeau ne peuvent pas être imbriqués davantage ici.', 'error');

            return false;
        }
        if (countAll() + countAll([block]) > boot.max) {
            toast('Une lettre ne peut pas dépasser ' + boot.max + ' blocs.', 'error');

            return false;
        }
        const index = target.index === null || target.index === undefined ? spot.list.length : Math.max(0, Math.min(target.index, spot.list.length));
        spot.list.splice(index, 0, block);

        return true;
    }

    const afterTarget = (id) => {
        const at = locate(id);

        return at ? { parent: at.parent?.id ?? null, col: at.col, index: at.index + 1 } : { parent: null, col: 0, index: null };
    };
    const defaultTarget = () => insertTarget ?? (selected && locate(selected) ? afterTarget(selected) : { parent: null, col: 0, index: null });

    /* ───────── Plan de travail : mise à jour par lignes ───────── */
    const stripIds = (key, value) => (key === 'id' ? undefined : value);
    const keyOf = (block) => JSON.stringify(block, stripIds); // le contenu du bloc, sans ses identifiants
    const idsOf = (block) => [...walk([block])].map((inner) => inner.id);
    const rowKeys = new Map(); // bloc de premier niveau → contenu actuellement affiché dans le plan de travail
    const cache = new Map(); // contenu → { html, ids } : un bloc déjà dessiné (ou préparé d'avance) s'affiche sans passer par le serveur
    const wanted = new Map();
    const inflight = new Set();
    let themeVersion = 0;

    /** Le courrier d'un bloc déjà dessiné, adapté aux identifiants du bloc actuel (copie, déplacement…). */
    function remap(html, from, to) {
        let out = html;
        from.forEach((id, i) => {
            if (id !== to[i]) out = out.split('"' + id + '"').join('"' + to[i] + '"');
        });

        return out;
    }

    function sync() {
        if (!ready || !frame.contentWindow?.nl) return;
        const items = [];
        const missing = [];

        state.blocks.forEach((block) => {
            const key = keyOf(block);
            const hit = cache.get(key);
            if (rowKeys.get(block.id) === key) {
                items.push({ id: block.id });
            } else if (hit) {
                items.push({ id: block.id, html: remap(hit.html, hit.ids, idsOf(block)) });
                rowKeys.set(block.id, key);
            } else {
                items.push({ id: block.id }); // l'ancienne ligne reste, ou un bloc « en chargement » apparaît, le temps que le serveur dessine
                missing.push(block);
            }
        });

        const present = new Set(state.blocks.map((block) => block.id));
        [...rowKeys.keys()].forEach((id) => !present.has(id) && rowKeys.delete(id));
        callFrame('setRows', items);
        if (missing.length) wantRows(missing);
    }

    function wantRows(blocks) {
        blocks.forEach((block) => {
            const key = keyOf(block);
            wanted.forEach((other, otherKey) => other.id === block.id && wanted.delete(otherKey)); // la version précédente de ce bloc n'intéresse plus personne
            if (!cache.has(key) && !inflight.has(key)) wanted.set(key, clone(block));
        });
        clearTimeout(timers.rows);
        timers.rows = setTimeout(flushRows, 30);
    }

    async function flushRows() {
        const batch = [...wanted.entries()].slice(0, 40);
        if (!batch.length) return;
        batch.forEach(([key]) => (wanted.delete(key), inflight.add(key)));
        const version = themeVersion;

        try {
            const result = await api(urls.rows, 'POST', { blocks: batch.map(([, block]) => block), theme: state.theme });
            if (version === themeVersion) {
                batch.forEach(([key, block], i) => cache.set(key, { html: result.rows[i], ids: idsOf(block) }));
                Object.assign(media, result.media || {});
            }
        } catch (error) {
            toast('Le plan de travail n\'a pas pu être mis à jour : ' + error.message, 'error');
        } finally {
            batch.forEach(([key]) => inflight.delete(key));
        }

        if (version === themeVersion) sync();
        if (wanted.size) flushRows();
    }

    /** Prépare d'avance les blocs du panneau « Ajouter » : un clic les pose sur la lettre sans attendre. */
    async function prefetchLibrary() {
        const blocks = library.map((entry) => entry.block).filter((block) => !cache.has(keyOf(block)));
        if (!blocks.length) return;
        const version = themeVersion;
        try {
            const result = await api(urls.rows, 'POST', { blocks, theme: state.theme });
            if (version === themeVersion) blocks.forEach((block, i) => cache.set(keyOf(block), { html: result.rows[i], ids: idsOf(block) }));
        } catch {
            /* sans conséquence : les blocs seront dessinés à la demande */
        }
    }

    /** Le plan de travail vient de montrer ce texte tapé dessus : il n'y a rien à redessiner pour ce bloc. */
    function markDisplayed(id) {
        const top = topOf(id);
        if (top) rowKeys.set(top.id, keyOf(top));
    }

    /** Rechargement complet (ouverture, changement de couleurs ou de police, modèle) : on bascule sur le nouveau plan de travail une fois prêt. */
    async function renderFull() {
        const seq = ++renderSeq;
        const snapshot = clone(state.blocks);
        const scroll = frame.contentWindow?.scrollY ?? 0;
        ready = false;
        themeVersion++;
        cache.clear();
        rowKeys.clear();

        try {
            const result = await api(urls.render, 'POST', payload());
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
                    snapshot.forEach((block) => rowKeys.set(block.id, keyOf(block)));
                    ready = true;
                    callFrame('select', selected);
                    sync(); // la lettre a pu changer pendant le chargement
                    prefetchLibrary();
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

    /** Une modification terminée : on garde un point de retour, on enregistre et on met le plan de travail à jour. */
    function commit({ render = 'rows', panels = true } = {}) {
        pushHistory();
        scheduleSave();
        if (render === 'full') renderFull();
        else if (render === 'header') refreshHeader();
        else if (render) sync();
        if (panels) renderPanels();
    }

    /* ───────── En-tête de la lettre : redessiné seul, sans recharger le plan de travail ───────── */
    const isHeaderKey = (key) => key === 'header' || key.startsWith('header_') || key.startsWith('social_');
    let headerSeq = 0;

    function refreshHeader() {
        clearTimeout(timers.header);
        timers.header = setTimeout(async () => {
            const seq = ++headerSeq;
            try {
                const result = await api(urls.header, 'POST', { theme: state.theme });
                if (seq === headerSeq && ready) callFrame('setHeader', result.html);
            } catch (error) {
                toast('L\'en-tête n\'a pas pu être mis à jour : ' + error.message, 'error');
            }
        }, 70);
    }

    /** Un réglage du thème : l'en-tête se redessine seul, le reste (couleurs, police) recharge le plan de travail. */
    function setThemeKey(key, value, { final = true } = {}) {
        state.theme[key] = value;
        if (final) commit({ panels: false, render: isHeaderKey(key) ? 'header' : 'full' });
        else {
            scheduleSave();
            if (isHeaderKey(key)) refreshHeader();
            else {
                clearTimeout(timers.full);
                timers.full = setTimeout(renderFull, 250);
            }
        }
    }

    function selectHeader() {
        selected = null;
        headerSelected = true;
        insertTarget = null;
        tab = 'block';
        callFrame('select', null);
        callFrame('selectHeader', true);
        renderPanels();
    }

    function travel(step) {
        const target = cursor + step;
        if (target < 0 || target >= history.length) return;
        const before = clone(state.theme);
        cursor = target;
        state = JSON.parse(history[cursor]);
        if (selected && !find(selected)) selected = null;
        updateHistoryButtons();
        scheduleSave();
        const changed = Object.keys(state.theme).filter((key) => state.theme[key] !== before[key]);
        if (changed.some((key) => !isHeaderKey(key))) renderFull();
        else {
            if (changed.length) refreshHeader();
            sync();
        }
        callFrame('select', selected);
        renderPanels();
        $('[data-nl-subject]').value = state.subject;
    }

    /* ───────── Opérations sur les blocs ───────── */
    /** Ajoute un bloc (ou une composition) du panneau « Ajouter », à l'endroit demandé. */
    function insertEntry(kind, key, target = null) {
        const entry = libraryByKey.get(kind + ':' + key);
        if (locked || !entry) return;
        const block = renew(clone(entry.block));
        if (!place(block, target ?? defaultTarget())) return;
        selected = block.id;
        insertTarget = null;
        tab = 'block';
        commit();
        callFrame('select', block.id);
        callFrame('reveal', block.id);
        // Un bloc d'image sans image : on propose tout de suite la médiathèque.
        if (['image', 'feature'].includes(block.type)) setTimeout(() => chooseMedia(block.id), 250);
    }

    function moveTo(id, target) {
        const at = locate(id);
        if (!at) return;
        if (target.parent && (target.parent === id || containsId(at.block, target.parent))) return toast('Un bloc ne peut pas être déposé dans lui-même.', 'error');
        at.list.splice(at.index, 1);
        if (!place(at.block, target)) {
            at.list.splice(at.index, 0, at.block); // refusé : on remet le bloc où il était

            return;
        }
        selected = id;
        commit();
        callFrame('select', id);
    }

    function shift(id, step) {
        const at = locate(id);
        if (!at) return;
        const to = at.index + step;
        if (to < 0 || to >= at.list.length) return;
        moveTo(id, { parent: at.parent?.id ?? null, col: at.col, index: to });
    }

    function duplicate(id) {
        const at = locate(id);
        if (!at) return;
        const copy = renew(clone(at.block));
        if (!place(copy, afterTarget(id))) return;
        selected = copy.id;
        commit();
        callFrame('select', copy.id);
        callFrame('reveal', copy.id);
    }

    function remove(id) {
        const at = locate(id);
        if (!at) return;
        const [removed] = at.list.splice(at.index, 1);
        selected = at.list[at.index]?.id ?? at.list[at.index - 1]?.id ?? at.parent?.id ?? null;
        commit();
        callFrame('select', selected);
        toast('Bloc « ' + label(removed.type) + ' » supprimé. Ctrl+Z pour l\'annuler.');
    }

    /** Le nombre de colonnes change : on ajoute des colonnes vides, ou on verse les blocs des colonnes en trop dans la dernière. */
    function reflowColumns(block) {
        const count = Number(block.data.count);
        const columns = block.children ?? (block.children = []);
        while (columns.length < count) columns.push([]);
        while (columns.length > count) columns.at(-1).forEach((inner) => columns[count - 1].push(inner)), columns.pop();
        const ratios = boot.ratios[count];
        if (!ratios[block.data.ratio]) block.data.ratio = Object.keys(ratios)[0];
    }

    function selectBlock(id, { reveal = false } = {}) {
        selected = id;
        headerSelected = false;
        callFrame('selectHeader', false);
        insertTarget = null;
        if (tab === 'add' || tab === 'block') tab = id ? 'block' : 'add';
        callFrame('select', id);
        if (reveal) callFrame('reveal', id);
        renderPanels();
    }

    /* ───────── Messages du plan de travail ───────── */
    window.addEventListener('message', (event) => {
        if (event.source !== frame.contentWindow || !event.data?.nl) return;
        const m = event.data;

        if (m.op === 'select') {
            selected = m.id;
            headerSelected = false;
            insertTarget = null;
            if (tab === 'add' || tab === 'block') tab = m.id ? 'block' : 'add';
            renderPanels();
        } else if (m.op === 'edit') {
            const block = find(m.id);
            if (block && m.field in block.data) {
                block.data[m.field] = m.value;
                markDisplayed(m.id);
                scheduleSave();
                renderStructure();
            }
        } else if (m.op === 'header') selectHeader();
        else if (m.op === 'theme-edit') {
            // Le hashtag modifié sur la lettre : le plan de travail le montre déjà, il n'y a rien à redessiner.
            state.theme[m.key] = m.value;
            scheduleSave();
            const input = $('[data-nl-header-tag]');
            if (input && input !== document.activeElement) input.value = m.value;
        } else if (m.op === 'commit') pushHistory();
        else if (m.op === 'up') shift(m.id, -1);
        else if (m.op === 'down') shift(m.id, 1);
        else if (m.op === 'move') moveTo(m.id, { parent: m.parent, col: m.col, index: m.index });
        else if (m.op === 'dup') duplicate(m.id);
        else if (m.op === 'del') remove(m.id);
        else if (m.op === 'drop-add') insertEntry(m.kind, m.key, { parent: m.parent, col: m.col, index: m.index });
        else if (m.op === 'add') {
            insertTarget = afterTarget(m.id);
            tab = 'add';
            renderPanels();
            toast('Choisissez le bloc à ajouter dans le panneau de gauche, ou glissez-le sur la lettre.');
        } else if (m.op === 'slot') {
            insertTarget = { parent: m.parent, col: m.col, index: null };
            tab = 'add';
            renderPanels();
            toast('Choisissez le bloc à ajouter dans cette zone.');
        } else if (m.op === 'parent') {
            const at = locate(m.id);
            if (at?.parent) selectBlock(at.parent.id, { reveal: true });
        } else if (m.op === 'media') chooseMedia(m.id);
        else if (m.op === 'undo') travel(-1);
        else if (m.op === 'redo') travel(1);
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

    /** Petit schéma d'un bloc, pour le reconnaître d'un coup d'œil. */
    const WIRES = {
        heading: 'bar', text: 'lines', image: 'img', button: 'btn', quote: 'quo lines', feature: 'img lines', news: 'row row', columns: 'col col', section: 'band', divider: 'hr', spacer: 'gap',
        'photo-text': 'img lines', 'text-photo': 'lines img', 'two-photos': 'img img', 'three-photos': 'img img img', figures: 'fig fig fig', 'banner-light': 'band', 'banner-dark': 'band dark',
    };
    const wire = (key) => h('span', { class: 'nl-wire', 'aria-hidden': 'true' }, (WIRES[key] ?? 'lines').split(' ').map((part) => h('i', { class: 'nl-w nl-w--' + part })));

    function describeTarget() {
        if (insertTarget) {
            const parent = insertTarget.parent ? find(insertTarget.parent) : null;

            return parent ? 'dans ' + (parent.type === 'columns' ? 'la colonne ' + (insertTarget.col + 1) + ' des colonnes' : 'le bandeau') : 'à la position choisie';
        }

        return selected ? 'sous le bloc sélectionné' : 'à la fin de la lettre';
    }

    function renderAdd() {
        const pane = $('[data-nl-pane="add"]');
        const groups = [
            ['content', 'Contenu'],
            ['layout', 'Mise en page'],
            ['preset', 'Compositions toutes faites'],
        ];
        const card = (entry) =>
            h(
                'div',
                {
                    role: 'button',
                    tabindex: '0',
                    class: 've-card nl-card',
                    draggable: 'true',
                    onkeydown: (event) => (event.key === 'Enter' || event.key === ' ') && (event.preventDefault(), insertEntry(entry.kind, entry.key)),
                    title: 'Cliquer pour ajouter, ou glisser sur la lettre',
                    onclick: () => insertEntry(entry.kind, entry.key),
                    ondragstart: (event) => {
                        event.dataTransfer.setData('text/nl-add', entry.kind + ':' + entry.key);
                        event.dataTransfer.setData('text/plain', entry.label);
                        event.dataTransfer.effectAllowed = 'copy';
                    },
                },
                wire(entry.key),
                h('strong', {}, entry.label),
                h('span', { class: 've-hint' }, entry.hint),
            );

        pane.replaceChildren(...[
            h('p', { class: 've-pane__intro' }, 'Cliquez sur un bloc pour l\'ajouter ' + describeTarget() + ', ou glissez-le directement sur la lettre.'),
            insertTarget ? h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => ((insertTarget = null), renderAdd()) }, 'Annuler le choix de l\'emplacement') : null,
            ...groups.map(([group, title]) => h('section', { class: 'nl-group' }, h('h3', { class: 'nl-section' }, title), h('div', { class: 've-library' }, library.filter((entry) => entry.group === group).map(card)))),
        ].filter(Boolean));
    }

    const plain = (html) => (html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    const preview = (block) => {
        const d = block.data;
        const text = { heading: d.text, text: plain(d.html), button: d.label, quote: d.text, feature: d.title, news: d.title, columns: block.children?.length + ' colonnes', section: block.children?.[0]?.length + ' bloc(s)' }[block.type];

        return text ? String(text).slice(0, 38) : '';
    };

    /** L'arbre des blocs : chaque conteneur montre ses colonnes ; on glisse un bloc d'une liste à l'autre. */
    function renderStructure() {
        const pane = $('[data-nl-pane="structure"]');
        if (!pane) return;
        let dragged = null;

        const dropOn = (parent, col, rawIndex) => {
            if (!dragged) return;
            const at = locate(dragged);
            const sameList = at && (at.parent?.id ?? null) === (parent?.id ?? null) && at.col === col;
            moveTo(dragged, { parent: parent?.id ?? null, col, index: sameList && at.index < rawIndex ? rawIndex - 1 : rawIndex });
        };
        const zone = (parent, col, rawIndex, extra = {}) => ({
            ondragover: (event) => (event.preventDefault(), event.stopPropagation(), event.currentTarget.classList.add('is-over')),
            ondragleave: (event) => event.currentTarget.classList.remove('is-over'),
            ondrop: (event) => (event.preventDefault(), event.stopPropagation(), dropOn(parent, col, rawIndex)),
            ...extra,
        });

        const list = (blocks, parent, col) => {
            const items = blocks.map((block, index) => item(block, blocks, index, parent, col));
            items.push(h('li', { class: 'nl-tree__end' + (blocks.length ? '' : ' is-empty'), ...zone(parent, col, blocks.length) }, blocks.length ? '' : 'Glissez un bloc ici'));

            return h('ol', { class: 've-list nl-tree' }, items);
        };

        const item = (block, blocks, index, parent, col) => {
            const row = h(
                'div',
                { class: 've-item' + (block.id === selected ? ' is-on' : '') },
                h('span', { class: 've-item__grip', 'aria-hidden': 'true' }, '⠿'),
                h('button', { type: 'button', class: 've-item__main', onclick: () => selectBlock(block.id, { reveal: true }) }, h('strong', {}, label(block.type)), h('span', { class: 've-hint' }, preview(block))),
                h('button', { type: 'button', class: 've-mini', title: 'Monter', 'aria-label': 'Monter', disabled: index === 0, onclick: () => shift(block.id, -1) }, '↑'),
                h('button', { type: 'button', class: 've-mini', title: 'Descendre', 'aria-label': 'Descendre', disabled: index === blocks.length - 1, onclick: () => shift(block.id, 1) }, '↓'),
                h('button', { type: 'button', class: 've-mini', title: 'Supprimer', 'aria-label': 'Supprimer', onclick: () => remove(block.id) }, '🗑'),
            );
            const inner = (block.children ?? []).map((column, c) => h('div', { class: 'nl-tree__col' }, block.type === 'columns' ? h('span', { class: 'nl-tree__colname' }, 'Colonne ' + (c + 1)) : null, list(column, block, c)));

            return h(
                'li',
                {
                    class: 'nl-tree__item',
                    draggable: 'true',
                    ondragstart: (event) => {
                        event.stopPropagation();
                        dragged = block.id;
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', block.id);
                        event.currentTarget.classList.add('is-drag');
                    },
                    ondragend: (event) => event.currentTarget.classList.remove('is-drag'),
                    ...zone(parent, col, index),
                },
                row,
                inner,
            );
        };

        pane.replaceChildren(h('p', { class: 've-pane__intro' }, state.blocks.length ? 'Glissez pour changer l\'ordre, même d\'une colonne à l\'autre.' : 'La lettre est vide.'), list(state.blocks, null, 0));
    }

    /* ───────── Champs de réglage ───────── */
    const typed = (type, key, raw) => (typeof types[type].defaults[key] === 'number' ? Number(raw) : raw);

    function setData(block, key, value, { final = true } = {}) {
        block.data[key] = value;
        if (block.type === 'columns' && key === 'count') reflowColumns(block);
        if (final) commit({ panels: false });
        else {
            scheduleSave();
            sync();
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

        if (spec.kind === 'select') {
            const options = spec.optionsByCount ? spec.optionsByCount[block.data.count] ?? spec.options : spec.options;
            control = segmented(options, value, (picked) => (setData(block, spec.key, typed(block.type, spec.key, picked)), renderBlock(), renderStructure()));
        } else if (spec.kind === 'color') control = colorControl(value, (picked) => (setData(block, spec.key, picked), renderBlock()), spec.empty ?? null);
        else if (spec.kind === 'range') control = rangeControl(spec, value, (v) => setData(block, spec.key, v, { final: false }), (v) => setData(block, spec.key, v));
        else if (spec.kind === 'toggle') control = h('label', { class: 'nl-toggle' }, h('input', { type: 'checkbox', checked: !!value, onchange: (event) => setData(block, spec.key, event.target.checked ? '1' : '') }), 'Oui');
        else if (spec.kind === 'url') control = h('input', { type: 'url', class: 'nl-input', value, placeholder: 'https://…', oninput: (event) => setData(block, spec.key, event.target.value, { final: false }), onchange: (event) => setData(block, spec.key, event.target.value) });
        else if (spec.kind === 'media') {
            const asset = media[value];
            control = h(
                'div',
                { class: 've-media-field' },
                asset ? h('img', { src: asset.thumb, alt: asset.alt }) : h('span', { class: 've-media-field__empty' }, 'Aucune image'),
                h('div', {}, h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => chooseMedia(block.id) }, asset ? 'Changer' : 'Choisir une image'), asset ? h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => (setData(block, spec.key, 0), renderBlock()) }, 'Retirer') : null),
            );
        }

        return h('div', { class: 've-field' }, h('span', { class: 've-label' }, spec.label), control);
    }

    /** Réglages de l'en-tête : couleur, hauteur, emplacement de chaque élément, taille du logo, hashtag, réseaux sociaux. */
    function renderHeaderPanel(pane) {
        const theme = state.theme;
        const info = boot.header;
        const set = (key, value, options) => (setThemeKey(key, value, options), renderHeaderPanel(pane));
        const range = (key, title, min, max) => h('div', { class: 've-field' }, h('span', { class: 've-label' }, title), rangeControl({ min, max, unit: ' px', label: title }, theme[key], (v) => setThemeKey(key, v, { final: false }), (v) => setThemeKey(key, v)));
        const shown = !!theme.header;

        pane.replaceChildren(
            ...[
                h('div', { class: 've-block-head' }, h('strong', {}, 'En-tête de la lettre'), h('span', { class: 've-hint' }, 'Cliquez sur le hashtag pour le modifier directement.')),
                h('div', { class: 've-field' }, h('label', { class: 'nl-toggle' }, h('input', { type: 'checkbox', checked: shown, onchange: (event) => set('header', event.target.checked ? '1' : '') }), 'Afficher l\'en-tête')),
                shown ? h('div', { class: 've-field' }, h('span', { class: 've-label' }, 'Couleur de fond'), colorControl(theme.header_color, (picked) => set('header_color', picked), null, ['#232e66', '#131a3f', '#8a724b', '#1d2233', '#ffffff', '#e1ebff'])) : null,
                shown ? range('header_height', 'Hauteur de l\'en-tête', 60, 260) : null,
                shown ? h('div', { class: 'nl-section' }, 'Emplacement des éléments') : null,
                ...(shown
                    ? Object.entries(info.items).map(([item, title]) => h('div', { class: 've-field' }, h('span', { class: 've-label' }, title), segmented(info.slots, theme['header_' + item], (value) => set('header_' + item, value))))
                    : []),
                shown && theme.header_logo ? range('header_logo_size', 'Taille du logo', 32, 160) : null,
                shown && theme.header_tag
                    ? h('div', { class: 've-field' }, h('label', { class: 've-label', for: 'nl-header-tag' }, 'Texte du hashtag'), h('input', { id: 'nl-header-tag', class: 'nl-input', 'data-nl-header-tag': '', maxlength: 40, value: theme.header_tag_text, placeholder: '#UAZINFOS', oninput: (event) => setThemeKey('header_tag_text', event.target.value, { final: false }), onchange: (event) => setThemeKey('header_tag_text', event.target.value) }))
                    : null,
                shown ? h('div', { class: 've-field' }, h('label', { class: 'nl-toggle' }, h('input', { type: 'checkbox', checked: !!theme.header_rule, onchange: (event) => set('header_rule', event.target.checked ? '1' : '') }), 'Filet doré sous l\'en-tête')) : null,
                shown ? h('div', { class: 'nl-section' }, 'Réseaux sociaux') : null,
                shown && theme.header_social === '' ? h('p', { class: 'nl-hint' }, 'Placez « Réseaux sociaux » à gauche, au centre ou à droite pour les afficher. Une icône n\'apparaît que si son adresse est renseignée.') : null,
                ...(shown
                    ? Object.entries(info.socials).map(([network, title]) =>
                          h('div', { class: 've-field' }, h('label', { class: 've-label', for: 'nl-social-' + network }, title), h('input', { id: 'nl-social-' + network, type: 'url', class: 'nl-input', value: theme['social_' + network], placeholder: 'https://…', oninput: (event) => setThemeKey('social_' + network, event.target.value, { final: false }), onchange: (event) => setThemeKey('social_' + network, event.target.value) })),
                      )
                    : []),
                shown ? h('div', { class: 've-actions' },
                    h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => { Object.entries(info.socials).forEach(([network]) => info.site[network] && (theme['social_' + network] = info.site[network])); commit({ panels: false, render: 'header' }); renderHeaderPanel(pane); } }, 'Reprendre les adresses du site'),
                    h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => { Object.entries(info.uaz).forEach(([key, value]) => (!key.startsWith('social_') || !theme[key]) && (theme[key] = value)); commit({ panels: false, render: 'header' }); renderHeaderPanel(pane); } }, 'Disposition de l\'UAZ')) : null,
            ].filter(Boolean),
        );
    }

    function renderBlock() {
        const pane = $('[data-nl-pane="block"]');
        if (!pane) return;
        if (headerSelected) return renderHeaderPanel(pane);
        const block = selected ? find(selected) : null;

        if (!block) {
            pane.replaceChildren(h('p', { class: 've-pane__intro' }, 'Cliquez sur un bloc du plan de travail pour régler son aspect. Pour régler une colonne ou un bandeau, cliquez sur son fond (ou sur « ↰ » dans la barre d\'un bloc qu\'il contient).'));

            return;
        }

        const meta = types[block.type];
        const inline = Object.keys(meta.inline ?? {}).length > 0;
        const trail = [];
        for (let at = locate(block.id); at; at = at.parent ? locate(at.parent.id) : null) trail.unshift(label(at.block.type));
        const at = locate(block.id);

        pane.replaceChildren(
            ...[
                h('div', { class: 've-block-head' }, h('strong', {}, meta.label), trail.length > 1 ? h('span', { class: 've-hint' }, trail.join(' › ')) : null),
                at?.parent ? h('button', { type: 'button', class: 've-btn ve-btn--sm nl-parent', onclick: () => selectBlock(at.parent.id, { reveal: true }) }, '↰ Sélectionner ' + (at.parent.type === 'columns' ? 'les colonnes' : 'le bandeau')) : null,
                inline ? h('p', { class: 'nl-hint' }, 'Le texte se modifie directement sur le plan de travail : cliquez dessus.') : null,
                isContainer(block.type) ? h('div', { class: 've-field' }, h('span', { class: 've-label' }, 'Ajouter un bloc dans…'), h('div', { class: 'nl-seg' }, block.children.map((_, c) => h('button', { type: 'button', onclick: () => ((insertTarget = { parent: block.id, col: c, index: null }), (tab = 'add'), renderAdd(), showTab('add')) }, block.type === 'columns' ? 'Colonne ' + (c + 1) : 'Ce bandeau')))) : null,
                ...meta.fields.map((spec) => field(spec, block)),
                h('div', { class: 've-actions' },
                    h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => duplicate(block.id) }, 'Dupliquer'),
                    h('button', { type: 'button', class: 've-btn ve-btn--sm', onclick: () => remove(block.id) }, 'Supprimer')),
            ].filter(Boolean),
        );
    }

    function renderLetter() {
        const pane = $('[data-nl-pane="letter"]');
        if (!pane) return;
        const theme = state.theme;
        const setTheme = (key, value) => (setThemeKey(key, value), renderLetter());
        const colorRow = (key, title, colors) => h('div', { class: 've-field' }, h('span', { class: 've-label' }, title), colorControl(theme[key], (picked) => setTheme(key, picked), null, colors));

        pane.replaceChildren(
            ...[
            h('div', { class: 've-field' },
                h('button', { type: 'button', class: 've-btn ve-btn--primary', onclick: openTemplates }, 'Choisir un modèle…'),
                h('p', { class: 've-hint' }, 'Remplace la mise en page actuelle (on peut annuler avec Ctrl+Z).')),
            h('div', { class: 've-field' },
                h('label', { class: 've-label', for: 'nl-preheader' }, 'Texte d\'aperçu'),
                h('input', { id: 'nl-preheader', class: 'nl-input', maxlength: 190, value: state.preheader, placeholder: 'Une ligne affichée à côté de l\'objet', oninput: (event) => ((state.preheader = event.target.value), scheduleSave()), onchange: () => pushHistory() }),
                h('p', { class: 've-hint' }, 'Visible dans la liste des messages, avant l\'ouverture.')),
            h('div', { class: 'nl-section' }, 'Couleurs'),
            colorRow('background', 'Fond autour de la lettre', ['#eef0f6', '#fbf9f4', '#e1ebff', '#131a3f', '#ffffff']),
            colorRow('card', 'Fond de la lettre', ['#ffffff', '#fbf9f4', '#f4f6fb', '#e1ebff']),
            colorRow('text', 'Texte', ['#1d2233', '#23272d', '#131a3f', '#4b5160']),
            colorRow('accent', 'Titres et liens', ['#232e66', '#131a3f', '#8a724b', '#b4452f', '#1f8a5b']),
            colorRow('gold', 'Filets et détails', ['#8a724b', '#e0b957', '#232e66', '#9aa1b5']),
            h('div', { class: 'nl-section' }, 'Typographie'),
            h('div', { class: 've-field' }, h('span', { class: 've-label' }, 'Police des titres'), segmented({ serif: 'Avec empattements', sans: 'Sans empattements' }, theme.font, (value) => setTheme('font', value))),
            h('div', { class: 'nl-section' }, 'En-tête'),
            h('div', { class: 've-field' }, h('button', { type: 'button', class: 've-btn', onclick: selectHeader }, 'Régler l\'en-tête…'), h('p', { class: 've-hint' }, 'Logo, hashtag, réseaux sociaux, hauteur et emplacements : cliquez aussi directement sur l\'en-tête de la lettre.')),
            ].filter(Boolean),
        );
    }

    /* ───────── Modèles de lettre ───────── */
    const templatesDialog = $('[data-nl-dialog="templates"]');
    let templatesLoaded = null;

    async function openTemplates() {
        if (locked) return;
        templatesDialog.showModal();
        const grid = $('[data-nl-templates-grid]');
        if (!templatesLoaded) {
            grid.replaceChildren(h('p', { class: 've-hint' }, 'Préparation des aperçus…'));
            templatesLoaded = api(urls.templates, 'GET').then((result) => result.templates);
        }
        try {
            const templates = await templatesLoaded;
            grid.replaceChildren(
                ...templates.map((template) => {
                    const preview = h('iframe', { class: 'nl-tpl__frame', title: 'Aperçu du modèle ' + template.name, tabindex: '-1', loading: 'lazy', sandbox: '' });
                    preview.srcdoc = template.preview;

                    return h(
                        'article',
                        { class: 'nl-tpl' },
                        h('div', { class: 'nl-tpl__view' }, preview),
                        h('div', { class: 'nl-tpl__body' }, h('strong', {}, template.name), h('p', { class: 've-hint' }, template.description), h('button', { type: 'button', class: 've-btn ve-btn--primary ve-btn--sm', onclick: () => applyTemplate(template) }, 'Utiliser ce modèle')),
                    );
                }),
            );
        } catch (error) {
            templatesLoaded = null;
            grid.replaceChildren(h('p', { class: 'nl-dialog__error' }, 'Les modèles n\'ont pas pu être chargés : ' + error.message));
        }
    }

    function applyTemplate(template) {
        state.blocks = clone(template.blocks);
        state.theme = clone(template.theme);
        if (!state.subject.trim()) {
            state.subject = template.subject;
            $('[data-nl-subject]').value = template.subject;
        }
        Object.assign(media, template.media || {});
        selected = null;
        insertTarget = null;
        tab = 'add';
        templatesDialog.close();
        commit({ render: 'full' });
        toast('Modèle « ' + template.name + ' » appliqué. Ctrl+Z pour revenir en arrière.');
    }

    /* ───────── Médiathèque ───────── */
    const mediaDialog = $('[data-nl-media]');
    const grid = $('[data-nl-media-grid]');
    const more = $('[data-nl-media-more]');
    const uploadForm = $('[data-nl-media-upload]');
    let mediaTarget = null;
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

    function chooseMedia(id) {
        if (locked || !id || !find(id)) return;
        mediaTarget = id;
        pendingFile = null;
        uploadForm.hidden = true;
        mediaDialog.showModal();
        loadMedia();
    }

    function pickMedia(asset) {
        const block = find(mediaTarget);
        if (block) {
            media[asset.id] = asset;
            block.data.media = asset.id;
            selected = block.id;
            mediaDialog.close();
            commit();
            callFrame('select', block.id);
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
            setTimeout(() => window.dispatchEvent(new Event('resize')), 50);
        }),
    );
    $('[data-nl-undo]')?.addEventListener('click', () => travel(-1));
    $('[data-nl-redo]')?.addEventListener('click', () => travel(1));
    $$('[data-nl-tab]').forEach((button) => button.addEventListener('click', () => ((tab = button.dataset.nlTab), showTab(tab))));
    $('[data-nl-subject]')?.addEventListener('input', (event) => ((state.subject = event.target.value), scheduleSave()));
    $('[data-nl-subject]')?.addEventListener('change', () => pushHistory());
    $$('[data-nl-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog').close()));

    document.addEventListener('keydown', (event) => {
        if (event.target.closest('input, textarea, select, dialog, [contenteditable]')) return;
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
            : 'La lettre « ' + state.subject + ' » va être envoyée à ' + boot.audience + ' abonné' + (boot.audience > 1 ? 's' : '') + ' actif' + (boot.audience > 1 ? 's' : '') + ' (adresse confirmée, non désinscrit), depuis ' + boot.from + '.';
        // Oublis courants : une image vide ne s'affiche pas dans le courrier, un bouton sans adresse mène à la page d'accueil du site.
        const all = [...walk()];
        const warnings = [];
        const bare = all.filter((block) => ['image', 'feature'].includes(block.type) && !block.data.media).length;
        const blind = all.filter((block) => block.type === 'button' && !block.data.url).length;
        if (bare) warnings.push(bare + ' bloc' + (bare > 1 ? 's' : '') + ' « image » sans photo (ils n\'apparaîtront pas).');
        if (blind) warnings.push(blind + ' bouton' + (blind > 1 ? 's' : '') + ' sans adresse de lien (ils mèneront à la page d\'accueil du site).');
        if (!all.length) warnings.push('La lettre est vide.');
        const warn = $('[data-nl-send-warn]');
        warn.hidden = warnings.length === 0;
        warn.replaceChildren(...warnings.map((text) => h('li', {}, text)));
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
    renderFull();
})();
