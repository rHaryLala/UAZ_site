// Éditeur visuel : script qui tourne DANS la page affichée (iframe). Il dessine les repères de blocs, rend les textes éditables en place
// et prévient l'éditeur (window.parent) de ce qui change. L'état de la page (blocs, titre, résumé) appartient à l'éditeur : ce script ne garde rien.
(() => {
    const root = document.getElementById('ve-root');
    const SKIP = 'script, style, svg, .sr-only, [aria-hidden="true"], [data-ve-fig]';
    const norm = (s) => s.replace(/[  ]/g, ' ').replace(/\s+/g, ' ').trim();
    let blockEls = [];
    let selected = -1;
    let chrome = null;
    let toolbar = null;
    let observer = null;

    const VE = { host: null, mount, select, place };
    window.VE = VE;

    const el = (tag, className, attrs = {}) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        Object.entries(attrs).forEach(([k, v]) => node.setAttribute(k, v));

        return node;
    };

    /* ───────── Montage ───────── */
    function mount(html, select = -1) {
        const scroll = window.scrollY;
        root.innerHTML = html;
        blockEls = [...root.querySelectorAll('[data-ve-block]')];

        bindHero();
        blockEls.forEach((block, i) => bindBlock(block, i));

        if (!blockEls.length) {
            const empty = el('div', 've-empty');
            empty.innerHTML = '<p>Cette page est vide.</p>';
            const add = el('button', 've-empty__btn', { type: 'button' });
            add.textContent = '+ Ajouter un premier bloc';
            add.addEventListener('click', () => VE.host.action('addAfter', -1));
            empty.append(add);
            root.append(empty);
        }

        window.scrollTo(0, scroll);
        VE.select(select, false);
    }

    /** Textes de la page elle-même (titre et résumé de l'en-tête). */
    function bindHero() {
        const hero = root.querySelector('[data-ve-hero]');
        if (!hero) return;
        const page = VE.host.getPage();
        const strings = [
            { path: ['title'], key: norm(page.title ?? '') },
            { path: ['summary'], key: norm(page.summary ?? '') },
        ].filter((s) => s.key);

        bindTexts(hero, strings, (path, value) => VE.host.pageText(path[0], value));
        hero.addEventListener('click', () => VE.host.select(-1));
    }

    function bindBlock(block, index) {
        const data = VE.host.getBlock(index);
        const definition = VE.host.types[data.type];
        const strings = [];
        collect(definition.fields, data.data, [], strings);

        // Texte riche : tout le contenu est éditable avec une barre de mise en forme.
        if (data.type === 'rich_text') {
            const prose = block.querySelector('.prose');
            if (prose) {
                prose.setAttribute('contenteditable', 'true');
                prose.setAttribute('data-ve-rich', '');
                prose.addEventListener('input', () => VE.host.rich(index, ['content'], serialize(prose)));
                prose.addEventListener('keydown', (e) => richKeys(e));
                prose.addEventListener('paste', (e) => pasteClean(e, true));
            }
        }

        bindTexts(block, strings, (path, value) => VE.host.text(index, path, value));
        block.addEventListener('click', () => VE.host.select(index));
    }

    function collect(fields, data, path, out) {
        for (const f of fields) {
            const v = data?.[f.name];
            if ((f.type === 'text' || f.type === 'textarea') && typeof v === 'string' && v.trim()) {
                out.push({ path: [...path, f.name], key: norm(v) });
            } else if (f.type === 'repeater' && Array.isArray(v)) {
                v.forEach((item, i) => collect(f.fields, item, [...path, f.name, i], out));
            }
        }
    }

    /** Rend éditable chaque texte du bloc qui correspond exactement à un champ texte de ses données (aucune modification des vues du site). */
    function bindTexts(container, strings, onChange) {
        const used = new Set();
        const nodes = [];
        const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, {
            acceptNode: (n) => (!n.nodeValue.trim() || n.parentElement.closest(SKIP) || n.parentElement.closest('[data-ve-rich]') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT),
        });
        while (walker.nextNode()) nodes.push(walker.currentNode);

        for (const node of nodes) {
            const key = norm(node.nodeValue);
            const index = strings.findIndex((s, i) => !used.has(i) && s.key === key);
            if (index < 0) continue;
            used.add(index);

            const lead = node.nodeValue.match(/^\s*/)[0];
            const trail = node.nodeValue.match(/\s*$/)[0];
            const span = el('span', 've-text', { contenteditable: 'true', spellcheck: 'true' });
            span.textContent = node.nodeValue.trim();
            node.replaceWith(document.createTextNode(lead), span, document.createTextNode(trail));

            span.addEventListener('input', () => onChange(strings[index].path, norm(span.textContent)));
            span.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    span.blur();
                }
            });
            span.addEventListener('paste', (e) => pasteClean(e, false));
        }
    }

    function pasteClean(event, multiline) {
        event.preventDefault();
        const text = (event.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, multiline ? text : text.replace(/\s+/g, ' '));
    }

    /** Reconstitue le HTML enregistré : les images du texte redeviennent leur balise d'origine, sans aucun repère d'édition. */
    function serialize(prose) {
        const clone = prose.cloneNode(true);
        clone.querySelectorAll('[data-ve-fig]').forEach((wrapper) => {
            const figure = document.createElement('figure');
            figure.setAttribute('data-media', wrapper.dataset.veFig);
            if (wrapper.dataset.veCap) {
                const caption = document.createElement('figcaption');
                caption.textContent = wrapper.dataset.veCap;
                figure.append(caption);
            }
            wrapper.replaceWith(figure);
        });
        clone.querySelectorAll('[contenteditable]').forEach((n) => n.removeAttribute('contenteditable'));

        return clone.innerHTML;
    }

    /* ───────── Sélection et barre d'outils du bloc ───────── */
    function select(index, notify = true) {
        selected = index;
        blockEls.forEach((b, i) => b.classList.toggle('is-selected', i === index));
        buildChrome();
        place();
        if (notify) VE.host.select(index, false);
    }

    function buildChrome() {
        chrome?.remove();
        observer?.disconnect();
        if (selected < 0 || !blockEls[selected]) {
            chrome = null;

            return;
        }
        const data = VE.host.getBlock(selected);
        const label = VE.host.types[data.type].label;

        chrome = el('div', 've-chrome');
        chrome.append(el('span', 've-chrome__label'));
        chrome.firstChild.textContent = label;

        const btn = (icon, title, action, extra = '') => {
            const b = el('button', `ve-chrome__btn ${extra}`, { type: 'button', title, 'aria-label': title });
            b.innerHTML = icon;
            b.addEventListener('mousedown', (e) => e.preventDefault());
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                VE.host.action(action, selected);
            });

            return b;
        };
        const svg = (p) => `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${p}</svg>`;
        chrome.append(
            btn(svg('<path d="M12 19V5M5 12l7-7 7 7"/>'), 'Monter', 'up'),
            btn(svg('<path d="M12 5v14M5 12l7 7 7-7"/>'), 'Descendre', 'down'),
            btn(svg('<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/>'), 'Dupliquer', 'duplicate'),
            btn(svg('<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12h10l1-12M9 7V4h6v3"/>'), 'Supprimer', 'delete', 'is-danger'),
        );

        const add = (cls, title, action) => {
            const b = el('button', `ve-add ${cls}`, { type: 'button', title, 'aria-label': title });
            b.innerHTML = svg('<path d="M12 5v14M5 12h14"/>');
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                VE.host.action(action, selected);
            });

            return b;
        };
        chrome.append(add('ve-add--top', 'Ajouter un bloc avant', 'addBefore'), add('ve-add--bottom', 'Ajouter un bloc après', 'addAfter'));
        document.body.append(chrome);

        observer = new ResizeObserver(place);
        observer.observe(blockEls[selected]);
    }

    function place() {
        if (!chrome || !blockEls[selected]) return;
        const r = blockEls[selected].getBoundingClientRect();
        Object.assign(chrome.style, { top: `${r.top + window.scrollY}px`, left: `${r.left + window.scrollX}px`, width: `${r.width}px`, height: `${r.height}px` });
    }

    /* ───────── Barre de mise en forme du texte riche ───────── */
    function buildToolbar() {
        toolbar = el('div', 've-toolbar');
        toolbar.hidden = true;
        const tools = [
            ['B', 'Gras', () => document.execCommand('bold')],
            ['I', 'Italique', () => document.execCommand('italic')],
            ['H2', 'Titre', () => document.execCommand('formatBlock', false, 'h2')],
            ['H3', 'Sous-titre', () => document.execCommand('formatBlock', false, 'h3')],
            ['¶', 'Paragraphe', () => document.execCommand('formatBlock', false, 'p')],
            ['• Liste', 'Liste à puces', () => document.execCommand('insertUnorderedList')],
            ['1. Liste', 'Liste numérotée', () => document.execCommand('insertOrderedList')],
            ['« »', 'Citation', () => document.execCommand('formatBlock', false, 'blockquote')],
            ['Lien', 'Insérer un lien', link],
            ['✕ Lien', 'Retirer le lien', () => document.execCommand('unlink')],
        ];
        tools.forEach(([text, title, run]) => {
            const b = el('button', 've-toolbar__btn', { type: 'button', title, 'aria-label': title });
            b.textContent = text;
            b.addEventListener('mousedown', (e) => e.preventDefault());
            b.addEventListener('click', () => {
                run();
                const prose = document.activeElement?.closest?.('[data-ve-rich]');
                prose?.dispatchEvent(new Event('input', { bubbles: true }));
            });
            toolbar.append(b);
        });
        document.body.append(toolbar);
    }

    function link() {
        const url = window.prompt('Adresse du lien (https://…, /chemin du site, mailto:…)', 'https://');
        if (url && /^(\/|https?:\/\/|mailto:|tel:)/i.test(url.trim())) document.execCommand('createLink', false, url.trim());
    }

    function richKeys(event) {
        if ((event.ctrlKey || event.metaKey) && ['b', 'i'].includes(event.key.toLowerCase())) return; // gras / italique natifs
    }

    document.addEventListener('selectionchange', () => {
        if (!toolbar) return;
        const sel = document.getSelection();
        const prose = sel?.anchorNode && (sel.anchorNode.nodeType === 1 ? sel.anchorNode : sel.anchorNode.parentElement)?.closest?.('[data-ve-rich]');
        if (!prose || sel.isCollapsed) {
            toolbar.hidden = true;

            return;
        }
        const rect = sel.getRangeAt(0).getBoundingClientRect();
        toolbar.hidden = false;
        const width = toolbar.offsetWidth;
        toolbar.style.top = `${Math.max(8, rect.top + window.scrollY - toolbar.offsetHeight - 10)}px`;
        toolbar.style.left = `${Math.min(Math.max(8, rect.left + window.scrollX + rect.width / 2 - width / 2), document.documentElement.clientWidth - width - 8)}px`;
    });

    /* ───────── Divers ───────── */
    // Aucun lien ne doit faire quitter l'éditeur.
    document.addEventListener('click', (e) => e.target.closest('a')?.matches('a') && e.preventDefault(), true);
    document.addEventListener('submit', (e) => e.preventDefault(), true);
    window.addEventListener('resize', place);
    window.addEventListener('load', place);

    // Raccourcis (hors zone de saisie) transmis à l'éditeur : annuler, rétablir, enregistrer.
    document.addEventListener('keydown', (e) => {
        if (e.target.closest?.('[contenteditable="true"]')) return;
        if (VE.host?.key(e)) e.preventDefault();
    });

    buildToolbar();
})();
