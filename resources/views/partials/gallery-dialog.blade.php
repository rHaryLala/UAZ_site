{{-- Visionneuse plein écran (à placer dans un élément x-data="lightbox" ; voir resources/js/components/lightbox.js). $dialogLabel : nom accessible. --}}
<dialog x-ref="dialog" aria-label="{{ $dialogLabel ?? 'Galerie' }}" @keydown.arrow-right="next" @keydown.arrow-left="prev" class="m-auto h-dvh max-h-none w-screen max-w-none overflow-hidden bg-navy-950/95 p-0 text-white backdrop:bg-navy-950 open:flex">
    <div class="relative flex size-full flex-col items-center justify-center p-4 md:p-10">
        <button type="button" @click="close" class="absolute top-4 right-4 inline-flex size-12 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900 md:top-6 md:right-6" aria-label="{{ __('Fermer la visionneuse') }}"><x-ico name="close" class="size-5" /></button>
        <button type="button" @click="prev" class="absolute top-1/2 left-3 inline-flex size-12 -translate-y-1/2 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900 md:left-6" aria-label="{{ __('Photo précédente') }}"><x-ico name="arrow-left" class="size-5" /></button>
        <button type="button" @click="next" class="absolute top-1/2 right-3 inline-flex size-12 -translate-y-1/2 items-center justify-center rounded-none border border-white/40 hover:bg-white hover:text-navy-900 md:right-6" aria-label="{{ __('Photo suivante') }}"><x-ico name="arrow-right" class="size-5" /></button>

        <img x-bind:src="current.src" x-bind:alt="current.alt" class="max-h-[78dvh] max-w-full rounded-lg object-contain">
        <p class="mt-4 max-w-2xl text-center text-sm text-navy-100"><span x-text="current.caption"></span> <span class="tabular-nums text-gold-300" x-text="counter" aria-live="polite"></span></p>
    </div>
</dialog>
