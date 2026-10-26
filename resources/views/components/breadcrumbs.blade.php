@props(['items' => [], 'dark' => false])

{{-- Fil d'Ariane : [['label' => 'Formations', 'url' => '/formations'], ['label' => 'Licence en Droit']] ; le dernier est la page courante. --}}
<nav aria-label="Fil d'Ariane" {{ $attributes }}>
    <ol @class(['flex flex-wrap items-center gap-x-2 gap-y-1 text-sm', 'text-navy-100' => $dark, 'text-ink-600' => ! $dark])>
        <li><a href="{{ url('/') }}" @class(['underline-offset-4 hover:underline', 'hover:text-white' => $dark, 'hover:text-navy-800' => ! $dark])>Accueil</a></li>
        @foreach ($items as $item)
            <li class="flex items-center gap-2">
                <x-ico name="chevron-right" class="size-3.5 opacity-60" />
                @if (! $loop->last && ! empty($item['url']))
                    <a href="{{ $item['url'] }}" @class(['underline-offset-4 hover:underline', 'hover:text-white' => $dark, 'hover:text-navy-800' => ! $dark])>{{ $item['label'] }}</a>
                @else
                    <span aria-current="page" @class(['font-medium', 'text-white' => $dark, 'text-ink-900' => ! $dark])>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
