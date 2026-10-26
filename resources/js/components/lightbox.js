// Visionneuse de galerie basée sur <dialog> (focus piégé et Échap gérés nativement par le navigateur).
// Chaque vignette est un lien <a data-lightbox-item href="grande-image"> : sans JS, le lien ouvre l'image.
export default () => ({
    items: [],
    index: 0,

    init() {
        this.items = [...this.$el.querySelectorAll('[data-lightbox-item]')].map((link) => ({
            src: link.getAttribute('href'),
            alt: link.dataset.alt ?? '',
            caption: link.dataset.caption ?? '',
            trigger: link,
        }));

        this.items.forEach((item, i) =>
            item.trigger.addEventListener('click', (event) => {
                event.preventDefault();
                this.show(i);
            }),
        );

        this.$refs.dialog.addEventListener('click', (event) => {
            if (event.target === this.$refs.dialog) this.close();
        });
    },

    get current() {
        return this.items[this.index] ?? { src: '', alt: '', caption: '' };
    },

    get counter() {
        return `${this.index + 1} / ${this.items.length}`;
    },

    show(i) {
        this.index = i;
        this.$refs.dialog.showModal();
        window.dispatchEvent(new CustomEvent('uaz:menu', { detail: { open: true } }));
    },

    close() {
        this.$refs.dialog.close();
        window.dispatchEvent(new CustomEvent('uaz:menu', { detail: { open: false } }));
        this.items[this.index]?.trigger.focus();
    },

    next() {
        this.index = (this.index + 1) % this.items.length;
    },

    prev() {
        this.index = (this.index - 1 + this.items.length) % this.items.length;
    },
});
