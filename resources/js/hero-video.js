// Vidéo d'en-tête (drone au-dessus du campus) : chargée seulement si la connexion et les préférences le permettent.
// - connexion lente / « économiseur de données » ou mouvement réduit : on reste sur l'image d'affiche, rien n'est téléchargé ;
// - écran étroit : version 720p ; sinon 1080p ;
// - lecture en boucle : la vidéo s'efface en fondu sur la dernière demi-seconde et réapparaît au redémarrage,
//   pour masquer le saut entre la fin et le début du vol.
const FADE_BEFORE_END = 0.7;

export function initHeroVideo() {
    const video = document.querySelector('[data-hero-video]');
    if (!video) return;

    const connection = navigator.connection;
    const slow = Boolean(connection?.saveData) || ['slow-2g', '2g', '3g'].includes(connection?.effectiveType);
    if (slow || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const narrow = window.matchMedia('(max-width: 767px)').matches;
    video.loop = true;
    video.src = narrow ? video.dataset.srcSd : video.dataset.srcHd;
    video.addEventListener('playing', () => video.setAttribute('data-playing', ''), { once: true });
    video.addEventListener('timeupdate', () => {
        if (!video.duration) return;
        const remaining = video.duration - video.currentTime;
        video.classList.toggle('is-fading', remaining < FADE_BEFORE_END);
    });
    video.play().catch(() => {});
}
