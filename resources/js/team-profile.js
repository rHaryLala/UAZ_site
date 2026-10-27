// Page Équipe : un clic sur une carte la retourne en 3D et l'agrandit en fiche (citation, message aux lecteurs).
// GSAP : la carte se soulève vers l'écran (profondeur), pivote de 180° autour de l'axe vertical pendant qu'elle grandit jusqu'à
// la taille de la fiche, puis le contenu apparaît en cascade. La fermeture rejoue la même timeline à l'envers.
// Rien n'est jamais étiré : la taille réelle (largeur/hauteur) est animée, la face avant est une copie de la carte qui remplit
// son cadre, et la face arrière a son contenu à taille finale derrière une « fenêtre » qui s'agrandit.
// Mouvement réduit : ouverture instantanée. Sans JavaScript, les cartes restent de simples vignettes.
import { gsap } from './animations/gsap.js';

const MAX_WIDTH = 960;
const MARGIN = 16;

export function initTeamProfile() {
    const dialog = document.getElementById('team-profile');
    const grid = document.querySelector('[data-team-grid]');
    if (!dialog || !grid || typeof dialog.showModal !== 'function') return;

    const flip = dialog.querySelector('[data-flip]');
    const front = dialog.querySelector('[data-flip-front]');
    const back = dialog.querySelector('[data-flip-back]');
    const inner = dialog.querySelector('[data-flip-inner]');
    const body = dialog.querySelector('[data-profile-body]');
    const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let timeline = null;
    let origin = null;
    let busy = false;

    const lock = (open) => {
        document.documentElement.classList.toggle('profile-open', open);
        // Le défilement fluide (Lenis) s'arrête pendant que la fiche est ouverte.
        window.dispatchEvent(new CustomEvent('uaz:search', { detail: { open } }));
    };

    // Taille finale de la fiche : on mesure le contenu à sa largeur finale, plafonné à la hauteur de l'écran.
    const finalSize = () => {
        const width = Math.min(MAX_WIDTH, window.innerWidth - MARGIN * 2);
        inner.style.width = `${width}px`;
        inner.style.height = 'auto';
        inner.style.maxHeight = `${window.innerHeight - MARGIN * 2}px`;
        const height = Math.min(inner.offsetHeight, window.innerHeight - MARGIN * 2);
        inner.style.height = `${height}px`;

        return { width, height, left: (window.innerWidth - width) / 2, top: (window.innerHeight - height) / 2 };
    };

    const open = (card, template) => {
        if (busy) return;
        busy = true;
        origin = card;

        body.replaceChildren(template.content.cloneNode(true));
        dialog.setAttribute('aria-label', body.querySelector('[data-profile-name]')?.textContent.trim() ?? 'Profil');

        // Face avant : copie inerte de la carte (sans son bouton), qui remplit son cadre.
        const copy = card.cloneNode(true);
        copy.querySelector('[data-profile-open]')?.remove();
        copy.querySelectorAll('a, button').forEach((el) => el.setAttribute('tabindex', '-1'));
        copy.classList.remove('hover:shadow-lift', 'focus-within:shadow-lift');
        front.replaceChildren(copy);

        const from = card.getBoundingClientRect();

        dialog.showModal();
        // Mesure après l'ouverture : un <dialog> fermé n'a pas de dimensions.
        const to = finalSize();
        lock(true);
        inner.scrollTop = 0;

        if (reduced()) {
            gsap.set(flip, { left: to.left, top: to.top, width: to.width, height: to.height, rotationY: 180 });
            busy = false;
            dialog.querySelector('[data-profile-close]').focus();

            return;
        }

        const items = body.querySelectorAll('[data-profile-content] > *');
        const photo = body.querySelector('img, picture, svg');
        card.style.visibility = 'hidden';

        gsap.set(flip, { left: from.left, top: from.top, width: from.width, height: from.height, rotationY: 0, rotationX: 0, z: 0, transformOrigin: '50% 50%' });
        gsap.set(items, { autoAlpha: 0, y: 26 });
        gsap.set(photo, { scale: 1.12, transformOrigin: '50% 50%' });

        timeline = gsap
            .timeline({ onReverseComplete: finishClose, onComplete: () => { busy = false; dialog.querySelector('[data-profile-close]').focus({ preventScroll: true }); } })
            // Retournement + agrandissement (dimensions réelles, jamais d'étirement).
            .to(flip, { left: to.left, top: to.top, width: to.width, height: to.height, rotationY: 180, duration: 1.05, ease: 'power3.inOut' }, 0)
            // Profondeur : la carte se soulève vers l'écran pendant la première moitié du retournement, puis se repose.
            .to(flip, { z: 260, rotationX: -7, duration: 0.5, ease: 'power2.out' }, 0)
            .to(flip, { z: 0, rotationX: 0, duration: 0.55, ease: 'power2.inOut' }, 0.5)
            .fromTo(flip, { boxShadow: '0 8px 14px rgba(0,0,0,0.25)' }, { boxShadow: '0 34px 60px rgba(0,0,0,0.55)', duration: 0.5, ease: 'power2.out', yoyo: true, repeat: 1 }, 0)
            // Fond : la page recule légèrement.
            .to(grid, { scale: 0.97, opacity: 0.6, duration: 0.9, ease: 'power2.out' }, 0)
            // Contenu de la fiche : léger zoom arrière de la photo, texte en cascade.
            .to(photo, { scale: 1, duration: 1.1, ease: 'power2.out' }, 0.55)
            .to(items, { autoAlpha: 1, y: 0, duration: 0.6, ease: 'power3.out', stagger: 0.07 }, 0.75);
    };

    function finishClose() {
        dialog.close();
        lock(false);
        gsap.set(grid, { clearProps: 'transform,opacity' });
        gsap.set(flip, { clearProps: 'all' });
        if (origin) {
            origin.style.visibility = '';
            origin.querySelector('[data-profile-open]')?.focus({ preventScroll: true });
        }
        timeline = null;
        busy = false;
    }

    const close = () => {
        if (!dialog.open) return;

        if (!timeline || reduced()) {
            finishClose();

            return;
        }

        busy = true;
        timeline.timeScale(1.25).reverse();
    };

    grid.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-profile-open]') : null;
        if (!button) return;
        const template = button.closest('li')?.querySelector('[data-profile-template]');
        if (template) open(button.closest('article'), template);
    });

    dialog.querySelector('[data-profile-close]').addEventListener('click', close);
    // Clic sur le fond (en dehors de la fiche) : fermeture.
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close();
    });
    // Échap : on passe par notre fermeture animée.
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        close();
    });
}
