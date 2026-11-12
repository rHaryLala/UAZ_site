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

// Fenêtre « Étudiant » de la barre du haut (choix Registrar / Moodle).
if (document.querySelector('[data-student-dialog]')) import('./student-dialog.js').then((m) => m.initStudentDialog());

// Fenêtre « Newsletter » : ouverture à la première visite, puis tous les 20 jours si non abonné.
if (document.querySelector('[data-newsletter-dialog]')) import('./newsletter.js').then((m) => m.initNewsletter());

// Frais académiques : simulateur de relevé et boutons « Copier » (indépendants des animations).
if (document.querySelector('[data-fee-sim]')) import('./fees-sim.js').then((m) => m.initFeesSim());
if (document.querySelector('[data-fee-copy]')) import('./fees-sim.js').then((m) => m.initFeeCopy());

// Titre « Votre futur commence ici. » en trois langues, avec glitch.
if (document.querySelector('[data-future-title]')) import('./future-title.js').then((m) => m.initFutureTitle());

// Vidéo « Votre futur commence ici » de l'accueil (lecture automatique).
if (document.querySelector('[data-future]')) import('./future-video.js').then((m) => m.initFutureVideo());

// Météo du campus (pied de page) : rafraîchie toutes les 10 minutes.
if (document.querySelector('[data-weather]')) import('./weather.js').then((m) => m.initWeather());

// Phrase du héros de l'accueil en trois langues.
if (document.querySelector('[data-hero-ticker]')) import('./hero-ticker.js').then((m) => m.initHeroTicker());

// Yearbook : livre numérique à feuilleter.
if (document.querySelector('[data-yearbook]')) import('./yearbook.js').then((m) => m.initYearbook());

// Vidéo d'en-tête de la page À propos.
if (document.querySelector('[data-hero-video]')) import('./hero-video.js').then((m) => m.initHeroVideo());

// Page Équipe : fiche agrandie au clic sur une carte.
if (document.querySelector('[data-team-grid]')) import('./team-profile.js').then((m) => m.initTeamProfile());

// Filtres sans rechargement complet (zones data-ajax-region) : chargé seulement si la page en contient.
if (document.querySelector('[data-ajax-region]')) import('./ajax-region.js').then((m) => m.initAjaxRegion());
