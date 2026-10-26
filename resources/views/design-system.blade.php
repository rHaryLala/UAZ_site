<x-layouts.app title="Design system" description="Catalogue des couleurs, typographies et composants du site de l'UAZ." noindex>
    <x-page-hero
        eyebrow="Design system"
        title="Esprit, Main, Intelligence — au Service"
        intro="Spiritus · Manus · Mens · Service. Couleurs extraites du sceau, deux familles typographiques auto-hébergées, composants accessibles (WCAG AA)."
        :breadcrumbs="[['label' => 'Design system']]"
    />

    {{-- Navigation interne --}}
    <nav aria-label="Sections du catalogue" class="sticky top-[var(--header-h)] z-30 border-b border-paper-300 bg-paper-100/95 backdrop-blur">
        <ul class="container-site flex gap-1 overflow-x-auto py-2 text-sm font-medium whitespace-nowrap">
            @foreach (['couleurs' => 'Couleurs', 'typographie' => 'Typographie', 'boutons' => 'Boutons', 'cartes' => 'Cartes', 'formulaires' => 'Formulaires', 'blocs' => 'Blocs', 'motifs' => 'Motifs', 'etats' => 'États'] as $id => $label)
                <li><a href="#{{ $id }}" class="rounded-full px-4 py-2 hover:bg-paper-300">{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>

    {{-- COULEURS --}}
    <x-section id="couleurs" eyebrow="Fondations" title="Couleurs" intro="Trois couleurs du sceau — bleu marine #232E66, or/bronze #8A724B, noir #060807 — plus le blanc, avec des nuances dérivées en OKLCH.">
        <div class="space-y-10">
            @foreach ($palette as $name => $steps)
                <div>
                    <h3 class="mb-4 font-sans text-sm font-semibold tracking-[0.14em] text-ink-700 uppercase">{{ $name }}</h3>
                    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-11">
                        @foreach ($steps as $step => $hex)
                            <li class="overflow-hidden rounded-lg border border-paper-300 bg-paper-50 text-xs">
                                <div class="h-16" style="background: {{ $hex }}"></div>
                                <div class="p-2"><span class="block font-semibold text-ink-900">{{ $name }}-{{ $step }}</span><span class="text-ink-600">{{ $hex }}</span></div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <div>
                <h3 class="mb-4 font-sans text-sm font-semibold tracking-[0.14em] text-ink-700 uppercase">Contrastes autorisés (WCAG AA : 4,5 : 1 texte, 3 : 1 grand texte)</h3>
                <ul class="grid gap-3 md:grid-cols-2">
                    @foreach ($contrasts as $c)
                        <li class="flex items-center gap-4 rounded-lg border border-paper-300 bg-paper-50 p-3">
                            <span class="flex h-14 w-24 shrink-0 items-center justify-center rounded-md font-serif text-2xl" style="background: {{ $c['bg'] }}; color: {{ $c['fg'] }}">Aa</span>
                            <span class="text-sm"><span class="block font-semibold text-ink-900">{{ $c['label'] }}</span>
                                <span class="text-ink-700">{{ number_format($c['ratio'], 2, ',', ' ') }} : 1 — {{ $c['ratio'] >= 7 ? 'AAA' : ($c['ratio'] >= 4.5 ? 'AA' : ($c['ratio'] >= 3 ? 'AA grand texte seulement' : 'insuffisant')) }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-section>

    {{-- TYPOGRAPHIE --}}
    <x-section id="typographie" tone="white" eyebrow="Fondations" title="Typographie" intro="Fraunces (titres, serif expressif à axe optique) et Inter (texte), auto-hébergées, latin uniquement, font-display: swap.">
        <div class="grid gap-12 lg:grid-cols-[1fr_1.2fr]">
            <div class="space-y-6 text-sm text-ink-700">
                <p><span class="eyebrow">Eyebrow</span> — surtitre capitales espacées, or 700 sur clair, or 300 sur marine.</p>
                <p><strong class="text-ink-900">Fraunces</strong> pour <code>h1–h4</code>, chiffres clés et citations.</p>
                <p><strong class="text-ink-900">Inter</strong> pour le texte courant, les formulaires et l'interface.</p>
                <p>Échelle fluide : de 390 px à 1440 px, aucune taille fixe en pixels.</p>
            </div>
            <div class="space-y-8">
                <p class="font-serif text-display text-navy-800">Display</p>
                <h1 class="text-h1">Titre 1 — Préparer les leaders de demain</h1>
                <h2 class="text-h2">Titre 2 — Une formation, un avenir</h2>
                <h3 class="text-h3">Titre 3 — Campus moderne et verdoyant</h3>
                <h4 class="text-h4">Titre 4 — Vie étudiante dynamique</h4>
                <p class="text-lead text-ink-700">Texte d'introduction (lead) : Excellence académique, vie étudiante dynamique et développement personnel.</p>
                <p>Texte courant. L'UAZ combine professeurs permanents expérimentés, campus moderne, bibliothèque, laboratoires, espaces de détente et accompagnement professionnel et spirituel. <a href="#" class="font-medium text-navy-800 underline decoration-gold-500 decoration-2 underline-offset-4">Lien dans un paragraphe</a>.</p>
            </div>
        </div>
    </x-section>

    {{-- BOUTONS --}}
    <x-section id="boutons" eyebrow="Composants" title="Boutons et liens">
        <div class="grid gap-8 md:grid-cols-2">
            <div class="rounded-card bg-paper-50 p-8 shadow-card">
                <p class="mb-5 text-sm font-semibold text-ink-700">Sur fond clair</p>
                <div class="flex flex-wrap gap-4">
                    <x-button href="#" variant="navy" arrow>Principal</x-button>
                    <x-button href="#" variant="outline">Secondaire</x-button>
                    <x-button href="#" variant="navy" size="sm">Petit</x-button>
                    <x-button variant="navy" disabled class="opacity-50">Désactivé</x-button>
                </div>
                <p class="mt-6"><a href="#" class="link-arrow">Lien avec flèche <x-ico name="arrow-right" class="size-4" /></a></p>
            </div>
            <div class="on-dark rounded-card bg-navy-800 p-8 text-white shadow-card">
                <p class="mb-5 text-sm font-semibold text-navy-100">Sur fond marine</p>
                <div class="flex flex-wrap gap-4">
                    <x-button href="#" variant="gold" arrow>Principal</x-button>
                    <x-button href="#" variant="outline">Secondaire</x-button>
                </div>
                <p class="mt-6"><a href="#" class="link-arrow">Lien avec flèche <x-ico name="arrow-right" class="size-4" /></a></p>
            </div>
        </div>
    </x-section>

    {{-- CARTES --}}
    <x-section id="cartes" tone="sand" eyebrow="Composants" title="Cartes" intro="Survolez les cartes : élévation, zoom d'image et résumé révélé. Tout le contenu reste accessible au clavier.">
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <x-news-card :post="$post" />
            <x-formation-card :formation="$formation" class="lg:col-span-1" />
            <div class="space-y-6">
                <x-event-card :event="$event" />
                <div class="on-dark rounded-card bg-navy-800 p-8"><x-testimonial-card :testimonial="$testimonial" /></div>
            </div>
        </div>
        <div class="mt-12 grid max-w-3xl grid-cols-2 gap-6 sm:grid-cols-3">
            <x-person-card :member="$member" />
        </div>
    </x-section>

    {{-- FORMULAIRES --}}
    <x-section id="formulaires" eyebrow="Composants" title="Formulaires" intro="Libellés visibles, aide et erreurs reliées aux champs (aria-describedby), zones tactiles de 48 px minimum.">
        <form class="grid max-w-3xl gap-6 md:grid-cols-2" onsubmit="return false">
            <div>
                <label for="ds-name" class="field-label">Nom complet</label>
                <input id="ds-name" class="field-input" type="text" placeholder="Prénom Nom" autocomplete="name">
            </div>
            <div>
                <label for="ds-mail" class="field-label">Adresse e-mail</label>
                <input id="ds-mail" class="field-input" type="email" value="invalide" aria-invalid="true" aria-describedby="ds-mail-error" autocomplete="email">
                <p id="ds-mail-error" class="field-error">Saisissez une adresse e-mail valide.</p>
            </div>
            <div class="md:col-span-2">
                <label for="ds-subject" class="field-label">Sujet</label>
                <select id="ds-subject" class="field-input"><option>Admissions</option><option>Formations</option><option>Autre</option></select>
                <p class="field-help">Choisissez le service concerné.</p>
            </div>
            <div class="md:col-span-2">
                <label for="ds-msg" class="field-label">Message</label>
                <textarea id="ds-msg" class="field-input" rows="4"></textarea>
            </div>
            <div class="md:col-span-2"><x-button type="submit" variant="navy" arrow>Envoyer</x-button></div>
        </form>
    </x-section>

    {{-- BLOCS --}}
    <x-section id="blocs" tone="white" eyebrow="Composants" title="Blocs de page">
        <div class="space-y-16">
            <x-key-figures :figures="[['value' => 1995, 'label' => 'Fondation de l\'UAZ'], ['value' => 7, 'label' => 'Mentions de licence'], ['value' => 180, 'label' => 'Crédits ECTS en licence'], ['value' => 200, 'suffix' => '+', 'label' => 'Hectares de campus']]" />
            <x-breadcrumbs :items="[['label' => 'Formations', 'url' => '#'], ['label' => 'Licence en Informatique']]" />
            {{ $paginator->links() }}
        </div>
    </x-section>
    <x-cta-banner eyebrow="Votre avenir se dessine" title="Préparer aujourd'hui les leaders de demain." text="Admission après le baccalauréat selon la mention et les critères de chaque filière."
        :primary="['label' => 'Candidature et admission', 'url' => '#']" :secondary="['label' => 'Informations d\'admission', 'url' => '#']" />

    {{-- MOTIFS --}}
    <x-section id="motifs" eyebrow="Identité" title="Motifs graphiques" intro="Le triangle et la silhouette de Madagascar du sceau servent de filigranes, de séparateurs et de masques. Le logo lui-même n'est jamais déformé.">
        <div class="grid gap-6 sm:grid-cols-3">
            <div class="flex aspect-square items-center justify-center rounded-card bg-navy-800 p-10"><x-seal :size="192" class="size-40" /></div>
            <div class="flex aspect-square items-center justify-center rounded-card bg-navy-900 p-10 text-gold-300"><x-triangle class="w-full" /></div>
            <div class="flex aspect-square items-center justify-center rounded-card bg-navy-800 p-10 text-gold-300"><x-madagascar class="h-full" /></div>
        </div>
    </x-section>

    {{-- ÉTATS --}}
    <x-section id="etats" tone="sand" eyebrow="Accessibilité" title="États">
        <ul class="grid gap-4 text-sm md:grid-cols-3">
            <li class="rounded-card bg-paper-50 p-6 shadow-card"><strong class="text-ink-900">Focus visible</strong><p class="mt-2 text-ink-700">Anneau 3 px marine (or clair sur fond sombre). Naviguez au clavier avec Tab :</p><p class="mt-4"><a href="#" class="btn btn-outline btn-sm">Bouton focusable</a></p></li>
            <li class="rounded-card bg-paper-50 p-6 shadow-card"><strong class="text-ink-900">Mouvement réduit</strong><p class="mt-2 text-ink-700">Avec « réduire les animations », le contenu s'affiche directement : aucune translation ni défilement fluide.</p></li>
            <li class="rounded-card bg-paper-50 p-6 shadow-card"><strong class="text-ink-900">Sans JavaScript</strong><p class="mt-2 text-ink-700">Tout le contenu est dans le HTML. Les animations n'ajoutent que de l'effet ; le menu s'affiche à plat.</p></li>
        </ul>
    </x-section>
</x-layouts.app>
