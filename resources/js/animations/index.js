// Chargeur d'animations : GSAP, Lenis et chaque module ne sont téléchargés qu'après le premier rendu,
// et seulement si la page contient l'élément correspondant. Voir docs/ARCHITECTURE.md (section Animations).
const MODULES = [['[data-reveal]', () => import('./reveal.js').then((m) => m.initReveal())]];

export function initAnimations() {
    const start = () => {
        import('./smooth-scroll.js').then((m) => m.initSmoothScroll());
        MODULES.forEach(([selector, load]) => {
            if (document.querySelector(selector)) load();
        });
    };

    // Les animations n'ont aucune priorité face au rendu initial.
    if ('requestIdleCallback' in window) requestIdleCallback(start, { timeout: 600 });
    else setTimeout(start, 0);
}
