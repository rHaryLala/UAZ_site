// Défilement fluide (Lenis) synchronisé avec ScrollTrigger. Désactivé si l'utilisateur préfère moins de mouvement.
import Lenis from 'lenis';
import 'lenis/dist/lenis.css';
import { gsap, ScrollTrigger, MOTION_OK } from './gsap.js';

export function initSmoothScroll() {
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, () => {
        const lenis = new Lenis({ lerp: 0.1, smoothWheel: true, anchors: true });
        window.__lenis = lenis;

        lenis.on('scroll', ScrollTrigger.update);
        const tick = (time) => lenis.raf(time * 1000);
        gsap.ticker.add(tick);
        gsap.ticker.lagSmoothing(0);

        // Menu plein écran, visionneuse… : on suspend le défilement.
        const onLock = (event) => (event.detail.open ? lenis.stop() : lenis.start());
        window.addEventListener('uaz:menu', onLock);

        // Recalcul des positions une fois les images chargées (évite des déclencheurs mal placés).
        const refresh = () => ScrollTrigger.refresh();
        window.addEventListener('load', refresh);

        // Nettoyage (changement de media query ou rechargement dynamique de la page).
        return () => {
            window.removeEventListener('uaz:menu', onLock);
            window.removeEventListener('load', refresh);
            gsap.ticker.remove(tick);
            lenis.destroy();
            delete window.__lenis;
        };
    });
}
