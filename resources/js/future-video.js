// Vidéo « Votre futur commence ici » (accueil) : rien n'est téléchargé avant le clic sur ▶. Au clic, on charge la version adaptée à
// l'écran (640 px de large sur mobile, 960 px sinon), on affiche les commandes du navigateur et on lance la lecture avec le son.
export function initFutureVideo() {
    const box = document.querySelector('[data-future]');
    const video = box?.querySelector('[data-future-video]');
    const button = box?.querySelector('[data-future-play]');
    if (!video || !button) return;

    button.addEventListener('click', () => {
        const narrow = window.matchMedia('(max-width: 767px)').matches;
        video.src = narrow ? video.dataset.srcSd : video.dataset.srcHd;
        video.controls = true;
        button.remove();
        video.play().catch(() => {});
        video.focus();
    });
}
