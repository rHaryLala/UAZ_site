<section class="bg-paper-50 py-14 md:py-20">
    <div class="container-site">
        @if (! empty($data['title']))<h2 class="mb-10 text-h2" data-reveal>{{ $data['title'] }}</h2>@endif
        <x-key-figures :figures="$data['items'] ?? []" />
    </div>
</section>
