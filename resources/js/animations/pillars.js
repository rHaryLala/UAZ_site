// « Spiritus · Manus · Mens » : le triangle se dessine au fil du défilement (scrub), Madagascar et les trois mots
// apparaissent tour à tour. Mouvement réduit : le schéma complet est affiché directement.
import { gsap, MOTION_OK, NO_MOTION } from './gsap.js';

export function initPillars() {
    const section = document.querySelector('[data-pillars]');
    if (!section) return;

    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, () => {
        const triangle = section.querySelector('[data-pillars-triangle]');
        const island = section.querySelector('[data-pillars-island]');
        const words = section.querySelectorAll('[data-pillars-word]');

        gsap.set(triangle, { strokeDashoffset: 1 });
        gsap.set(island, { autoAlpha: 0, scale: 0.9 });
        gsap.set(words, { autoAlpha: 0, y: 14 });
        section.setAttribute('data-pillars-started', '');

        gsap.timeline({
            scrollTrigger: {
                trigger: triangle.closest('svg'),
                start: 'top 78%',
                end: 'bottom 38%',
                scrub: 0.7,
            },
        })
            .to(triangle, { strokeDashoffset: 0, ease: 'none', duration: 3 }, 0)
            .to(words, { autoAlpha: 1, y: 0, stagger: 0.55, duration: 0.7 }, 0.3)
            .to(island, { autoAlpha: 1, scale: 1, duration: 1.2 }, 1.4);
    });

    mm.add(NO_MOTION, () => section.setAttribute('data-pillars-started', ''));
}
