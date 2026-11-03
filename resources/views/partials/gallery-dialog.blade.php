{{-- Visionneuse plein écran (à placer dans un élément x-data="lightbox" ; voir resources/js/components/lightbox.js). $dialogLabel : nom accessible. --}}
<dialog x-ref="dialog" aria-label="{{ $dialogLabel ?? 'Galerie' }}" @keydown.arrow-right="next" @keydown.arrow-left="prev" @keydown="onKey" class="m-auto h-dvh max-h-none w-screen max-w-none overflow-hidden bg-navy-950/95 p-0 text-white backdrop:bg-navy-950 open:flex">
    <div class="relative flex size-full flex-col items-center justify-center overflow-hidden p-4 md:p-10">
        <button type="button" @click="close" class="absolute top-4 right-4 z-10 inline-flex size-12 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900 md:top-6 md:right-6" aria-label="{{ __('Fermer la visionneuse') }}"><x-ico name="close" class="size-5" /></button>
        <button type="button" @click="prev" class="absolute top-1/2 left-3 z-10 inline-flex size-12 -translate-y-1/2 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900 md:left-6" aria-label="{{ __('Photo précédente') }}"><x-ico name="arrow-left" class="size-5" /></button>
        <button type="button" @click="next" class="absolute top-1/2 right-3 z-10 inline-flex size-12 -translate-y-1/2 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900 md:right-6" aria-label="{{ __('Photo suivante') }}"><x-ico name="arrow-right" class="size-5" /></button>

        {{-- Zoom : − / taille actuelle (un clic revient à 100 %) / +. Aussi : molette, double-clic, pincement, touches + − 0. --}}
        <div class="absolute top-4 left-4 z-10 flex items-center md:top-6 md:left-6" role="group" aria-label="{{ __('Zoom') }}">
            <button type="button" @click="zoomOut" class="inline-flex size-12 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900" aria-label="{{ __('Zoom arrière') }}"><x-ico name="zoom-out" class="size-5" /></button>
            <button type="button" @click="resetZoom" class="-mx-px inline-flex h-12 min-w-20 items-center justify-center rounded-none border border-white/40 px-3 text-sm tabular-nums hover:bg-white hover:text-navy-900" aria-label="{{ __('Taille d\'origine') }}"><span x-text="zoomLabel">100 %</span></button>
            <button type="button" @click="zoomIn" class="inline-flex size-12 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900" aria-label="{{ __('Zoom avant') }}"><x-ico name="zoom-in" class="size-5" /></button>
        </div>

        <img x-ref="photo" x-bind:src="current.src" x-bind:alt="current.alt" class="max-h-[78dvh] max-w-full touch-none rounded-lg object-contain select-none" style="cursor: zoom-in">
        <p class="mt-4 max-w-2xl text-center text-sm text-navy-100"><span x-text="current.caption"></span> <span class="tabular-nums text-gold-300" x-text="counter" aria-live="polite"></span></p>
    </div>
</dialog>
