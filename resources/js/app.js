// Point d'entrée du site public (volontairement minuscule : tout le reste est chargé à la demande).
//
// - Alpine tourne en build « CSP » (pas d'eval) : compatible avec une Content-Security-Policy stricte.
//   Les composants Alpine sont donc déclarés en JS (components/*), jamais en expressions inline complexes.
// - Livewire (qui embarque son propre Alpine) n'est chargé que sur les pages qui contiennent un composant
//   Livewire (formulaire de contact, recherche). Ailleurs : Alpine seul, bien plus léger.
// - Les animations (GSAP, Lenis…) sont chargées après le rendu, module par module.
import { initAnimations } from './animations/index.js';

const hasLivewire = document.querySelector('[wire\\:id]') !== null;

(hasLivewire ? import('./boot/livewire.js') : import('./boot/alpine.js')).then((boot) => boot.default());

initAnimations();

// Filtres sans rechargement complet (zones data-ajax-region) : chargé seulement si la page en contient.
if (document.querySelector('[data-ajax-region]')) import('./ajax-region.js').then((m) => m.initAjaxRegion());
