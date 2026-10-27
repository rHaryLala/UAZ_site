// Menu plein écran + état « page défilée » de l'en-tête.
// Écrit pour la build CSP d'Alpine : le gabarit n'utilise que des références (open, toggle, close…).
export default () => ({
    open: false,
    scrolled: false,
    dark: false, // l'en-tête translucide passe au-dessus d'une section sombre (texte clair)

    init() {
        let queued = false;
        const update = () => {
            queued = false;
            this.scrolled = window.scrollY > 24;
            this.dark = this.scrolled && this.sectionIsDark();
        };
        update();
        window.addEventListener(
            'scroll',
            () => {
                if (queued) return;
                queued = true;
                requestAnimationFrame(update);
            },
            { passive: true },
        );
    },

    // Section pleine largeur (et non une simple carte) sombre située sous le milieu de l'en-tête.
    sectionIsDark() {
        const header = document.querySelector('.site-header');
        if (!header) return false;
        const box = header.getBoundingClientRect();
        const under = document
            .elementsFromPoint(window.innerWidth / 2, box.top + box.height / 2)
            .find((el) => !header.contains(el) && el.closest('.on-dark, main > *, footer'));
        const section = under?.closest('.on-dark');

        return Boolean(section) && section.getBoundingClientRect().width >= window.innerWidth * 0.9;
    },

    // La loupe (ou « Recherche » dans la navigation) ouvre la fenêtre de recherche, pas la page.
    openSearch() {
        this.close();
        window.dispatchEvent(new CustomEvent('uaz:search-open'));
    },

    toggle() {
        this.set(!this.open);
    },

    close() {
        if (this.open) this.set(false);
    },

    set(value) {
        this.open = value;
        document.documentElement.classList.toggle('menu-open', value);
        // Le défilement fluide (Lenis) s'arrête pendant que le menu est ouvert.
        window.dispatchEvent(new CustomEvent('uaz:menu', { detail: { open: value } }));
        if (!value) this.$refs.trigger?.focus();
    },
});
