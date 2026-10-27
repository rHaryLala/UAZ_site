// Météo du campus dans le pied de page : la page arrive avec la valeur du moment ; tant qu'elle reste ouverte, on la rafraîchit
// toutes les 10 minutes (route /meteo, mise en cache côté serveur).
const REFRESH = 10 * 60 * 1000;

export function initWeather() {
    const box = document.querySelector('[data-weather]');
    if (!box) return;

    const refresh = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(box.dataset.url, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            if (!data) return;

            box.querySelector('[data-weather-temp]').textContent = `${data.temperature} °C`;
            box.querySelector('[data-weather-label]').textContent = data.label;
            // Les icônes sont déjà dans la page (une par état) : on n'affiche que la bonne.
            box.querySelectorAll('[data-weather-icon]').forEach((icon) => {
                icon.toggleAttribute('hidden', icon.dataset.weatherIcon !== data.icon);
            });
        } catch {
            // Hors ligne ou erreur : on garde la valeur affichée.
        }
    };

    setInterval(refresh, REFRESH);
}
