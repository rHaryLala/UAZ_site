// Vidéo « Votre futur commence ici » (accueil) : démarre toute seule, en boucle, sans pause ni avance rapide ; la seule commande est le
// bouton qui coupe ou remet le son. Rien n'est téléchargé avant que la section approche de l'écran.
// Les navigateurs interdisent l'autoplay avec le son : la lecture commence donc muette, le visiteur active le son d'un clic.
// Connexion lente, économiseur de données ou mouvement réduit : pas de lecture automatique, un bouton ▶ apparaît à la place.
export function initFutureVideo() {
    const box = document.querySelector('[data-future]');
    const video = box?.querySelector('[data-future-video]');
    const play = box?.querySelector('[data-future-play]');
    const sound = box?.querySelector('[data-future-sound]');
    if (!video) return;

    const connection = navigator.connection;
    const slow = Boolean(connection?.saveData) || ['slow-2g', '2g', '3g'].includes(connection?.effectiveType);
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const narrow = window.matchMedia('(max-width: 767px)').matches;

    const syncSound = () => {
        const on = !video.muted;
        sound?.setAttribute('aria-pressed', String(on));
        sound?.setAttribute('aria-label', on ? sound.dataset.labelOff : sound.dataset.labelOn);
        sound?.querySelector('[data-sound-icon="on"]')?.toggleAttribute('hidden', !on);
        sound?.querySelector('[data-sound-icon="off"]')?.toggleAttribute('hidden', on);
    };

    sound?.addEventListener('click', () => {
        video.muted = !video.muted;
        syncSound();
    });

    // Pas de menu contextuel ni de clic qui mette en pause : la vidéo n'a aucune autre commande.
    video.addEventListener('contextmenu', (event) => event.preventDefault());

    const start = (withSound) => {
        video.src = narrow ? video.dataset.srcSd : video.dataset.srcHd;
        video.muted = !withSound;
        video.loop = true;
        syncSound();
        play?.setAttribute('hidden', '');
        video.play().catch(() => play?.removeAttribute('hidden'));
    };

    if (slow || reduced) {
        play?.removeAttribute('hidden');
        play?.addEventListener('click', () => start(true), { once: true });

        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            if (!entries.some((entry) => entry.isIntersecting)) return;
            observer.disconnect();
            start(false);
        },
        { rootMargin: '300px 0px' },
    );
    observer.observe(box);
}
