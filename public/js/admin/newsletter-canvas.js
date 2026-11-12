// Plan de travail de l'éditeur de newsletters (dans l'iframe) : on modifie les textes sur place, on clique un bloc pour le sélectionner (n'importe quel
// bloc, même dans une colonne ou un bandeau), une barre flottante permet de le déplacer (glisser), le monter, le descendre, le dupliquer, le
// supprimer. Ce script ne garde aucun état de la newsletter : il signale ce qui se passe à l'éditeur (window.parent), qui tient l'état, l'enregistre
// et met à jour le plan de travail par window.nl (setRows, select) sans jamais recharger l'iframe pour un simple changement de bloc.
(() => {
    const LABELS = { heading: 'Titre', text: 'Texte', image: 'Image', button: 'Bouton', quote: 'Citation', feature: 'Image et texte', hero: 'Photo en fond', mosaic: 'Mosaïque', news: 'Actualités', divider: 'Séparateur', spacer: 'Espace', columns: 'Colonnes', section: 'Bandeau' };
    const tell = (message) => window.parent.postMessage({ nl: true, ...message }, '*');
    const idOf = (el) => el?.dataset.nlId ?? null;
    const byId = (id) => (id ? document.querySelector('[data-nl-block][data-nl-id="' + id + '"]') : null);
    const listOf = (block) => block?.parentElement?.closest('[data-nl-list]') ?? null;
    const root = () => document.querySelector('[data-nl-list="root"]');

    /** Les blocs placés directement dans une liste (la newsletter, une colonne, un bandeau), dans l'ordre. */
    const blocksIn = (list) => [...list.querySelectorAll('[data-nl-block]')].filter((block) => listOf(block) === list);

    let selectedId = null;
    let headerOn = false; // l'en-tête de la newsletter est sélectionné
    let bar = null;
    let hovered = null;
    let drop = null;

    /* ───────── Texte modifiable sur place ───────── */
    function bind(scope) {
        scope.querySelectorAll('[data-nl-theme-field]').forEach((el) => {
            if (el.dataset.nlBound) return;
            el.dataset.nlBound = '1';
            el.contentEditable = 'plaintext-only';
            if (el.contentEditable !== 'plaintext-only') el.contentEditable = 'true';
            el.spellcheck = false;
        });
        scope.querySelectorAll('[data-nl-field]').forEach((el) => {
            if (el.dataset.nlBound) return;
            el.dataset.nlBound = '1';
            // « plaintext-only » : pas de mise en forme collée dans un titre ; les navigateurs plus anciens retombent sur « true ».
            el.contentEditable = el.dataset.nlField === 'html' ? 'true' : 'plaintext-only';
            if (el.contentEditable !== 'true' && el.contentEditable !== 'plaintext-only') el.contentEditable = 'true';
            el.spellcheck = true;
            el.lang = 'fr';
        });
    }

    const timers = new WeakMap();
    const report = (el) => {
        clearTimeout(timers.get(el));
        const block = el.closest('[data-nl-block]');
        const rich = el.dataset.nlField === 'html';
        tell({ op: 'edit', id: idOf(block), field: el.dataset.nlField, value: rich ? el.innerHTML : el.textContent.replace(/\s+/g, ' ') });
    };

    const reportTheme = (el) => {
        clearTimeout(timers.get(el));
        tell({ op: 'theme-edit', key: el.dataset.nlThemeField, value: el.textContent.replace(/\s+/g, ' ').trim() });
    };

    document.addEventListener('input', (event) => {
        const themeField = event.target.closest?.('[data-nl-theme-field]');
        if (themeField) {
            clearTimeout(timers.get(themeField));
            timers.set(themeField, setTimeout(() => reportTheme(themeField), 250));

            return;
        }
        const el = event.target.closest?.('[data-nl-field]');
        if (!el) return;
        clearTimeout(timers.get(el));
        timers.set(el, setTimeout(() => report(el), 250));
    });
    document.addEventListener('focusout', (event) => {
        const themeField = event.target.closest?.('[data-nl-theme-field]');
        if (themeField) {
            reportTheme(themeField);
            tell({ op: 'commit' });

            return;
        }
        const el = event.target.closest?.('[data-nl-field]');
        if (!el) return;
        report(el);
        tell({ op: 'commit' });
    });
    document.addEventListener('focusin', (event) => {
        if (event.target.closest?.('[data-nl-theme-field]')) return selectHeader();
        const el = event.target.closest?.('[data-nl-field]');
        if (el) select(idOf(el.closest('[data-nl-block]')));
    });
    document.addEventListener('paste', (event) => {
        if (!event.target.closest?.('[data-nl-field]')) return;
        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
    });

    /* ───────── Sélection ───────── */
    function paint() {
        document.querySelectorAll('.nl-selected').forEach((el) => el.classList.remove('nl-selected'));
        byId(selectedId)?.classList.add('nl-selected');
        if (headerOn) document.querySelector('[data-nl-header]')?.classList.add('nl-selected');
    }

    /** L'en-tête de la newsletter (bandeau, logo, hashtag, réseaux) : un clic dessus ouvre ses réglages dans le panneau. */
    function selectHeader({ notify = true } = {}) {
        if (headerOn && !selectedId) return;
        headerOn = true;
        selectedId = null;
        paint();
        placeBar();
        if (notify) tell({ op: 'header' });
    }

    function select(id, { notify = true } = {}) {
        id = id || null;
        if (id === selectedId && bar && !headerOn) return;
        headerOn = false;
        selectedId = id;
        paint();
        placeBar();
        if (notify) tell({ op: 'select', id });
    }

    document.addEventListener('pointerover', (event) => {
        const block = event.target.closest?.('[data-nl-block]') ?? null;
        if (block === hovered) return;
        hovered?.classList.remove('nl-hover');
        hovered = block;
        hovered?.classList.add('nl-hover');
    });
    document.addEventListener('pointerleave', () => {
        hovered?.classList.remove('nl-hover');
        hovered = null;
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (link) event.preventDefault(); // on ne quitte jamais le plan de travail par un lien
        if (event.target.closest('.nl-bar')) return;

        if (event.target.closest('[data-nl-header]')) return selectHeader();

        const slot = event.target.closest('[data-nl-slot]');
        if (slot) {
            tell({ op: 'slot', parent: slot.dataset.nlParent, col: Number(slot.dataset.nlCol) });

            return;
        }

        const block = event.target.closest('[data-nl-block]');
        select(idOf(block));
        // Un emplacement d'image vide : un clic suffit pour ouvrir la médiathèque.
        if (event.target.closest('[data-nl-placeholder]') && block) tell({ op: 'media', id: idOf(block), field: event.target.closest('[data-nl-media]')?.dataset.nlMedia || 'media' });
    });

    document.addEventListener('dblclick', (event) => {
        const block = event.target.closest('[data-nl-block]');
        if (block && ['image', 'feature', 'hero', 'mosaic'].includes(block.dataset.nlType) && !event.target.closest('[data-nl-field]')) {
            tell({ op: 'media', id: idOf(block), field: event.target.closest('[data-nl-media]')?.dataset.nlMedia || 'media' });
        }
    });

    document.addEventListener('keydown', (event) => {
        const editing = event.target.closest?.('[contenteditable]');
        const mod = event.ctrlKey || event.metaKey;
        const key = event.key.toLowerCase();

        if (event.key === 'Escape' && editing) editing.blur();
        else if (!editing && mod && key === 'z') {
            event.preventDefault();
            tell({ op: event.shiftKey ? 'redo' : 'undo' });
        } else if (!editing && mod && key === 'y') {
            event.preventDefault();
            tell({ op: 'redo' });
        } else if (!editing && mod && key === 'd' && selectedId) {
            event.preventDefault();
            tell({ op: 'dup', id: selectedId });
        } else if (!editing && selectedId && (event.key === 'Delete' || event.key === 'Backspace')) {
            event.preventDefault();
            tell({ op: 'del', id: selectedId });
        } else if (!editing && event.key === 'Escape') {
            select(null);
        } else if (editing && !event.shiftKey && event.key === 'Enter' && editing.contentEditable === 'plaintext-only') {
            event.preventDefault();
            editing.blur();
        }
    });

    /* ───────── Barre flottante ───────── */
    const button = (label, op, title) => {
        const node = document.createElement('button');
        node.type = 'button';
        node.innerHTML = label;
        node.title = title;
        node.setAttribute('aria-label', title);
        node.dataset.op = op;

        return node;
    };
    const separator = () => Object.assign(document.createElement('span'), { className: 'nl-bar__sep' });

    function placeBar() {
        bar?.remove();
        bar = null;
        const block = byId(selectedId);
        if (!block) return;

        const type = block.dataset.nlType;
        const siblings = blocksIn(listOf(block));
        const at = siblings.indexOf(block);
        const nested = Number(block.dataset.nlDepth) > 0;

        bar = document.createElement('div');
        bar.className = 'nl-bar';
        bar.setAttribute('role', 'toolbar');
        bar.setAttribute('aria-label', 'Actions du bloc ' + (LABELS[type] ?? type));

        const tag = document.createElement('span');
        tag.className = 'nl-bar__tag';
        tag.textContent = LABELS[type] ?? type;
        const grip = button('⠿', 'grip', 'Glisser pour déplacer le bloc');
        grip.classList.add('nl-bar__grip');
        bar.append(tag, grip);

        // Mise en forme du texte : seulement dans les blocs qui ont un texte enrichi.
        if (block.querySelector('[data-nl-field="html"]')) {
            bar.append(separator(), button('<b>G</b>', 'bold', 'Gras'), button('<i>I</i>', 'italic', 'Italique'), button('🔗', 'link', 'Ajouter un lien'), button('•&nbsp;≡', 'list', 'Liste à puces'));
        }
        if (['image', 'feature', 'hero', 'mosaic'].includes(type)) bar.append(separator(), button('🖼', 'media', 'Choisir la photo'));

        const up = button('↑', 'up', 'Monter');
        const down = button('↓', 'down', 'Descendre');
        up.disabled = at <= 0;
        down.disabled = at === siblings.length - 1;
        bar.append(separator());
        if (nested) bar.append(button('↰', 'parent', 'Sélectionner le conteneur'));
        bar.append(up, down, button('⧉', 'dup', 'Dupliquer'), button('＋', 'add', 'Ajouter un bloc dessous'), button('🗑', 'del', 'Supprimer le bloc'));

        // Les boutons ne doivent pas faire perdre la sélection du texte.
        bar.addEventListener('mousedown', (event) => event.preventDefault());
        bar.addEventListener('click', onBar);
        grip.addEventListener('pointerdown', startDrag);
        document.body.append(bar);
        positionBar();
    }

    function positionBar() {
        const block = byId(selectedId);
        if (!bar || !block) return;
        const rect = block.getBoundingClientRect();
        const top = rect.top + window.scrollY;
        const height = bar.offsetHeight;
        bar.style.top = (top - height - 6 < window.scrollY + 4 ? top + 6 : top - height - 6) + 'px';
        bar.style.left = Math.max(6, Math.min(rect.left + window.scrollX + 8, document.documentElement.clientWidth - bar.offsetWidth - 6)) + 'px';
    }

    function onBar(event) {
        const op = event.target.closest('button')?.dataset.op;
        if (!op || !selectedId) return;

        if (op === 'bold' || op === 'italic') document.execCommand(op);
        else if (op === 'list') document.execCommand('insertUnorderedList');
        else if (op === 'link') {
            const url = window.prompt('Adresse du lien (https://…)');
            if (url) document.execCommand('createLink', false, url);
        } else if (['up', 'down', 'dup', 'del', 'add', 'media', 'parent'].includes(op)) {
            tell({ op, id: selectedId });
        }
    }

    /* ───────── Où va tomber un bloc ? (déplacement par la poignée, ou bloc glissé depuis le panneau « Ajouter ») ───────── */
    function dropTarget(x, y, ignore = null) {
        let list = document.elementFromPoint(x, y)?.closest('[data-nl-list]') ?? null;
        // On ne dépose jamais un bloc dans lui-même : on remonte à la liste qui le contient.
        while (list && ignore && ignore.contains(list)) list = list.parentElement?.closest('[data-nl-list]') ?? null;
        list ??= root();

        const items = blocksIn(list).filter((block) => block !== ignore);
        let index = items.length;
        for (let i = 0; i < items.length; i++) {
            const r = items[i].getBoundingClientRect();
            if (y < r.top + r.height / 2) {
                index = i;
                break;
            }
        }

        const box = list.getBoundingClientRect();
        let edge;
        if (items.length === 0) edge = box.top + Math.min(box.height / 2, 24);
        else if (index < items.length) edge = items[index].getBoundingClientRect().top;
        else edge = items[items.length - 1].getBoundingClientRect().bottom;

        return { list, parent: list.dataset.nlParent || null, col: Number(list.dataset.nlCol || 0), index, line: { top: edge, left: box.left, width: box.width } };
    }

    function showDrop(target) {
        if (!drop) {
            drop = document.createElement('div');
            drop.className = 'nl-drop';
            document.body.append(drop);
        }
        document.querySelectorAll('.nl-drop-list').forEach((el) => el !== target.list && el.classList.remove('nl-drop-list'));
        if (target.list !== root()) target.list.classList.add('nl-drop-list');
        drop.style.top = target.line.top + window.scrollY - 2 + 'px';
        drop.style.left = target.line.left + window.scrollX + 'px';
        drop.style.width = target.line.width + 'px';
    }

    function hideDrop() {
        drop?.remove();
        drop = null;
        document.querySelectorAll('.nl-drop-list').forEach((el) => el.classList.remove('nl-drop-list'));
    }

    /* Déplacement d'un bloc existant : on le saisit par la poignée de la barre. */
    function startDrag(event) {
        event.preventDefault();
        const block = byId(selectedId);
        const grip = event.currentTarget;
        grip.setPointerCapture(event.pointerId);
        block.classList.add('nl-dragging');
        let target = null;

        const move = (e) => {
            target = dropTarget(e.clientX, e.clientY, block);
            showDrop(target);
        };
        const end = () => {
            grip.removeEventListener('pointermove', move);
            grip.removeEventListener('pointerup', end);
            grip.removeEventListener('pointercancel', end);
            block.classList.remove('nl-dragging');
            hideDrop();
            // L'index est compté sans le bloc déplacé : l'éditeur le retire d'abord, puis le replace à cet index.
            if (target) tell({ op: 'move', id: idOf(block), parent: target.parent, col: target.col, index: target.index });
        };
        grip.addEventListener('pointermove', move);
        grip.addEventListener('pointerup', end);
        grip.addEventListener('pointercancel', end);
        move(event);
    }

    /* Un bloc glissé depuis le panneau « Ajouter » (HTML5 : le panneau est dans la page parente, la newsletter dans cet iframe). */
    const isAdd = (event) => [...(event.dataTransfer?.types ?? [])].includes('text/nl-add');
    document.addEventListener('dragover', (event) => {
        if (!isAdd(event)) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
        showDrop(dropTarget(event.clientX, event.clientY));
    });
    document.addEventListener('dragleave', (event) => {
        if (isAdd(event) && !event.relatedTarget) hideDrop();
    });
    document.addEventListener('drop', (event) => {
        if (!isAdd(event)) return;
        event.preventDefault();
        const target = dropTarget(event.clientX, event.clientY);
        hideDrop();
        const [kind, key] = event.dataTransfer.getData('text/nl-add').split(':');
        tell({ op: 'drop-add', kind, key, parent: target.parent, col: target.col, index: target.index });
    });

    /* ───────── Mise à jour des lignes de la newsletter par l'éditeur ───────── */
    const ghost = (id) => {
        const row = document.createElement('tr');
        row.dataset.nlRow = '';
        row.dataset.nlGhost = '';
        const cell = row.appendChild(document.createElement('td'));
        cell.dataset.nlId = id;
        cell.textContent = 'Chargement du bloc…';

        return row;
    };
    const parseRow = (html) => {
        const holder = document.createElement('tbody');
        holder.innerHTML = html;

        return holder.firstElementChild;
    };
    const topRows = () => [...root().querySelectorAll(':scope > tbody > tr[data-nl-row]')];
    const rowId = (row) => row.querySelector(':scope > td[data-nl-id]')?.dataset.nlId;

    /**
     * Met les lignes de la newsletter dans l'état voulu. items : [{ id, html? , ghost? }] dans l'ordre. Une ligne sans html reste telle quelle
     * (ou devient un bloc « en chargement » si elle n'existe pas encore) ; les lignes absentes de la liste sont retirées.
     */
    function setRows(items) {
        const empty = root().querySelector(':scope > tbody > tr[data-nl-empty]');
        const current = new Map(topRows().map((row) => [rowId(row), row]));
        const want = [];

        items.forEach((item) => {
            let row = current.get(item.id);
            if (item.html) {
                const fresh = parseRow(item.html);
                if (row) row.replaceWith(fresh);
                row = fresh;
                bind(row);
            } else if (!row) {
                row = ghost(item.id);
            }
            want.push(row);
        });

        const wanted = new Set(want);
        current.forEach((row) => !wanted.has(row) && row.remove());

        const inPlace = topRows();
        if (inPlace.length !== want.length || want.some((row, i) => row !== inPlace[i])) want.forEach((row) => empty.before(row));
        if (empty) empty.hidden = want.length > 0;

        // Le bloc sélectionné a pu être redessiné (ou n'est pas encore arrivé) : on remet les repères.
        paint();
        placeBar();
    }

    /** Remplace les lignes de l'en-tête (et le petit blanc qui suit) par celles que le serveur vient de dessiner. */
    function setHeader(html) {
        const body = root().querySelector(':scope > tbody');
        const old = [...body.querySelectorAll(':scope > tr[data-nl-hdr]')];
        const anchor = old.at(-1)?.nextElementSibling ?? body.firstElementChild;
        const holder = document.createElement('tbody');
        holder.innerHTML = html;
        [...holder.children].forEach((row) => anchor.before(row));
        old.forEach((row) => row.remove());
        bind(body);
        paint();
        positionBar();
    }

    window.nl = {
        setRows,
        setHeader,
        select: (id) => select(id, { notify: false }),
        selectHeader: (on) => (on ? selectHeader({ notify: false }) : ((headerOn = false), paint())),
        reveal: (id) => byId(id)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }),
        focus: (id) => byId(id)?.querySelector('[data-nl-field]')?.focus(),
    };

    window.addEventListener('resize', positionBar);
    document.addEventListener('load', positionBar, true); // une image qui finit de charger déplace ce qui suit
    bind(document);
    placeBar();
    tell({ op: 'ready' });
})();
