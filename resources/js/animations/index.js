// Chargeur d'animations : GSAP, Lenis et chaque module ne sont téléchargés qu'après le premier rendu,
// et seulement si la page contient l'élément correspondant. Voir docs/ARCHITECTURE.md (section Animations).
const MODULES = [
    ['[data-reveal]', () => import('./reveal.js').then((m) => m.initReveal())],
    ['[data-hero]', () => import('./hero.js').then((m) => m.initHero())],
    ['[data-counter]', () => import('./counters.js').then((m) => m.initCounters())],
    ['[data-pillars]', () => import('./pillars.js').then((m) => m.initPillars())],
    ['[data-parallax]', () => import('./parallax.js').then((m) => m.initParallax())],
];

export function initAnimations() {
    const start = () => {
        import('./smooth-scroll.js').then((m) => m.initSmoothScroll());
        MODULES.forEach(([selector, load]) => {
            if (document.querySelector(selector)) load();
        });
    };

    // Le héros prime : on ne le fait pas attendre une période d'inactivité du navigateur.
    if (document.querySelector('[data-hero]')) start();
    else if ('requestIdleCallback' in window) requestIdleCallback(start, { timeout: 600 });
    else setTimeout(start, 0);
}
