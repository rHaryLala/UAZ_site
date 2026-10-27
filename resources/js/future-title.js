// Titre « Votre futur commence ici. » en trois langues (français, anglais, malgache) : chaque version reste 8 secondes, puis un effet de
// glitch (tremblement, tranches décalées, double contour or/bleu) la remplace par la suivante. Mouvement réduit : le français seul.
const STAY = 8000;
const OUT = 420;
const IN = 520;

export function initFutureTitle() {
    const title = document.querySelector('[data-future-title]');
    if (!title || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const items = [...title.children];
    if (items.length < 2) return;

    let index = 0;

    setInterval(() => {
        if (document.hidden) return;
        const current = items[index];
        index = (index + 1) % items.length;
        const next = items[index];

        current.dataset.state = 'out';
        current.setAttribute('aria-hidden', 'true');

        setTimeout(() => {
            delete current.dataset.state;
            next.dataset.state = 'in';
            next.removeAttribute('aria-hidden');
            setTimeout(() => (next.dataset.state = 'current'), IN);
        }, OUT);
    }, STAY);
}
