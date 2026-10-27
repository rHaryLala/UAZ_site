// Menu plein écran animé avec GSAP : un rideau or puis le panneau marine s'ouvrent en cercle depuis le bouton « Menu »,
// les liens montent en cascade, les motifs se déploient. Seulement clip-path / transform / opacity.
// Le composant Alpine (components/site-menu.js) pilote l'état et annonce chaque changement par l'événement « uaz:menu » ;
// sans GSAP, ou en mouvement réduit, c'est le repli CSS (app.css) qui gère l'affichage.
import { gsap, MOTION_OK } from './gsap.js';

export function initMenu() {
    const overlay = document.getElementById('site-menu');
    const curtain = document.querySelector('[data-menu-curtain]');
    const trigger = document.querySelector('[aria-controls="site-menu"]');
    if (!overlay || !curtain || !trigger) return;

    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, () => {
        const items = overlay.querySelectorAll('[data-menu-item]');
        const deco = overlay.querySelectorAll('[data-menu-deco]');
        const root = document.documentElement;
        let timeline = null;

        // Cercle centré sur le bouton « Menu », assez grand pour couvrir tout l'écran.
        const circle = (radius) => {
            const box = trigger.getBoundingClientRect();

            return `circle(${radius}px at ${box.left + box.width / 2}px ${box.top + box.height / 2}px)`;
        };
        const reach = () => Math.hypot(window.innerWidth, window.innerHeight) + 40;

        // Ouvert avant que GSAP soit prêt (repli CSS) : on le laisse affiché, la fermeture sera animée.
        const alreadyOpen = overlay.dataset.open === 'true';
        gsap.set([overlay, curtain], { visibility: alreadyOpen ? 'visible' : 'hidden' });
        if (alreadyOpen) gsap.set(overlay, { clipPath: 'none' });
        root.classList.add('menu-gsap');

        const play = (open) => {
            timeline?.kill();
            const from = circle(0);
            const to = circle(reach());

            if (open) {
                gsap.set([curtain, overlay], { visibility: 'visible', clipPath: from });
                gsap.set(items, { autoAlpha: 0, y: 48 });
                gsap.set(deco, { autoAlpha: 0, scale: 0.92 });
                overlay.scrollTop = 0;

                timeline = gsap
                    .timeline({ defaults: { ease: 'power3.inOut' } })
                    .to(curtain, { clipPath: to, duration: 0.8 }, 0)
                    .to(overlay, { clipPath: to, duration: 0.8 }, 0.2)
                    .to(deco, { autoAlpha: 1, scale: 1, duration: 1.2, ease: 'expo.out', stagger: 0.12 }, 0.6)
                    .to(items, { autoAlpha: 1, y: 0, duration: 0.9, ease: 'expo.out', stagger: 0.045 }, 0.55);
            } else {
                timeline = gsap
                    .timeline({
                        defaults: { ease: 'power3.inOut' },
                        onComplete: () => gsap.set([curtain, overlay], { visibility: 'hidden' }),
                    })
                    .to(items, { autoAlpha: 0, y: -16, duration: 0.25, stagger: { each: 0.012, from: 'end' } }, 0)
                    .to(overlay, { clipPath: from, duration: 0.6 }, 0.1)
                    .to(curtain, { clipPath: from, duration: 0.6 }, 0.22);
            }
        };

        const onMenu = (event) => play(event.detail.open);
        window.addEventListener('uaz:menu', onMenu);

        return () => {
            window.removeEventListener('uaz:menu', onMenu);
            timeline?.kill();
            root.classList.remove('menu-gsap');
            gsap.set([overlay, curtain, items, deco], { clearProps: 'all' });
        };
    });
}
