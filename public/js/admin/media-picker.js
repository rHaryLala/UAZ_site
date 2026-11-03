// Sélecteur de médias façon WordPress pour le back-office : une fenêtre avec la médiathèque en grille (recherche, filtre, détails du fichier)
// et un onglet « Envoyer des fichiers » (glisser-déposer + texte alternatif obligatoire). Utilisé par App\Filament\Forms\MediaField.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('uazMediaPicker', (config) => ({
        state: config.state,
        multiple: config.multiple,
        imagesOnly: config.imagesOnly,
        current: config.current, // aperçu du média actuellement choisi (mode simple)
        open: false,
        tab: 'library',
        items: [],
        page: 1,
        hasMore: false,
        loading: false,
        search: '',
        type: config.imagesOnly ? 'image' : 'all',
        selected: [], // médias cochés dans la fenêtre
        pending: [], // fichiers en attente d'envoi : { file, preview, alt, error }
        uploading: false,
        uploadError: '',
        progress: { done: 0, total: 0 },
        dragging: false,
        timer: null,
        trapOwner: null,
        token: 0,

        get focus() {
            return this.selected[this.selected.length - 1] ?? null;
        },

        // Dans une fenêtre modale de Filament (témoignages, équipe…), son piège à focus ramènerait le curseur hors du champ de texte
        // alternatif : il est suspendu tant que le sélecteur est ouvert, puis rétabli.
        suspendTrap() {
            for (let el = this.$root.parentElement; el; el = el.parentElement) {
                const owner = (el._x_dataStack ?? []).find((scope) => 'isTrapActive' in scope);
                if (owner) {
                    this.trapOwner = owner.isTrapActive ? owner : null;
                    if (this.trapOwner) this.trapOwner.isTrapActive = false;
                    return;
                }
            }
        },

        resumeTrap() {
            if (this.trapOwner && this.$root.isConnected) this.trapOwner.isTrapActive = true;
            this.trapOwner = null;
        },

        async show() {
            this.suspendTrap();
            this.open = true;
            this.tab = 'library';
            this.selected = !this.multiple && this.current ? [this.current] : [];
            document.documentElement.style.overflow = 'hidden';
            if (!this.items.length) await this.load(true);
            this.$nextTick(() => this.$refs.search?.focus());
        },

        close() {
            this.open = false;
            document.documentElement.style.overflow = '';
            this.resumeTrap();
        },

        onSearch() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.load(true), 280);
        },

        async load(reset = false) {
            if (this.loading && !reset) return;
            const token = ++this.token; // une recherche plus récente remplace celle en cours
            this.loading = true;
            if (reset) {
                this.page = 1;
                this.items = [];
            }
            try {
                const params = new URLSearchParams({ q: this.search, type: this.type, page: this.page });
                const response = await fetch(`${config.browseUrl}?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const json = await response.json();
                if (token !== this.token) return;
                this.items = reset ? json.data : [...this.items, ...json.data];
                this.hasMore = json.has_more;
                this.page += 1;
            } catch (error) {
                this.uploadError = 'La médiathèque est momentanément indisponible.';
            } finally {
                if (token === this.token) this.loading = false;
            }
        },

        isSelected(item) {
            return this.selected.some((s) => s.id === item.id);
        },

        toggle(item) {
            if (this.multiple) {
                this.selected = this.isSelected(item) ? this.selected.filter((s) => s.id !== item.id) : [...this.selected, item];
            } else {
                this.selected = [item];
            }
        },

        choose() {
            if (!this.selected.length) return;
            if (this.multiple) {
                this.state = this.selected.map((s) => s.id);
            } else {
                this.current = this.selected[0];
                this.state = this.selected[0].id;
            }
            this.close();
        },

        remove() {
            this.current = null;
            this.state = null;
        },

        // --- Envoi de fichiers ---
        addFiles(fileList) {
            this.uploadError = '';
            [...fileList].forEach((file) => {
                if (!/^(image\/(jpeg|png|webp)|application\/pdf)$/.test(file.type)) {
                    this.uploadError = `« ${file.name} » n'est pas accepté (JPEG, PNG, WebP ou PDF seulement).`;

                    return;
                }
                this.pending.push({ file, preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null, alt: '', error: '' });
            });
        },

        onDrop(event) {
            this.dragging = false;
            this.addFiles(event.dataTransfer.files);
        },

        removePending(index) {
            this.pending.splice(index, 1);
        },

        get canUpload() {
            return this.pending.length > 0 && this.pending.every((p) => p.alt.trim().length >= 3) && !this.uploading;
        },

        // Un fichier par requête : plusieurs photos de drone ou d'appareil photo dépassent vite la limite de taille d'une
        // requête du serveur (413). Les fichiers refusés restent dans la liste, avec leur erreur, pour être retirés ou renvoyés.
        async upload() {
            if (!this.canUpload) return;
            this.uploading = true;
            this.uploadError = '';
            this.progress = { done: 0, total: this.pending.length };
            const sent = [];
            for (const p of [...this.pending]) {
                p.error = '';
                const body = new FormData();
                body.append('files[]', p.file);
                body.append('alts[0]', p.alt.trim());
                try {
                    const response = await fetch(config.uploadUrl, {
                        method: 'POST',
                        body,
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
                    });
                    const json = await response.json().catch(() => null);
                    if (response.ok && json?.data) {
                        sent.push(...json.data);
                        p.preview && URL.revokeObjectURL(p.preview);
                        this.pending = this.pending.filter((other) => other !== p);
                    } else {
                        p.error = response.status === 413 ? 'Fichier trop lourd pour le serveur.' : (json?.message ?? 'Envoi impossible.');
                    }
                } catch (error) {
                    p.error = 'Envoi impossible : vérifiez votre connexion et réessayez.';
                }
                this.progress.done++;
            }
            if (sent.length) {
                this.items = [...sent, ...this.items];
                this.selected = this.multiple ? [...this.selected, ...sent] : [sent[0]];
            }
            if (this.pending.length) {
                this.uploadError = `${this.pending.length} fichier(s) n'ont pas été envoyés : voir le détail ci-dessous.`;
            } else {
                this.tab = 'library';
            }
            this.uploading = false;
        },
    }));
});
