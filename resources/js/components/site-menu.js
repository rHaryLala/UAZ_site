// Menu plein écran + état « page défilée » de l'en-tête.
// Écrit pour la build CSP d'Alpine : le gabarit n'utilise que des références (open, toggle, close…).
export default () => ({
    open: false,
    scrolled: false,

    init() {
        const onScroll = () => {
            this.scrolled = window.scrollY > 24;
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
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
