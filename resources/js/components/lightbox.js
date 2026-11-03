// Visionneuse de galerie basée sur <dialog> (focus piégé et Échap gérés nativement par le navigateur).
// Chaque vignette est un lien <a data-lightbox-item href="grande-image"> : sans JS, le lien ouvre l'image.
// Zoom : boutons + / − / pourcentage (rétablit la taille), touches + − 0, molette, double-clic, pincement ; une photo zoomée se
// déplace en la faisant glisser. Le zoom revient à 1 à chaque changement de photo.
const MIN_ZOOM = 1;
const MAX_ZOOM = 5;

export default () => {
    // Hors de l'état réactif d'Alpine : une Map n'a pas à être observée.
    const pointers = new Map();

    return {
        items: [],
        index: 0,
        zoom: 1,
        panX: 0,
        panY: 0,
        pinchStart: null,
        dragged: false,

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

            const img = this.$refs.photo;
            img.addEventListener('wheel', (event) => this.onWheel(event), { passive: false });
            img.addEventListener('pointerdown', (event) => this.onPointerDown(event));
            img.addEventListener('pointermove', (event) => this.onPointerMove(event));
            img.addEventListener('pointerup', (event) => this.onPointerEnd(event));
            img.addEventListener('pointercancel', (event) => this.onPointerEnd(event));
            img.addEventListener('dblclick', (event) => this.onDoubleClick(event));
            img.addEventListener('dragstart', (event) => event.preventDefault());
        },

        get current() {
            return this.items[this.index] ?? { src: '', alt: '', caption: '' };
        },

        get counter() {
            return `${this.index + 1} / ${this.items.length}`;
        },

        get zoomLabel() {
            return `${Math.round(this.zoom * 100)} %`;
        },

        show(i) {
            this.index = i;
            this.resetZoom();
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
            this.resetZoom();
        },

        prev() {
            this.index = (this.index - 1 + this.items.length) % this.items.length;
            this.resetZoom();
        },

        // ── Zoom ──

        zoomIn() {
            this.zoomTo(this.zoom * 1.5);
        },

        zoomOut() {
            this.zoomTo(this.zoom / 1.5);
        },

        resetZoom() {
            this.zoom = 1;
            this.panX = 0;
            this.panY = 0;
            pointers.clear();
            this.pinchStart = null;
            this.render();
        },

        /** Change l'échelle en gardant fixe le point (x, y), en pixels depuis le centre de l'image (par défaut : le centre). */
        zoomTo(target, x = 0, y = 0) {
            const next = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, target));
            const ratio = next / this.zoom;
            this.panX = x - (x - this.panX) * ratio;
            this.panY = y - (y - this.panY) * ratio;
            this.zoom = next;
            this.render();
        },

        onKey(event) {
            if (event.key === '+' || event.key === '=') {
                event.preventDefault();
                this.zoomIn();
            } else if (event.key === '-' || event.key === '_') {
                event.preventDefault();
                this.zoomOut();
            } else if (event.key === '0') {
                event.preventDefault();
                this.resetZoom();
            }
        },

        /** Position d'un pointeur par rapport au centre de l'image (non zoomée). */
        fromCenter(event) {
            const box = this.$refs.photo.getBoundingClientRect();
            const size = this.$refs.photo.offsetWidth
                ? { w: this.$refs.photo.offsetWidth, h: this.$refs.photo.offsetHeight }
                : { w: box.width, h: box.height };
            const cx = box.left + box.width / 2 - this.panX;
            const cy = box.top + box.height / 2 - this.panY;

            return { x: event.clientX - cx, y: event.clientY - cy, size };
        },

        onWheel(event) {
            event.preventDefault();
            const at = this.fromCenter(event);
            this.zoomTo(this.zoom * Math.exp(-event.deltaY * 0.0018), at.x, at.y);
        },

        onDoubleClick(event) {
            const at = this.fromCenter(event);
            if (this.zoom > 1.05) this.resetZoom();
            else this.zoomTo(2.5, at.x, at.y);
        },

        onPointerDown(event) {
            try {
                this.$refs.photo.setPointerCapture(event.pointerId);
            } catch {
                // pointeur déjà terminé : le glissement fonctionne sans capture
            }
            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
            this.dragged = false;
            if (pointers.size === 2) {
                const [a, b] = [...pointers.values()];
                this.pinchStart = { distance: Math.hypot(a.x - b.x, a.y - b.y), zoom: this.zoom };
            }
        },

        onPointerMove(event) {
            const last = pointers.get(event.pointerId);
            if (!last) return;
            const point = { x: event.clientX, y: event.clientY };

            if (pointers.size === 2 && this.pinchStart) {
                pointers.set(event.pointerId, point);
                const [a, b] = [...pointers.values()];
                const distance = Math.hypot(a.x - b.x, a.y - b.y);
                const middle = this.fromCenter({ clientX: (a.x + b.x) / 2, clientY: (a.y + b.y) / 2 });
                this.zoomTo(
                    this.pinchStart.zoom * (distance / (this.pinchStart.distance || 1)),
                    middle.x,
                    middle.y,
                );
                this.dragged = true;
                return;
            }

            if (this.zoom > 1) {
                this.panX += point.x - last.x;
                this.panY += point.y - last.y;
                this.dragged = true;
                this.render();
            }
            pointers.set(event.pointerId, point);
        },

        onPointerEnd(event) {
            pointers.delete(event.pointerId);
            if (pointers.size < 2) this.pinchStart = null;
        },

        /** La photo ne peut pas sortir de son cadre d'origine : au plus la moitié de l'agrandissement de chaque côté. */
        render() {
            const img = this.$refs.photo;
            const limitX = (img.offsetWidth * (this.zoom - 1)) / 2;
            const limitY = (img.offsetHeight * (this.zoom - 1)) / 2;
            this.panX = Math.max(-limitX, Math.min(limitX, this.panX));
            this.panY = Math.max(-limitY, Math.min(limitY, this.panY));
            img.style.transform =
                this.zoom === 1 ? '' : `translate(${this.panX}px, ${this.panY}px) scale(${this.zoom})`;
            img.style.cursor = this.zoom > 1 ? 'grab' : 'zoom-in';
            this.$refs.dialog.toggleAttribute('data-zoomed', this.zoom > 1);
        },
    };
};
