// Yearbook : livre numérique à feuilleter (page-flip). Les pages sont des images WebP générées à partir du PDF ; elles ne se
// chargent qu'à l'approche de la page courante. Commandes : pages (clic / glisser), flèches du clavier, boutons, curseur,
// miniatures, plein écran. Sans JavaScript, la page propose le PDF d'origine.
const AHEAD = 5;
const BEHIND = 3;

export async function initYearbook() {
    const root = document.querySelector('[data-yearbook]');
    if (!root) return;

    const stage = root.querySelector('[data-yb-stage]');
    const book = root.querySelector('[data-yb-book]');
    const pages = [...book.querySelectorAll('[data-yb-page]')];
    const total = pages.length;
    const label = root.querySelector('[data-yb-label]');
    const slider = root.querySelector('[data-yb-slider]');
    const prev = root.querySelector('[data-yb-prev]');
    const next = root.querySelector('[data-yb-next]');
    const thumbs = root.querySelector('[data-yb-thumbs]');
    const thumbsToggle = root.querySelector('[data-yb-thumbs-toggle]');
    const fullscreen = root.querySelector('[data-yb-fullscreen]');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // page-flip injecte sa propre balise <style> : on lui donne le nonce de la page (CSP stricte), le temps de l'initialisation.
    const nonce = document.querySelector('script[nonce]')?.nonce || document.querySelector('style[nonce]')?.nonce;
    const createElement = document.createElement;
    document.createElement = function (tag, ...rest) {
        const element = createElement.call(this, tag, ...rest);
        if (nonce && String(tag).toLowerCase() === 'style') element.setAttribute('nonce', nonce);

        return element;
    };

    // Import dynamique : la bibliothèque crée sa balise <style> dès son chargement, donc après le correctif ci-dessus.
    const { PageFlip } = await import('page-flip');
    document.createElement = createElement;

    const flip = new PageFlip(book, {
        width: 595,
        height: 822,
        size: 'stretch',
        minWidth: 260,
        maxWidth: 760,
        minHeight: 360,
        maxHeight: 1050,
        drawShadow: true,
        maxShadowOpacity: 0.55,
        flippingTime: reduced ? 1 : 900,
        showCover: true,
        usePortrait: true,
        mobileScrollSupport: true,
        swipeDistance: 24,
        clickEventForward: false,
        startPage: 0,
    });

    flip.loadFromHTML(pages);

    // Chargement paresseux : les images proches de la page courante.
    const ensure = (index) => {
        for (let i = Math.max(0, index - BEHIND); i <= Math.min(total - 1, index + AHEAD); i++) {
            const img = pages[i].querySelector('img[data-src]');
            if (img) {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            }
        }
    };

    const describe = (index) => {
        // Livre ouvert (2 pages visibles) : « Pages 4–5 / 44 » ; couverture et page seule : « Page 1 / 44 ».
        const portrait = flip.getOrientation() === 'portrait';
        const last = total - 1;
        if (portrait || index === 0 || index === last) return `Page ${index + 1} / ${total}`;
        const left = index % 2 === 1 ? index : index - 1;

        return `Pages ${left + 1}–${Math.min(left + 2, total)} / ${total}`;
    };

    const sync = (index) => {
        ensure(index);
        label.textContent = describe(index);
        slider.value = String(index + 1);
        slider.setAttribute('aria-valuetext', label.textContent);
        prev.disabled = index <= 0;
        next.disabled = index >= total - 1;
        thumbs?.querySelectorAll('[data-yb-goto]').forEach((button) => {
            const current = Number(button.dataset.ybGoto) === index;
            button.toggleAttribute('data-current', current);
            if (current) button.setAttribute('aria-current', 'true');
            else button.removeAttribute('aria-current');
        });
        root.toggleAttribute('data-landscape', flip.getOrientation() === 'landscape');
        root.toggleAttribute('data-at-start', index === 0);
        root.toggleAttribute('data-at-end', index >= total - 1);
    };

    flip.on('flip', (event) => sync(event.data));
    flip.on('init', () => sync(0));
    flip.on('changeOrientation', () => sync(flip.getCurrentPageIndex()));
    ensure(0);
    sync(0);

    prev.addEventListener('click', () => flip.flipPrev());
    next.addEventListener('click', () => flip.flipNext());
    slider.max = String(total);
    slider.addEventListener('input', () => {
        const target = Number(slider.value) - 1;
        label.textContent = `Page ${target + 1} / ${total}`;
    });
    slider.addEventListener('change', () => {
        flip.turnToPage(Number(slider.value) - 1);
        sync(flip.getCurrentPageIndex());
    });

    // Miniatures : accès direct à une page.
    thumbsToggle?.addEventListener('click', () => {
        const open = thumbs.hasAttribute('hidden');
        thumbs.toggleAttribute('hidden', !open);
        thumbsToggle.setAttribute('aria-expanded', String(open));
        if (open) {
            thumbs.querySelectorAll('img[data-src]').forEach((img) => {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            });
            thumbs.querySelector('[data-current]')?.scrollIntoView({ block: 'nearest', inline: 'center' });
        }
    });
    thumbs?.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-yb-goto]') : null;
        if (!button) return;
        flip.turnToPage(Number(button.dataset.ybGoto));
        sync(flip.getCurrentPageIndex());
        stage.scrollIntoView({ block: 'nearest', behavior: reduced ? 'auto' : 'smooth' });
    });

    // Clavier : flèches ; Début / Fin.
    window.addEventListener('keydown', (event) => {
        if (event.target instanceof HTMLElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)) return;
        if (event.key === 'ArrowRight') flip.flipNext();
        else if (event.key === 'ArrowLeft') flip.flipPrev();
        else if (event.key === 'Home') flip.turnToPage(0);
        else if (event.key === 'End') flip.turnToPage(total - 1);
        else return;
        if (event.key === 'Home' || event.key === 'End') sync(flip.getCurrentPageIndex());
    });

    // Plein écran (le livre se redimensionne tout seul).
    if (fullscreen && stage.requestFullscreen) {
        fullscreen.addEventListener('click', () => (document.fullscreenElement ? document.exitFullscreen() : stage.requestFullscreen()));
        document.addEventListener('fullscreenchange', () => {
            fullscreen.setAttribute('aria-pressed', String(Boolean(document.fullscreenElement)));
            setTimeout(() => flip.update(), 120);
        });
    } else {
        fullscreen?.remove();
    }

    root.setAttribute('data-ready', '');
}
