{{-- Partenaires : un ruban de logos qui défile ; il ralentit au survol. Une seule rangée sans mouvement si l'on préfère moins d'animations. --}}
<section class="home-partners" data-home="partners" aria-labelledby="partners-title">
    <div class="container-site">
        <h2 id="partners-title" class="eyebrow mb-8 text-center" data-reveal>{{ __('home.partners.title') }}</h2>
    </div>

    <div class="home-partners__belt" data-home-belt-partners>
        <ul class="home-partners__track" data-home-partners-track>
            @foreach ($partners as $partner)
                <li>
                    @if ($partner->url)
                        <a href="{{ $partner->url }}" target="_blank" rel="noopener noreferrer" class="block" aria-label="{{ __(':name (nouvel onglet)', ['name' => $partner->name]) }}">
                            @if ($partner->logo)<x-picture :asset="$partner->logo" conversion="thumb" sizes="160px" :alt="$partner->name" class="home-partners__logo" />@else<span class="home-partners__name">{{ $partner->name }}</span>@endif
                        </a>
                    @elseif ($partner->logo)
                        <x-picture :asset="$partner->logo" conversion="thumb" sizes="160px" :alt="$partner->name" class="home-partners__logo" />
                    @else
                        <span class="home-partners__name">{{ $partner->name }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
