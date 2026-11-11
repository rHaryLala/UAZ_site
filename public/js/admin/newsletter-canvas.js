// Plan de travail de l'éditeur de lettres (dans l'iframe) : on modifie les textes sur place, on clique un bloc pour le sélectionner, une barre
// flottante permet de le déplacer (glisser), le monter, le descendre, le dupliquer, le supprimer. Ce script ne garde aucun état : il signale ce
// qui se passe à l'éditeur (window.parent), qui tient l'état de la lettre, l'enregistre et redessine le plan de travail.
(() => {
    const LABELS = { heading: 'Titre', text: 'Texte', image: 'Image', button: 'Bouton', quote: 'Citation', feature: 'Image et texte', news: 'Actualités', divider: 'Séparateur', spacer: 'Espace' };
    const tell = (message) => window.parent.postMessage({ nl: true, ...message }, '*');
    const blocks = () => [...document.querySelectorAll('[data-nl-block]')];
    const indexOf = (el) => Number(el.dataset.nlBlock);

    let selected = blocks().find((el) => el.classList.contains('nl-selected')) ?? null;
    let bar = null;
    let drop = null;

    /* ───────── Texte modifiable sur place ───────── */
    document.querySelectorAll('[data-nl-field]').forEach((el) => {
        const block = el.closest('[data-nl-block]');
        const rich = el.dataset.nlField === 'html';
        let timer = 0;

        // « plaintext-only » : pas de mise en forme collée dans un titre ; les navigateurs plus anciens retombent sur « true ».
        el.contentEditable = rich ? 'true' : 'plaintext-only';
        if (el.contentEditable !== 'true' && el.contentEditable !== 'plaintext-only') el.contentEditable = 'true';
        el.spellcheck = true;
        el.lang = 'fr';

        const report = () => {
            clearTimeout(timer);
            const value = rich ? el.innerHTML : el.textContent.replace(/\s+/g, ' ');
            tell({ op: 'edit', index: indexOf(block), field: el.dataset.nlField, value });
        };

        el.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(report, 250);
        });
        el.addEventListener('blur', () => {
            report();
            tell({ op: 'commit' });
        });
        el.addEventListener('focus', () => select(block));
        el.addEventListener('paste', (event) => {
            event.preventDefault();
            document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        });
        el.addEventListener('keydown', (event) => {
            if (!rich && event.key === 'Enter') {
                event.preventDefault();
                el.blur();
            }
            if (event.key === 'Escape') el.blur();
        });
    });

    /* ───────── Sélection ───────── */
    function select(block) {
        if (selected === block) return;
        selected?.classList.remove('nl-selected');
        selected = block;
        selected?.classList.add('nl-selected');
        placeBar();
        tell({ op: 'select', index: block ? indexOf(block) : -1 });
    }

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (link) event.preventDefault(); // on ne quitte jamais le plan de travail par un lien

        const block = event.target.closest('[data-nl-block]');
        if (event.target.closest('.nl-bar')) return;
        select(block);
    });

    document.addEventListener('dblclick', (event) => {
        const block = event.target.closest('[data-nl-block]');
        if (block && ['image', 'feature'].includes(block.dataset.nlType) && !event.target.closest('[data-nl-field]')) {
            tell({ op: 'media', index: indexOf(block) });
        }
    });

    document.addEventListener('keydown', (event) => {
        const editing = event.target.closest?.('[contenteditable]');
        const mod = event.ctrlKey || event.metaKey;

        if (!editing && mod && event.key.toLowerCase() === 'z') {
            event.preventDefault();
            tell({ op: event.shiftKey ? 'redo' : 'undo' });
        } else if (!editing && mod && event.key.toLowerCase() === 'y') {
            event.preventDefault();
            tell({ op: 'redo' });
        } else if (!editing && selected && (event.key === 'Delete' || event.key === 'Backspace')) {
            event.preventDefault();
            tell({ op: 'del', index: indexOf(selected) });
        } else if (!editing && event.key === 'Escape') {
            select(null);
        }
    });

    /* ───────── Barre flottante ───────── */
    const button = (label, op, title, extra = {}) => {
        const node = document.createElement('button');
        node.type = 'button';
        node.innerHTML = label;
        node.title = title;
        node.setAttribute('aria-label', title);
        node.dataset.op = op;
        Object.assign(node.dataset, extra);

        return node;
    };

    function placeBar() {
        bar?.remove();
        bar = null;
        if (!selected) return;

        const type = selected.dataset.nlType;
        const index = indexOf(selected);
        const total = blocks().length;
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
        if (selected.querySelector('[data-nl-field="html"]')) {
            const sep = document.createElement('span');
            sep.className = 'nl-bar__sep';
            bar.append(sep, button('<b>G</b>', 'bold', 'Gras'), button('<i>I</i>', 'italic', 'Italique'), button('🔗', 'link', 'Ajouter un lien'), button('•&nbsp;≡', 'list', 'Liste à puces'));
        }

        const sep = document.createElement('span');
        sep.className = 'nl-bar__sep';
        const up = button('↑', 'up', 'Monter');
        const down = button('↓', 'down', 'Descendre');
        up.disabled = index === 0;
        down.disabled = index === total - 1;
        bar.append(sep, up, down, button('⧉', 'dup', 'Dupliquer'), button('＋', 'add', 'Ajouter un bloc dessous'), button('🗑', 'del', 'Supprimer le bloc'));

        // Les boutons ne doivent pas faire perdre la sélection du texte.
        bar.addEventListener('mousedown', (event) => event.preventDefault());
        bar.addEventListener('click', onBar);
        grip.addEventListener('pointerdown', startDrag);
        document.body.append(bar);
        positionBar();
    }

    function positionBar() {
        if (!bar || !selected) return;
        const rect = selected.getBoundingClientRect();
        const top = rect.top + window.scrollY;
        const height = bar.offsetHeight;
        bar.style.top = (top - height - 6 < window.scrollY + 4 ? top + 6 : top - height - 6) + 'px';
        bar.style.left = Math.max(6, Math.min(rect.left + window.scrollX + 8, document.documentElement.clientWidth - bar.offsetWidth - 6)) + 'px';
    }

    function onBar(event) {
        const op = event.target.closest('button')?.dataset.op;
        if (!op || !selected) return;
        const index = indexOf(selected);

        if (op === 'bold' || op === 'italic') {
            document.execCommand(op);
        } else if (op === 'list') {
            document.execCommand('insertUnorderedList');
        } else if (op === 'link') {
            const url = window.prompt('Adresse du lien (https://…)');
            if (url) document.execCommand('createLink', false, url);
        } else if (['up', 'down', 'dup', 'del', 'add'].includes(op)) {
            tell({ op, index });
        }
    }

    /* ───────── Déplacement par glisser ───────── */
    function startDrag(event) {
        event.preventDefault();
        const from = indexOf(selected);
        const grip = event.currentTarget;
        grip.setPointerCapture(event.pointerId);
        selected.classList.add('nl-dragging');
        drop = document.createElement('div');
        drop.className = 'nl-drop';
        document.body.append(drop);
        let target = from;

        const move = (e) => {
            const y = e.clientY + window.scrollY;
            const list = blocks();
            target = list.length;
            for (let i = 0; i < list.length; i++) {
                const r = list[i].getBoundingClientRect();
                if (y < r.top + window.scrollY + r.height / 2) {
                    target = i;
                    break;
                }
            }
            const edge = target < list.length ? list[target].getBoundingClientRect().top + window.scrollY : list.at(-1).getBoundingClientRect().bottom + window.scrollY;
            drop.style.top = edge - 2 + 'px';
        };
        const end = () => {
            grip.removeEventListener('pointermove', move);
            grip.removeEventListener('pointerup', end);
            grip.removeEventListener('pointercancel', end);
            selected?.classList.remove('nl-dragging');
            drop?.remove();
            drop = null;
            // L'index d'arrivée est donné avant retrait du bloc : on corrige quand il descend.
            const to = target > from ? target - 1 : target;
            if (to !== from) tell({ op: 'move', from, to });
        };
        grip.addEventListener('pointermove', move);
        grip.addEventListener('pointerup', end);
        grip.addEventListener('pointercancel', end);
        move(event);
    }

    window.addEventListener('resize', positionBar);
    placeBar();
    tell({ op: 'ready' });
})();
