// Apparition douce des éléments [data-reveal] au défilement (opacité + translation uniquement).
// Variantes : data-reveal="up" (défaut) | "fade" | "left" | "right" ; data-reveal-delay="0.15".
// Le contenu est déjà dans le HTML : sans JS ou avec « réduire les animations », tout reste visible.
import { gsap, ScrollTrigger, MOTION_OK, NO_MOTION } from './gsap.js';

const FROM = {
    up: { y: 28 },
    fade: {},
    left: { x: -32 },
    right: { x: 32 },
};

export function initReveal() {
    const items = gsap.utils.toArray('[data-reveal]');
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, () => {
        document.documentElement.classList.add('anim-ready');

        ScrollTrigger.batch(items, {
            start: 'top 88%',
            once: true,
            onEnter: (batch) => {
                batch.forEach((el, i) => {
                    const variant = FROM[el.dataset.reveal] ?? FROM.up;
                    const delay = parseFloat(el.dataset.revealDelay ?? '0') + i * 0.08;
                    gsap.fromTo(
                        el,
                        { autoAlpha: 0, ...variant },
                        {
                            autoAlpha: 1,
                            x: 0,
                            y: 0,
                            duration: 0.9,
                            delay,
                            ease: 'expo.out',
                            clearProps: 'transform,opacity,visibility',
                            onStart: () => el.setAttribute('data-revealed', ''),
                        },
                    );
                });
            },
        });

        return () => items.forEach((el) => el.setAttribute('data-revealed', ''));
    });

    mm.add(NO_MOTION, () => {
        document.documentElement.classList.add('anim-ready');
        items.forEach((el) => el.setAttribute('data-revealed', ''));
    });
}
