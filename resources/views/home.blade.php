@php
    $title = null; // l'accueil n'ajoute rien au nom du site
    $description = $site->home_hero_subtitle ?: __('home.hero.subtitle');
@endphp

<x-layouts.app :title="$homePage?->seo_title" :description="$homePage?->metaDescription() ?: $description" :image="$homePage?->metaImage()" header-tone="dark">
    @include('home.hero')
    @include('home.difference')
    @include('home.future')
    @include('home.formations')
    @include('home.admissions')
    @include('home.life')
    @include('home.practice')

    @if ($testimonials->isNotEmpty())
        @include('home.testimonials')
    @endif

    @if ($news->isNotEmpty())
        @include('home.news')
    @endif

    @if ($events->isNotEmpty())
        @include('home.events')
    @endif

    @include('home.resources')

    @if ($partners->isNotEmpty())
        @include('home.partners')
    @endif

    @push('head')
        @include('partials.jsonld-organization', ['site' => $site])
    @endpush

    @include('home.closing')
</x-layouts.app>
