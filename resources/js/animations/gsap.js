// Enregistrement unique de GSAP et de ses plugins (partagé par tous les modules d'animation).
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';

gsap.registerPlugin(ScrollTrigger, SplitText);

export const NO_MOTION = '(prefers-reduced-motion: reduce)';
export const MOTION_OK = '(prefers-reduced-motion: no-preference)';

// Retour arrière / page restaurée depuis le cache du navigateur : on recalcule les positions.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) ScrollTrigger.refresh();
});

// Les déclencheurs sont détruits quand la page est quittée (pas de fuite entre navigations).
window.addEventListener('pagehide', () => ScrollTrigger.getAll().forEach((trigger) => trigger.kill()));

export { gsap, ScrollTrigger, SplitText };
