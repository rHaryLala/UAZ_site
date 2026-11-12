{{-- Fenêtre « S'abonner à la lettre d'information » : s'ouvre seule à la première visite (puis tous les 20 jours tant que la personne n'est pas
     abonnée, sur le même navigateur) ou depuis « S'abonner » dans la barre du haut. Logique : resources/js/newsletter.js. --}}
<dialog data-newsletter-dialog data-delay-days="20" class="student-dialog" aria-labelledby="newsletter-title">
    <div class="ornament relative bg-white p-8 text-ink-800 shadow-lift sm:p-10">
        <button type="button" data-newsletter-close class="absolute top-4 right-4 z-20 inline-flex size-10 items-center justify-center text-navy-900 hover:bg-sun-400" aria-label="{{ __('Fermer') }}">
            <x-ico name="close" class="size-5" />
        </button>

        <div data-newsletter-form-wrap>
            <p class="eyebrow mb-3">{{ __('Lettre d\'information') }}</p>
            <h2 id="newsletter-title" class="font-serif text-2xl text-navy-900 sm:text-3xl">{{ __('Restez proche de l\'UAZ') }}</h2>
            <span class="mt-4 mb-5 block h-0.5 w-12 bg-sun-400" aria-hidden="true"></span>
            <p class="mb-6 text-ink-700">{{ __('Actualités, événements et nouvelles du campus directement dans votre boîte mail. Vous pouvez vous désinscrire à tout moment.') }}</p>

            <form data-newsletter-form action="{{ route('newsletter.subscribe') }}" method="post" novalidate>
                @csrf
                <label for="newsletter-email" class="field-label">{{ __('Adresse e-mail') }}</label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input id="newsletter-email" name="email" type="email" class="field-input sm:flex-1" autocomplete="email" inputmode="email" required placeholder="{{ __('vous@exemple.com') }}" aria-describedby="newsletter-feedback">
                    <x-button type="submit" variant="navy" class="btn-square sm:shrink-0" data-newsletter-submit>{{ __('S\'abonner') }}</x-button>
                </div>

                {{-- Champ piège : invisible pour les humains, hors navigation clavier, rempli par les robots --}}
                <div class="absolute -left-[9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                    <label for="newsletter-website">{{ __('Ne pas remplir ce champ') }}</label>
                    <input id="newsletter-website" type="text" name="website" tabindex="-1" autocomplete="off">
                </div>

                <p id="newsletter-feedback" data-newsletter-error class="field-error mt-3" role="alert" hidden></p>
                <p class="mt-4 text-xs text-ink-600">{{ __('Nous n\'utilisons votre adresse que pour cette lettre.') }}
                    @if ($privacyPage ?? null)
                        <a href="{{ $privacyPage->url() }}" class="underline underline-offset-4">{{ __('En savoir plus') }}</a>
                    @endif
                </p>
            </form>
        </div>

        <div data-newsletter-done hidden role="status" aria-live="polite">
            <p class="eyebrow mb-3">{{ __('Lettre d\'information') }}</p>
            <p class="flex items-center gap-3 font-serif text-2xl text-navy-900"><x-ico name="check" class="size-6 shrink-0 text-gold-600" /> {{ __('Vous êtes abonné(e)') }}</p>
            <span class="mt-4 mb-5 block h-0.5 w-12 bg-sun-400" aria-hidden="true"></span>
            <p class="text-ink-700" data-newsletter-message></p>
            <button type="button" data-newsletter-close class="link-arrow mt-6">{{ __('Fermer') }}</button>
        </div>
    </div>
</dialog>
