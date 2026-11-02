<div>
    {{-- Région « status » : annoncée par les lecteurs d'écran après l'envoi --}}
    <div role="status" aria-live="polite">
        @if ($sent)
            <div class="rounded-card border border-gold-300 bg-paper-50 p-8 shadow-card" x-data x-init="$el.scrollIntoView({ block: 'center', behavior: 'smooth' })">
                <p class="flex items-center gap-3 font-serif text-h4 text-navy-800"><x-ico name="check" class="size-6 text-gold-600" /> {{ __('Merci, votre message a bien été envoyé.') }}</p>
                <p class="mt-3 text-ink-700">{{ __('Notre équipe vous répondra dans les meilleurs délais.') }}</p>
                <button type="button" wire:click="another" class="link-arrow mt-6">{{ __('Envoyer un autre message') }}</button>
            </div>
        @endif
    </div>

    @unless ($sent)
        <form wire:submit="send" class="grid gap-6 sm:grid-cols-2" novalidate>
            <div>
                <label for="cf-name" class="field-label">{{ __('Nom complet') }} <span class="text-ink-600">{{ __('(obligatoire)') }}</span></label>
                <input id="cf-name" type="text" wire:model="name" class="field-input" autocomplete="name" required @error('name') aria-invalid="true" aria-describedby="cf-name-error" @enderror>
                @error('name')<p id="cf-name-error" class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="cf-email" class="field-label">{{ __('Adresse e-mail') }} <span class="text-ink-600">{{ __('(obligatoire)') }}</span></label>
                <input id="cf-email" type="email" wire:model="email" class="field-input" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="cf-email-error" @enderror>
                @error('email')<p id="cf-email-error" class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="cf-phone" class="field-label">{{ __('Téléphone') }} <span class="text-ink-600">{{ __('(facultatif)') }}</span></label>
                <input id="cf-phone" type="tel" wire:model="phone" class="field-input" autocomplete="tel" @error('phone') aria-invalid="true" aria-describedby="cf-phone-error" @enderror>
                @error('phone')<p id="cf-phone-error" class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="cf-subject" class="field-label">{{ __('Sujet') }} <span class="text-ink-600">{{ __('(obligatoire)') }}</span></label>
                <input id="cf-subject" type="text" wire:model="subject" class="field-input" required @error('subject') aria-invalid="true" aria-describedby="cf-subject-error" @enderror>
                @error('subject')<p id="cf-subject-error" class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="sm:col-span-2">
                <label for="cf-message" class="field-label">{{ __('Message') }} <span class="text-ink-600">{{ __('(obligatoire)') }}</span></label>
                <textarea id="cf-message" wire:model="message" rows="6" class="field-input" required @error('message') aria-invalid="true" aria-describedby="cf-message-error" @enderror></textarea>
                @error('message')<p id="cf-message-error" class="field-error">{{ $message }}</p>@enderror
            </div>

            {{-- Champ piège : invisible pour les humains, hors navigation clavier, rempli par les robots --}}
            <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                <label for="cf-website">{{ __('Ne pas remplir ce champ') }}</label>
                <input id="cf-website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="sm:col-span-2 flex flex-wrap items-center gap-4">
                <x-button type="submit" variant="navy" arrow wire:loading.attr="disabled" wire:target="send">
                    <span wire:loading.remove wire:target="send">{{ __('Envoyer le message') }}</span>
                    <span wire:loading wire:target="send">{{ __('Envoi en cours…') }}</span>
                </x-button>
                <p class="text-sm text-ink-600">{{ __('Vos données ne servent qu\'à répondre à votre demande.') }}</p>
            </div>
        </form>
    @endunless
</div>
