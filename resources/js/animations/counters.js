// Chiffres clés : compteurs animés au défilement. La valeur finale est déjà dans le HTML (et dans un texte
// réservé aux lecteurs d'écran) : cette animation est purement visuelle.
import { gsap, MOTION_OK } from './gsap.js';

export function initCounters() {
    const mm = gsap.matchMedia();

    mm.add(MOTION_OK, () => {
        gsap.utils.toArray('[data-counter]').forEach((el) => {
            const end = Number(el.dataset.value);
            if (!Number.isFinite(end)) return;

            // Les années (1995…) ne partent pas de 0 : on remonte quelques décennies seulement.
            const start = end > 1800 && end < 2200 ? end - 90 : 0;
            const format = new Intl.NumberFormat('fr-FR', { useGrouping: end >= 10000 });
            const state = { value: start };
            el.textContent = format.format(start);

            gsap.to(state, {
                value: end,
                duration: 2.4,
                ease: 'power3.out',
                scrollTrigger: { trigger: el, start: 'top 92%', once: true },
                onUpdate: () => {
                    el.textContent = format.format(Math.round(state.value));
                },
                onComplete: () => {
                    el.textContent = format.format(end);
                },
            });
        });
    });
}
