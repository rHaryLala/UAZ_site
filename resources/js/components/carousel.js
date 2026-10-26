// Carrousel accessible : une simple piste « scroll-snap » (utilisable sans JS, au doigt ou au clavier),
// avec boutons précédent/suivant et indicateur. Pas de lecture automatique (WCAG 2.2.2).
export default () => ({
    index: 0,
    count: 0,

    init() {
        const track = this.$refs.track;
        this.count = track.children.length;
        track.addEventListener(
            'scroll',
            () => {
                const slide = track.children[0];
                if (!slide) return;
                this.index = Math.round(track.scrollLeft / slide.getBoundingClientRect().width);
            },
            { passive: true },
        );
    },

    get label() {
        return `${this.index + 1} / ${this.count}`;
    },

    get atStart() {
        return this.index <= 0;
    },

    get atEnd() {
        return this.index >= this.count - 1;
    },

    next() {
        this.scrollBySlide(1);
    },

    prev() {
        this.scrollBySlide(-1);
    },

    scrollBySlide(direction) {
        const track = this.$refs.track;
        const width = track.children[0]?.getBoundingClientRect().width ?? track.clientWidth;
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        track.scrollBy({ left: direction * width, behavior: reduced ? 'auto' : 'smooth' });
    },
});
