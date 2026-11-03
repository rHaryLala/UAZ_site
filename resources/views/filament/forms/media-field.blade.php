@php
    $statePath = $getStatePath();
    $multiple = $isMultiple();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:ignore.self
        x-data="uazMediaPicker({
            state: $wire.$entangle('{{ $statePath }}', @js($isLive())),
            multiple: @js($multiple),
            imagesOnly: @js($isImagesOnly()),
            current: @js($getCurrentPreview()),
            browseUrl: @js(route('admin.media-browser.browse')),
            uploadUrl: @js(route('admin.media-browser.upload')),
            csrf: @js(csrf_token()),
        })"
        x-on:keydown.escape.window="open && close()"
        class="uaz-mp"
    >
        {{-- Champ : aperçu du choix + boutons --}}
        <div class="uaz-mp__field">
            <template x-if="!multiple && current">
                <div class="uaz-mp__current">
                    <img x-bind:src="current.thumb" alt="" class="uaz-mp__thumb">
                    <div class="uaz-mp__meta">
                        <strong x-text="current.title"></strong>
                        <span x-text="current.alt" class="uaz-mp__muted"></span>
                    </div>
                </div>
            </template>
            <template x-if="!multiple && !current">
                <div class="uaz-mp__empty">Aucune image choisie</div>
            </template>

            <div class="uaz-mp__actions">
                <button type="button" class="uaz-mp__btn uaz-mp__btn--primary" x-on:click="show()">
                    <span x-text="multiple ? 'Choisir des images dans la médiathèque' : (current ? 'Changer' : 'Choisir une image')"></span>
                </button>
                <button type="button" class="uaz-mp__btn" x-show="!multiple && current" x-cloak x-on:click="remove()">Retirer</button>
            </div>
        </div>

        {{-- Fenêtre « Médiathèque » (façon WordPress) --}}
        <template x-teleport="body">
            <div x-show="open" x-cloak class="uaz-mp-overlay" role="dialog" aria-modal="true" aria-label="Médiathèque" x-on:click.self="close()">
                <div class="uaz-mp-modal">
                    <header class="uaz-mp-modal__head">
                        <h2 x-text="multiple ? 'Ajouter des images' : 'Choisir une image'"></h2>
                        <nav class="uaz-mp-tabs" role="tablist">
                            <button type="button" role="tab" x-bind:aria-selected="tab === 'library'" x-bind:class="tab === 'library' && 'is-active'" x-on:click="tab = 'library'">Médiathèque</button>
                            <button type="button" role="tab" x-bind:aria-selected="tab === 'upload'" x-bind:class="tab === 'upload' && 'is-active'" x-on:click="tab = 'upload'">Envoyer des fichiers</button>
                        </nav>
                        <button type="button" class="uaz-mp-modal__close" aria-label="Fermer" x-on:click="close()">✕</button>
                    </header>

                    {{-- Onglet médiathèque --}}
                    <div class="uaz-mp-body" x-show="tab === 'library'">
                        <div class="uaz-mp-main">
                            <div class="uaz-mp-toolbar">
                                <input x-ref="search" type="search" placeholder="Rechercher par titre, texte alternatif ou nom de fichier…" x-model="search" x-on:input="onSearch()" class="uaz-mp-input">
                                <select x-model="type" x-on:change="load(true)" class="uaz-mp-input uaz-mp-input--select" x-show="!imagesOnly">
                                    <option value="all">Tous les fichiers</option>
                                    <option value="image">Images</option>
                                    <option value="pdf">PDF</option>
                                </select>
                            </div>

                            <div class="uaz-mp-grid" x-on:scroll.passive="if ($el.scrollTop + $el.clientHeight > $el.scrollHeight - 200 && hasMore) load()">
                                <template x-for="item in items" x-bind:key="item.id">
                                    <button type="button" class="uaz-mp-tile" x-bind:class="isSelected(item) && 'is-selected'" x-on:click="toggle(item)" x-on:dblclick="!multiple && (selected = [item], choose())" x-bind:title="item.title" x-bind:aria-pressed="isSelected(item)">
                                        <img x-bind:src="item.thumb" alt="" loading="lazy">
                                        <span class="uaz-mp-tile__check" aria-hidden="true">✓</span>
                                        <span class="uaz-mp-tile__label" x-text="item.title"></span>
                                    </button>
                                </template>
                                <p class="uaz-mp-note" x-show="!loading && !items.length" x-cloak>Aucun fichier ne correspond. Utilisez l'onglet « Envoyer des fichiers ».</p>
                                <p class="uaz-mp-note" x-show="loading" x-cloak>Chargement…</p>
                                <button type="button" class="uaz-mp__btn uaz-mp-more" x-show="hasMore && !loading" x-cloak x-on:click="load()">Charger la suite</button>
                            </div>
                        </div>

                        {{-- Détails du fichier --}}
                        <aside class="uaz-mp-side">
                            <template x-if="focus">
                                <div>
                                    <h3>Détails du fichier</h3>
                                    <img x-bind:src="focus.card" alt="" class="uaz-mp-side__preview">
                                    <dl>
                                        <dt>Titre</dt><dd x-text="focus.title"></dd>
                                        <dt>Texte alternatif</dt><dd x-text="focus.alt || '— manquant'"></dd>
                                        <dt>Fichier</dt><dd x-text="focus.filename"></dd>
                                        <dt>Poids</dt><dd x-text="focus.size"></dd>
                                        <dt>Ajouté le</dt><dd x-text="focus.date"></dd>
                                    </dl>
                                    <a x-bind:href="focus.edit_url" target="_blank" rel="noopener" class="uaz-mp-link">Modifier ce média ↗</a>
                                </div>
                            </template>
                            <p class="uaz-mp__muted" x-show="!focus" x-cloak>Cliquez sur un fichier pour voir ses détails.<span x-show="multiple"> Vous pouvez en choisir plusieurs.</span></p>
                        </aside>
                    </div>

                    {{-- Onglet envoi --}}
                    <div class="uaz-mp-body uaz-mp-body--upload" x-show="tab === 'upload'" x-cloak>
                        <label class="uaz-mp-drop" x-bind:class="dragging && 'is-over'" x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false" x-on:drop.prevent="onDrop($event)">
                            <strong>Glissez-déposez vos fichiers ici</strong>
                            <span class="uaz-mp__muted">ou cliquez pour parcourir · JPEG, PNG, WebP, PDF</span>
                            <input type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" x-on:change="addFiles($event.target.files); $event.target.value = ''" hidden>
                        </label>

                        <p class="uaz-mp-error" x-show="uploadError" x-text="uploadError" x-cloak></p>

                        <ul class="uaz-mp-pending">
                            <template x-for="(p, index) in pending" x-bind:key="index">
                                <li>
                                    <img x-show="p.preview" x-bind:src="p.preview" alt="">
                                    <span class="uaz-mp-pending__doc" x-show="!p.preview">PDF</span>
                                    <div>
                                        <strong x-text="p.file.name"></strong>
                                        <label>
                                            <span class="uaz-mp__muted">Texte alternatif (obligatoire) : décrivez ce que montre le fichier</span>
                                            <input type="text" x-model="p.alt" class="uaz-mp-input" maxlength="300" placeholder="Ex. : Étudiants devant la bibliothèque">
                                        </label>
                                    </div>
                                    <button type="button" class="uaz-mp__btn" x-on:click="removePending(index)">Retirer</button>
                                </li>
                            </template>
                        </ul>

                        <div class="uaz-mp-upload-actions" x-show="pending.length" x-cloak>
                            <button type="button" class="uaz-mp__btn uaz-mp__btn--primary" x-bind:disabled="!canUpload" x-on:click="upload()">
                                <span x-text="uploading ? 'Envoi en cours…' : 'Envoyer dans la médiathèque'"></span>
                            </button>
                            <p class="uaz-mp__muted" style="margin:.5rem 0 0; font-size:.8125rem" role="status" x-show="!canUpload && !uploading" x-cloak>
                                Pour envoyer, renseignez le texte alternatif de chaque fichier (3 caractères au moins).
                            </p>
                        </div>
                    </div>

                    <footer class="uaz-mp-modal__foot">
                        <span class="uaz-mp__muted" x-show="multiple && selected.length" x-text="selected.length + ' fichier(s) sélectionné(s)'" x-cloak></span>
                        <button type="button" class="uaz-mp__btn" x-on:click="close()">Annuler</button>
                        <button type="button" class="uaz-mp__btn uaz-mp__btn--primary" x-bind:disabled="!selected.length" x-on:click="choose()">
                            <span x-text="multiple ? 'Ajouter la sélection' : 'Choisir cette image'"></span>
                        </button>
                    </footer>
                </div>
            </div>
        </template>
    </div>

    @once
        <style>
            .uaz-mp__field { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; padding: .625rem; border: 1px solid color-mix(in srgb, currentColor 20%, transparent); border-radius: .5rem; }
            .uaz-mp__current { display: flex; align-items: center; gap: .75rem; min-width: 0; flex: 1 1 14rem; }
            .uaz-mp__thumb { width: 3.5rem; height: 3.5rem; object-fit: cover; border-radius: .375rem; flex: none; background: color-mix(in srgb, currentColor 8%, transparent); }
            .uaz-mp__meta { display: flex; flex-direction: column; min-width: 0; }
            .uaz-mp__meta strong, .uaz-mp__meta span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .uaz-mp__empty { flex: 1 1 10rem; opacity: .6; font-size: .875rem; }
            .uaz-mp__muted { opacity: .65; font-size: .8125rem; }
            .uaz-mp__actions { display: flex; gap: .5rem; flex-wrap: wrap; }
            .uaz-mp__btn { padding: .45rem .9rem; border-radius: .5rem; border: 1px solid color-mix(in srgb, currentColor 25%, transparent); font-size: .875rem; font-weight: 500; cursor: pointer; background: transparent; color: inherit; }
            .uaz-mp__btn:hover:not(:disabled) { background: color-mix(in srgb, currentColor 8%, transparent); }
            .uaz-mp__btn:disabled { opacity: .45; cursor: not-allowed; }
            .uaz-mp__btn--primary { background: var(--uaz-accent, var(--primary-600)); border-color: var(--uaz-accent, var(--primary-600)); color: var(--primary-foreground, #fff); }
            .uaz-mp__btn--primary:hover:not(:disabled) { background: var(--uaz-accent, var(--primary-500)); }

            .uaz-mp-overlay { position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgb(10 22 40 / .65); }
            .uaz-mp-modal { display: flex; flex-direction: column; width: min(78rem, 100%); height: min(46rem, 100%); border-radius: .75rem; overflow: hidden; background: #fff; color: #1e293b; box-shadow: 0 25px 60px rgb(0 0 0 / .45); }
            .dark .uaz-mp-modal { background: #0f172a; color: #e2e8f0; }
            .uaz-mp-modal__head { display: flex; align-items: center; gap: 1.5rem; padding: .75rem 1.25rem; border-bottom: 1px solid color-mix(in srgb, currentColor 14%, transparent); }
            .uaz-mp-modal__head h2 { font-size: 1.125rem; font-weight: 600; margin: 0; }
            .uaz-mp-modal__close { margin-left: auto; width: 2.25rem; height: 2.25rem; border-radius: .5rem; border: 0; background: transparent; color: inherit; font-size: 1.125rem; cursor: pointer; }
            .uaz-mp-modal__close:hover { background: color-mix(in srgb, currentColor 10%, transparent); }
            .uaz-mp-tabs { display: flex; gap: .25rem; }
            .uaz-mp-tabs button { padding: .4rem .8rem; border: 0; border-bottom: 2px solid transparent; background: transparent; color: inherit; font-size: .9rem; cursor: pointer; opacity: .75; }
            .uaz-mp-tabs button.is-active { border-bottom-color: var(--uaz-accent, var(--primary-600)); opacity: 1; font-weight: 600; }
            .uaz-mp-body { display: flex; flex: 1; min-height: 0; }
            .uaz-mp-main { display: flex; flex-direction: column; flex: 1; min-width: 0; }
            .uaz-mp-toolbar { display: flex; gap: .5rem; padding: .75rem 1rem; }
            .uaz-mp-input:focus { outline: 2px solid var(--uaz-accent, var(--primary-500)); outline-offset: 0; }
            .uaz-mp-input { width: 100%; padding: .5rem .75rem; border: 1px solid color-mix(in srgb, currentColor 25%, transparent); border-radius: .5rem; background: transparent; color: inherit; font-size: .9rem; }
            .uaz-mp-input--select { width: 12rem; flex: none; }
            .uaz-mp-grid { display: grid; grid-template-columns: repeat(auto-fill, 8.5rem); grid-auto-rows: 8.5rem; justify-content: space-between; gap: .625rem; padding: .25rem 1rem 1rem; overflow-y: auto; flex: 1; align-content: start; }
            .uaz-mp-tile { position: relative; display: block; width: 100%; height: 100%; padding: 0; border: 2px solid transparent; border-radius: .5rem; overflow: hidden; cursor: pointer; background: color-mix(in srgb, currentColor 8%, transparent); }
            .uaz-mp-tile img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .uaz-mp-tile:hover { border-color: color-mix(in srgb, var(--uaz-accent, var(--primary-600)) 55%, transparent); }
            .uaz-mp-tile.is-selected { border-color: var(--uaz-accent, var(--primary-600)); box-shadow: 0 0 0 2px var(--uaz-accent, var(--primary-600)); }
            .uaz-mp-tile__label { position: absolute; inset: auto 0 0 0; padding: .25rem .4rem; font-size: .7rem; color: #fff; background: linear-gradient(transparent, rgb(0 0 0 / .72)); text-align: left; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
            .uaz-mp-tile__check { position: absolute; top: .35rem; right: .35rem; width: 1.4rem; height: 1.4rem; display: none; align-items: center; justify-content: center; border-radius: 999px; background: var(--uaz-accent, var(--primary-600)); color: var(--primary-foreground, #fff); font-size: .8rem; }
            .uaz-mp-tile.is-selected .uaz-mp-tile__check { display: flex; }
            .uaz-mp-note { grid-column: 1 / -1; padding: 1rem 0; opacity: .7; font-size: .9rem; }
            .uaz-mp-more { grid-column: 1 / -1; justify-self: center; }
            .uaz-mp-side { width: 17rem; flex: none; padding: 1rem; overflow-y: auto; border-left: 1px solid color-mix(in srgb, currentColor 14%, transparent); font-size: .85rem; }
            .uaz-mp-side h3 { margin: 0 0 .75rem; font-weight: 600; }
            .uaz-mp-side__preview { width: 100%; max-height: 11rem; object-fit: contain; border-radius: .5rem; background: color-mix(in srgb, currentColor 8%, transparent); margin-bottom: .75rem; }
            .uaz-mp-side dt { opacity: .6; font-size: .75rem; margin-top: .5rem; }
            .uaz-mp-side dd { margin: 0; overflow-wrap: anywhere; }
            .uaz-mp-link { display: inline-block; margin-top: .75rem; color: var(--uaz-accent, var(--primary-600)); text-decoration: underline; }
            .dark .uaz-mp-link { color: var(--uaz-accent, var(--primary-400)); }
            .uaz-mp-body--upload { flex-direction: column; padding: 1rem 1.25rem; gap: 1rem; overflow-y: auto; }
            .uaz-mp-drop { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .25rem; padding: 2rem 1rem; border: 2px dashed color-mix(in srgb, currentColor 30%, transparent); border-radius: .75rem; cursor: pointer; text-align: center; }
            .uaz-mp-drop.is-over { border-color: var(--uaz-accent, var(--primary-600)); background: color-mix(in srgb, var(--uaz-accent, var(--primary-600)) 10%, transparent); }
            .uaz-mp-pending { list-style: none; margin: 0; padding: 0; display: grid; gap: .75rem; }
            .uaz-mp-pending li { display: flex; align-items: center; gap: .875rem; padding: .625rem; border: 1px solid color-mix(in srgb, currentColor 16%, transparent); border-radius: .5rem; }
            .uaz-mp-pending li > div { flex: 1; min-width: 0; display: grid; gap: .35rem; }
            .uaz-mp-pending img, .uaz-mp-pending__doc { width: 4rem; height: 4rem; object-fit: cover; border-radius: .375rem; flex: none; }
            .uaz-mp-pending__doc { display: flex; align-items: center; justify-content: center; background: #c0392b; color: #fff; font-weight: 700; }
            .uaz-mp-error { color: #dc2626; font-size: .875rem; }
            .uaz-mp-modal__foot { display: flex; align-items: center; justify-content: flex-end; gap: .625rem; padding: .75rem 1.25rem; border-top: 1px solid color-mix(in srgb, currentColor 14%, transparent); }
            .uaz-mp-modal__foot > span { margin-right: auto; }
            @media (max-width: 720px) { .uaz-mp-side { display: none; } .uaz-mp-modal { height: 100%; border-radius: 0; } .uaz-mp-overlay { padding: 0; } .uaz-mp-modal__head { gap: .75rem; flex-wrap: wrap; } }
        </style>
    @endonce
</x-dynamic-component>
