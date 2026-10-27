// Fenêtre de recherche (loupe de l'en-tête, Ctrl/Cmd + K ou « / ») : les résultats viennent d'un fragment HTML
// rendu par le serveur (/recherche/apercu), donc le même moteur que la page /recherche. Sans JavaScript, la loupe
// reste un simple lien vers cette page.
// Écrit pour la build CSP d'Alpine : le gabarit n'utilise que des références.
const MIN_LENGTH = 2;

export default () => ({
    open: false,
    empty: true,
    loading: false,
    timer: null,
    controller: null,
    opener: null,

    init() {
        window.addEventListener('uaz:search-open', () => this.show());
        window.addEventListener('keydown', (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                this.open ? this.close() : this.show();
            } else if (event.key === '/' && !this.open && !this.isTyping(event.target)) {
                event.preventDefault();
                this.show();
            }
        });
    },

    isTyping(target) {
        return target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
    },

    show() {
        if (this.open) return;
        this.opener = document.activeElement;
        this.open = true;
        document.documentElement.classList.add('search-open');
        window.dispatchEvent(new CustomEvent('uaz:search', { detail: { open: true } }));
        this.$nextTick(() => this.$refs.input?.select());
    },

    close() {
        if (!this.open) return;
        this.open = false;
        document.documentElement.classList.remove('search-open');
        window.dispatchEvent(new CustomEvent('uaz:search', { detail: { open: false } }));
        // Le focus retourne à la loupe (ou à l'élément qui avait le focus avant l'ouverture).
        this.$nextTick(() => this.opener?.focus?.());
    },

    onInput(event) {
        const term = event.target.value.trim();
        clearTimeout(this.timer);
        this.controller?.abort();
        this.empty = term.length < MIN_LENGTH;

        if (this.empty) {
            this.render('');
            this.loading = false;

            return;
        }

        this.loading = true;
        this.timer = setTimeout(() => this.fetchResults(term), 220);
    },

    async fetchResults(term) {
        this.controller = new AbortController();

        try {
            const response = await fetch(`${this.$root.dataset.endpoint}?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                signal: this.controller.signal,
            });
            if (!response.ok) throw new Error(String(response.status));
            this.render(await response.text());
            this.loading = false;
        } catch (error) {
            if (error.name === 'AbortError') return;
            this.render('<p class="px-6 py-10 text-center text-sm text-ink-700">La recherche est momentanément indisponible. Réessayez dans un instant.</p>');
            this.loading = false;
        }
    },

    // Le HTML vient de notre propre serveur (fragment Blade, valeurs échappées) : x-html est interdit par la build CSP d'Alpine.
    render(markup) {
        this.$refs.results.innerHTML = markup;
    },

    // Flèches haut/bas : on parcourt les résultats ; en haut de liste on revient au champ.
    onKey(event) {
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
        const links = [...this.$refs.panel.querySelectorAll('[data-search-result], [data-search-quick]')];
        if (!links.length) return;
        event.preventDefault();

        const index = links.indexOf(document.activeElement);
        if (event.key === 'ArrowDown') links[Math.min(index + 1, links.length - 1)].focus();
        else if (index <= 0) this.$refs.input.focus();
        else links[index - 1].focus();
    },
});
