import siteMenu from './site-menu.js';
import carousel from './carousel.js';
import lightbox from './lightbox.js';
import searchModal from './search-modal.js';

/** Déclare les composants Alpine du site (partagé par les deux modes de démarrage). */
export function registerComponents(Alpine) {
    Alpine.data('siteMenu', siteMenu);
    Alpine.data('carousel', carousel);
    Alpine.data('lightbox', lightbox);
    Alpine.data('searchModal', searchModal);
}
