// Phrase du héros en plusieurs langues : chaque version reste affichée 5 secondes, puis défile de bas en haut pour laisser la
// place à la suivante (français → anglais → malgache → …). Mouvement réduit : la première version reste affichée, sans défilement.
const STAY = 5000;
const MOVE = 800;

export function initHeroTicker() {
    const ticker = document.querySelector('[data-hero-ticker]');
    if (!ticker || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const items = [...ticker.children];
    if (items.length < 2) return;

    let index = 0;

    setInterval(() => {
        if (document.hidden) return;
        const current = items[index];
        index = (index + 1) % items.length;
        const next = items[index];

        current.dataset.state = 'leaving';
        current.setAttribute('aria-hidden', 'true');
        next.dataset.state = 'current';
        next.removeAttribute('aria-hidden');

        // Une fois sortie par le haut, l'ancienne version revient sous la zone, sans animation, prête pour le tour suivant.
        setTimeout(() => {
            current.classList.add('is-reset');
            delete current.dataset.state;
            void current.offsetHeight;
            current.classList.remove('is-reset');
        }, MOVE + 50);
    }, STAY);
}
