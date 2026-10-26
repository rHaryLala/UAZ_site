@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="mt-14 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-ink-700">
            Page <span class="font-semibold text-ink-900">{{ $paginator->currentPage() }}</span> sur <span class="font-semibold text-ink-900">{{ $paginator->lastPage() }}</span>
        </p>

        <ul class="flex flex-wrap items-center gap-2">
            {{-- Précédent --}}
            <li>
                @if ($paginator->onFirstPage())
                    <span class="btn btn-outline btn-sm pointer-events-none opacity-40" aria-disabled="true"><x-ico name="arrow-left" class="size-4" /> Précédent</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-outline btn-sm"><x-ico name="arrow-left" class="size-4" /> Précédent</a>
                @endif
            </li>

            {{-- Numéros (masqués sur mobile) --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="hidden px-1 text-ink-600 sm:block" aria-hidden="true">{{ $element }}</li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li class="hidden sm:block">
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="inline-flex size-10 items-center justify-center rounded-full bg-navy-800 text-sm font-semibold text-white">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="inline-flex size-10 items-center justify-center rounded-full text-sm font-semibold text-navy-800 hover:bg-paper-300" aria-label="Aller à la page {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            {{-- Suivant --}}
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-navy btn-sm">Suivant <x-ico name="arrow-right" class="size-4" /></a>
                @else
                    <span class="btn btn-navy btn-sm pointer-events-none opacity-40" aria-disabled="true">Suivant <x-ico name="arrow-right" class="size-4" /></span>
                @endif
            </li>
        </ul>
    </nav>
@endif
