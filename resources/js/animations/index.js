// Chargeur d'animations : GSAP, Lenis et chaque module ne sont téléchargés qu'après le premier rendu,
// et seulement si la page contient l'élément correspondant. Voir docs/ARCHITECTURE.md (section Animations).
const MODULES = [
    ['#site-menu', () => import('./menu.js').then((m) => m.initMenu())],
    ['[data-reveal]', () => import('./reveal.js').then((m) => m.initReveal())],
    ['[data-hero]', () => import('./hero.js').then((m) => m.initHero())],
    ['[data-counter]', () => import('./counters.js').then((m) => m.initCounters())],
    ['[data-parallax]', () => import('./parallax.js').then((m) => m.initParallax())],
    ['[data-library]', () => import('./library.js').then((m) => m.initLibrary())],
    ['[data-cafeteria]', () => import('./cafeteria.js').then((m) => m.initCafeteria())],
    ['[data-dormitories]', () => import('./dormitories.js').then((m) => m.initDormitories())],
    ['[data-admissions]', () => import('./admissions.js').then((m) => m.initAdmissions())],
    ['[data-fees]', () => import('./fees.js').then((m) => m.initFees())],
    ['[data-research]', () => import('./research.js').then((m) => m.initResearch())],
    ['[data-support]', () => import('./support.js').then((m) => m.initSupport())],
    ['[data-timetables]', () => import('./timetables.js').then((m) => m.initTimetables())],
    ['[data-gallery]', () => import('./gallery.js').then((m) => m.initGallery())],
    ['[data-events]', () => import('./events.js').then((m) => m.initEvents())],
];

/** Connexion lente ou « économiseur de données » : aucune animation, aucun téléchargement de GSAP (~50 Ko). */
function saveDataEnabled() {
    const connection = navigator.connection;

    return Boolean(connection?.saveData) || ['slow-2g', '2g'].includes(connection?.effectiveType);
}

/** Lève tous les états initiaux d'animation : le contenu s'affiche tel quel, sans mouvement. */
function showEverything() {
    document.documentElement.classList.add('anim-ready', 'save-data', 'lib-ready', 'caf-ready', 'dor-ready', 'adm-ready', 'fee-ready', 'res-ready', 'sup-ready', 'tt-ready', 'gal-ready', 'evt-ready');
    document.querySelectorAll('[data-reveal]').forEach((el) => el.setAttribute('data-revealed', ''));
    document.querySelectorAll('[data-hero-title]').forEach((el) => el.setAttribute('data-split', ''));
    document.querySelectorAll('[data-hero]').forEach((el) => el.setAttribute('data-hero-started', ''));
}

export function initAnimations() {
    if (saveDataEnabled()) {
        showEverything();

        return;
    }

    const start = () => {
        import('./smooth-scroll.js').then((m) => m.initSmoothScroll());
        MODULES.forEach(([selector, load]) => {
            if (document.querySelector(selector)) load();
        });
    };

    // Le héros prime : on ne le fait pas attendre une période d'inactivité du navigateur.
    if (document.querySelector('[data-hero], [data-library], [data-cafeteria], [data-dormitories], [data-admissions], [data-fees], [data-research], [data-support], [data-timetables], [data-gallery], [data-events]')) start();
    else if ('requestIdleCallback' in window) requestIdleCallback(start, { timeout: 600 });
    else setTimeout(start, 0);
}
