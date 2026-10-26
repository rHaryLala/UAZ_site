// Parallaxe générique : [data-parallax="-12"] déplace l'élément de -12 % de sa hauteur sur la durée de son passage.
import { gsap, MOTION_OK } from './gsap.js';

export function initParallax() {
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, () => {
        gsap.utils.toArray('[data-parallax]').forEach((el) => {
            const amount = parseFloat(el.dataset.parallax) || -10;
            gsap.fromTo(
                el,
                { yPercent: -amount / 2 },
                {
                    yPercent: amount / 2,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: el.parentElement,
                        start: 'top bottom',
                        end: 'bottom top',
                        scrub: true,
                    },
                },
            );
        });
    });
}
