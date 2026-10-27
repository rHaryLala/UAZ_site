{{-- « Charger plus » de la médiathèque en grille, façon WordPress : la suite s'ajoute à la grille quand on arrive en bas (ou au clic),
     sans numéros de page. Styles en ligne : le CSS de Filament ne contient pas les classes Tailwind du site. --}}
@php($counts = $counts ?? ["shown" => 0, "total" => 0])
<style>
    .fi-ta .fi-pagination { display: none; }
</style>
<div
    class="fi-media-more"
    style="display:flex; flex-direction:column; align-items:center; gap:.5rem; padding:1.25rem 0 .5rem"
    x-data="{
        observer: null,
        init() {
            if (! ('IntersectionObserver' in window)) return;
            this.observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting) && this.$refs.more && ! this.busy) {
                    this.$wire.loadMoreMedia();
                }
            }, { rootMargin: '600px 0px' });
            this.observer.observe(this.$refs.sentinel);
        },
        busy: false,
        destroy() { this.observer?.disconnect(); },
    }"
    x-on:livewire-upload-start.window="busy = true"
>
    <span x-ref="sentinel" style="display:block; width:1px; height:1px"></span>
    <p style="font-size:.85rem; opacity:.7; margin:0">{{ $counts['shown'] }} sur {{ $counts['total'] }} fichier(s) affiché(s)</p>
    @if ($counts['shown'] < $counts['total'])
        <button
            type="button"
            x-ref="more"
            wire:click="loadMoreMedia"
            wire:loading.attr="disabled"
            wire:target="loadMoreMedia"
            class="fi-btn fi-color-gray"
            style="padding:.5rem 1.25rem; border:1px solid currentColor; border-radius:.5rem; font-weight:600; cursor:pointer; background:transparent; color:inherit"
        >
            <span wire:loading.remove wire:target="loadMoreMedia">Charger plus</span>
            <span wire:loading wire:target="loadMoreMedia">Chargement…</span>
        </button>
    @endif
</div>
