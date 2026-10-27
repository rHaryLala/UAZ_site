// Héros de l'accueil : titre révélé mot par mot (SplitText) et parallaxe douce de la photo.
// Seulement transform / opacity. Mouvement réduit : état final direct.
import { gsap, SplitText, MOTION_OK, NO_MOTION } from './gsap.js';

export function initHero() {
    const hero = document.querySelector('[data-hero]');
    if (!hero) return;

    const title = hero.querySelector('[data-hero-title]');
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, () => {
        const split = SplitText.create(title, { type: 'words', mask: 'words', aria: 'auto' });

        hero.setAttribute('data-hero-started', '');
        title.setAttribute('data-split', '');

        gsap.from(split.words, { yPercent: 118, duration: 1.25, stagger: 0.07, ease: 'expo.out', delay: 0.1 });

        // Parallaxe : la photo descend moins vite que la page.
        const bg = hero.querySelector('[data-hero-bg] img');
        if (bg) {
            gsap.to(bg, { yPercent: 12, ease: 'none', scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom top', scrub: true } });
        }

        return () => split.revert();
    });

    mm.add(NO_MOTION, () => {
        hero.setAttribute('data-hero-started', '');
        title.setAttribute('data-split', '');
    });
}
