{{-- Choix du modèle d'une lettre neuve : chaque carte montre la lettre telle qu'elle partirait (aperçu réduit, non cliquable). --}}
@php
    $names = \App\Support\NewsletterTemplates::list();
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="nlp">
        @foreach ($names as $key => $meta)
            <label class="nlp__card">
                <input type="radio" class="nlp__radio" name="{{ $getStatePath() }}-choice" wire:model.live="{{ $getStatePath() }}" value="{{ $key }}">
                <span class="nlp__view" wire:ignore>
                    <iframe srcdoc="{{ \App\Support\NewsletterRenderer::html(\App\Support\NewsletterTemplates::build($key)) }}" sandbox tabindex="-1" loading="lazy" title="Aperçu du modèle {{ $meta['name'] }}"></iframe>
                </span>
                <span class="nlp__name">{{ $meta['name'] }}</span>
                <span class="nlp__desc">{{ $meta['description'] }}</span>
            </label>
        @endforeach
    </div>

    <style>
        .nlp { display: grid; grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr)); gap: 1rem; }
        .nlp__card { position: relative; display: flex; flex-direction: column; gap: .25rem; padding: .6rem; border: 1px solid rgb(0 0 0 / .1); border-radius: .75rem; background: #fff; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
        :is(.dark) .nlp__card { background: rgb(255 255 255 / .04); border-color: rgb(255 255 255 / .12); }
        .nlp__card:hover { border-color: rgb(35 46 102 / .5); }
        .nlp__card:has(.nlp__radio:checked) { border-color: #232e66; box-shadow: 0 0 0 2px #232e66; }
        .nlp__card:has(.nlp__radio:focus-visible) { outline: 2px solid #3b6cff; outline-offset: 2px; }
        .nlp__radio { position: absolute; opacity: 0; pointer-events: none; }
        .nlp__view { position: relative; display: block; height: 14rem; overflow: hidden; border-radius: .5rem; background: #eef0f6; }
        .nlp__view iframe { position: absolute; top: 0; left: 50%; width: 660px; height: 1100px; border: 0; transform: translateX(-50%) scale(.3); transform-origin: top center; pointer-events: none; }
        .nlp__name { margin-top: .3rem; font-weight: 600; font-size: .9rem; }
        .nlp__desc { font-size: .78rem; line-height: 1.4; opacity: .7; }
    </style>
</x-dynamic-component>
