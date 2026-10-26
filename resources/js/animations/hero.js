// Héros de l'accueil : titre révélé mot par mot (SplitText), triangle du sceau qui se dessine, Madagascar qui apparaît,
// parallaxe douce de l'image. Seulement transform / opacity / stroke-dashoffset. Mouvement réduit : état final direct.
import { gsap, SplitText, MOTION_OK, NO_MOTION } from './gsap.js';

export function initHero() {
    const hero = document.querySelector('[data-hero]');
    if (!hero) return;

    const title = hero.querySelector('[data-hero-title]');
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, () => {
        const q = (selector) => hero.querySelectorAll(selector);
        const split = SplitText.create(title, { type: 'words', mask: 'words', aria: 'auto' });

        // Les états initiaux (masqués en CSS tant que JS n'a pas démarré) sont repris ici par GSAP.
        const triangle = q('[data-hero-triangle]');
        gsap.set(triangle, { strokeDashoffset: 1 });
        gsap.set(q('[data-hero-fill], [data-hero-island]'), { autoAlpha: 0 });
        gsap.set(q('[data-hero-word]'), { autoAlpha: 0, y: 10 });
        hero.setAttribute('data-hero-started', '');
        title.setAttribute('data-split', '');

        const tl = gsap.timeline({ defaults: { ease: 'expo.out' } });
        tl.from(split.words, { yPercent: 118, duration: 1.25, stagger: 0.07 }, 0.1)
            .to(triangle, { strokeDashoffset: 0, duration: 2.4, ease: 'power2.inOut' }, 0.15)
            .to(q('[data-hero-fill]'), { autoAlpha: 1, duration: 1.6, ease: 'power1.out' }, 1.0)
            .fromTo(
                q('[data-hero-island]'),
                { scale: 0.94, y: 24 },
                { autoAlpha: 0.9, scale: 1, y: 0, duration: 1.8 },
                0.9,
            )
            .to(q('[data-hero-word]'), { autoAlpha: 1, y: 0, duration: 1, stagger: 0.25 }, 1.3);

        // Parallaxe : l'image descend moins vite que la page, le motif remonte légèrement.
        const trigger = { trigger: hero, start: 'top top', end: 'bottom top', scrub: true };
        const bg = hero.querySelector('[data-hero-bg] img');
        if (bg) gsap.to(bg, { yPercent: 12, ease: 'none', scrollTrigger: trigger });
        gsap.to(hero.querySelector('[data-hero-graphic]'), {
            yPercent: -7,
            ease: 'none',
            scrollTrigger: trigger,
        });

        return () => split.revert();
    });

    mm.add(NO_MOTION, () => {
        hero.setAttribute('data-hero-started', '');
        title.setAttribute('data-split', '');
    });
}
