// Page Équipe : un clic sur une carte l'agrandit en fiche (citation, message aux lecteurs). Animation « FLIP » : la fiche part
// de la position et de la taille de la carte pour atteindre sa taille finale (Web Animations, pas de bibliothèque).
// Mouvement réduit : ouverture instantanée. Sans JavaScript, les cartes restent de simples vignettes (le bouton est inerte).
const EASE = 'cubic-bezier(0.16, 1, 0.3, 1)';

export function initTeamProfile() {
    const dialog = document.getElementById('team-profile');
    const grid = document.querySelector('[data-team-grid]');
    if (!dialog || !grid || typeof dialog.showModal !== 'function') return;

    const panel = dialog.querySelector('[data-profile-panel]');
    const body = dialog.querySelector('[data-profile-body]');
    const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let origin = null;
    let closing = false;

    const lock = (open) => {
        document.documentElement.classList.toggle('profile-open', open);
        // Le défilement fluide (Lenis) s'arrête pendant que la fiche est ouverte.
        window.dispatchEvent(new CustomEvent('uaz:search', { detail: { open } }));
    };

    // Transformation qui ramène la fiche vers la carte d'origine : on déplace et on réduit de façon UNIFORME (même échelle en
    // largeur et en hauteur), sinon la photo serait écrasée ; le fondu cache la différence de proportions.
    const toCard = (card) => {
        const from = card.getBoundingClientRect();
        const to = panel.getBoundingClientRect();
        const scale = Math.max(from.width / to.width, from.height / to.height);

        const dx = from.left + from.width / 2 - (to.left + to.width / 2);
        const dy = from.top + from.height / 2 - (to.top + to.height / 2);

        return `translate(${dx}px, ${dy}px) scale(${scale})`;
    };

    const visible = (card) => {
        const rect = card.getBoundingClientRect();

        return rect.bottom > 0 && rect.top < window.innerHeight && rect.right > 0 && rect.left < window.innerWidth;
    };

    const open = (card, template) => {
        origin = card;
        body.replaceChildren(template.content.cloneNode(true));
        dialog.setAttribute('aria-label', body.querySelector('[data-profile-name]')?.textContent.trim() ?? 'Profil');
        dialog.showModal();
        dialog.scrollTop = 0;
        panel.scrollTop = 0;
        lock(true);

        if (reduced()) return;
        panel.animate([{ transform: toCard(card), opacity: 0 }, { transform: 'none', opacity: 1 }], { duration: 480, easing: EASE });
        body.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 320, delay: 180, easing: 'ease-out', fill: 'backwards' });
    };

    const close = async () => {
        if (closing || !dialog.open) return;
        closing = true;

        if (!reduced()) {
            const animation = origin && visible(origin)
                ? panel.animate([{ transform: 'none', opacity: 1 }, { transform: toCard(origin), opacity: 0 }], { duration: 340, easing: 'cubic-bezier(0.65, 0, 0.35, 1)', fill: 'forwards' })
                : panel.animate([{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateY(16px)' }], { duration: 200, fill: 'forwards' });
            await animation.finished.catch(() => {});
            animation.cancel();
        }

        dialog.close();
        lock(false);
        closing = false;
        origin?.querySelector('[data-profile-open]')?.focus({ preventScroll: true });
    };

    grid.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-profile-open]') : null;
        if (!button) return;
        const item = button.closest('li');
        const template = item?.querySelector('[data-profile-template]');
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
