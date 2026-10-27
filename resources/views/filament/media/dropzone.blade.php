{{-- Zone de glisser-déposer de la médiathèque : les fichiers déposés (n'importe où sur la page) ouvrent le formulaire d'envoi, déjà rempli.
     Styles en ligne : le CSS de Filament ne contient pas les classes Tailwind du site. --}}
<div
    x-data="{
        over: false,
        count: 0,
        send(files) {
            if (! files || ! files.length) return;
            $refs.input.files = files;
            $refs.input.dispatchEvent(new Event('change', { bubbles: true }));
        },
    }"
    x-on:dragenter.window.prevent="if ($event.dataTransfer?.types?.includes('Files')) { count++; over = true }"
    x-on:dragleave.window.prevent="count = Math.max(0, count - 1); if (count === 0) over = false"
    x-on:dragover.window.prevent
    x-on:drop.window.prevent="count = 0; over = false; send($event.dataTransfer.files)"
>
    <label
        class="fi-media-dropzone"
        x-bind:style="{ borderColor: over ? 'var(--primary-500)' : '', background: over ? 'color-mix(in srgb, var(--primary-500) 10%, transparent)' : '' }"
        style="display:flex; align-items:center; justify-content:center; gap:.75rem; padding:1.25rem 1rem; margin-bottom:1rem; border:2px dashed #94a3b8; border-radius:.75rem; cursor:pointer; text-align:center; transition:border-color .15s, background .15s"
    >
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M5 20h14"/></svg>
        <span>
            <strong x-text="over ? 'Relâchez pour envoyer' : 'Glissez-déposez vos images ici'">Glissez-déposez vos images ici</strong>
            <span style="opacity:.7"> ou cliquez pour parcourir · JPEG, PNG, WebP, PDF</span>
        </span>
        <span wire:loading wire:target="dropped" style="font-weight:600">Envoi en cours…</span>
        <input x-ref="input" type="file" wire:model="dropped" multiple accept="image/jpeg,image/png,image/webp,application/pdf" style="display:none">
    </label>
    @error('dropped.*')<p style="color:#b3261e; font-size:.875rem; margin-bottom:.75rem">{{ $message }}</p>@enderror
</div>
