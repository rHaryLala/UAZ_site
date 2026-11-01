@php
    use App\Support\FeeCatalog;

    $f = fn (?int $n) => FeeCatalog::format($n);
    $years = $fees['licence']['years'];
    $departments = $fees['licence']['departments'];
    $tracks = $fees['master']['tracks'];
    $finance = $fees['finance'];
    $posterLabels = ['Sciences infirmières · semestre 1', 'Sciences infirmières · semestre 2', 'Autres départements · semestre 1', 'Autres départements · semestre 2', 'Master M1 · semestres 1 et 2', 'Master M2 · semestre 2'];

    // Fond de registre : lignes horizontales et deux doubles filets verticaux (tracés par GSAP au chargement).
    $ruleYs = [60, 120, 180, 240, 300, 360, 420, 480, 540];
@endphp

<x-layouts.app :title="$page->metaTitle()" :description="$page->metaDescription()" :image="$page->metaImage()" :noindex="$page->status !== \App\Enums\PublicationStatus::Published">
<div class="fee" data-fees>
    {{-- 1. LE REGISTRE : un grand livre de comptes se trace, le titre s'écrit (animations : resources/js/animations/fees.js) --}}
    <header class="fee-hero" data-fee-hero aria-labelledby="fee-title">
        <svg class="fee-ledger" viewBox="0 0 1200 600" preserveAspectRatio="none" aria-hidden="true" focusable="false">
            @foreach ($ruleYs as $y)<path data-fee-rule d="M0 {{ $y }} H1200" />@endforeach
            <path data-fee-rule class="is-double" d="M820 0 V600" />
            <path data-fee-rule class="is-double" d="M826 0 V600" />
            <path data-fee-rule class="is-double" d="M1010 0 V600" />
            <path data-fee-rule class="is-double" d="M1016 0 V600" />
        </svg>

        <div class="container-site fee-hero__inner">
            <x-breadcrumbs :items="$trail" />
            <p class="fee-badge" data-fee-hide data-fee-fade>Année académique {{ $fees['year'] }} · montants en Ariary</p>
            <h1 id="fee-title" class="fee-title" data-fee-hide data-fee-title>Frais académiques</h1>
            <p class="fee-hero__lead" data-fee-hide data-fee-fade>Une information claire et transparente sur les coûts de vos études&nbsp;: choisissez votre parcours, le relevé se dresse sous vos yeux.</p>
            <div class="fee-hero__cta" data-fee-hide data-fee-fade>
                <a href="#fee-sim" class="fee-btn">Calculer mes frais</a>
                <a href="#fee-tables" class="link-arrow">Voir les tableaux officiels</a>
            </div>
        </div>
    </header>

    {{-- 2. LE SIMULATEUR : on compose son parcours, le relevé s'imprime (logique : resources/js/fees-sim.js, sans JavaScript : tableaux plus bas) --}}
    <section id="fee-sim" class="fee-sim" data-fee-sim data-fee-section hidden aria-labelledby="fee-sim-title">
        <script type="application/json" data-fee-catalog>{!! json_encode(['catalog' => $catalog, 'groups' => $fees['groups'], 'years' => $years, 'departments' => collect($departments)->map(fn ($d) => $d['label']), 'tracks' => collect($tracks)->map(fn ($t) => $t['label']), 'test' => $fees['test_fee']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

        <div class="container-site">
            <h2 id="fee-sim-title" class="fee-h2" data-reveal>Votre relevé de frais</h2>

            <div class="fee-sim__grid">
                <form class="fee-controls" data-fee-controls aria-label="Composer son parcours">
                    <fieldset class="fee-field">
                        <legend>Niveau</legend>
                        <div class="fee-chips">
                            <label class="fee-chip"><input type="radio" name="fee-level" value="licence" checked><span>Licence</span></label>
                            <label class="fee-chip"><input type="radio" name="fee-level" value="master"><span>Master</span></label>
                        </div>
                    </fieldset>

                    <fieldset class="fee-field" data-fee-for="licence">
                        <legend>Département</legend>
                        <div class="fee-chips">
                            @foreach ($departments as $key => $department)
                                <label class="fee-chip"><input type="radio" name="fee-dept" value="{{ $key }}" @checked($loop->first)><span>{{ $department['label'] }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="fee-field" data-fee-for="licence">
                        <legend>Année d'études</legend>
                        <div class="fee-chips">
                            @foreach ($years as $key => $label)
                                <label class="fee-chip"><input type="radio" name="fee-year" value="{{ $key }}" @checked($key === 'l1')><span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="fee-field" data-fee-for="licence">
                        <legend>Régime</legend>
                        <div class="fee-chips">
                            <label class="fee-chip"><input type="radio" name="fee-status" value="interne" checked><span>Interne</span></label>
                            <label class="fee-chip"><input type="radio" name="fee-status" value="externe"><span>Externe</span></label>
                        </div>
                        <p class="fee-field__hint">L'interne ajoute le dortoir et la cantine du campus.</p>
                    </fieldset>

                    <fieldset class="fee-field" data-fee-for="master" hidden>
                        <legend>Parcours</legend>
                        <div class="fee-chips">
                            @foreach ($tracks as $key => $track)
                                <label class="fee-chip fee-chip--wide"><input type="radio" name="fee-track" value="{{ $key }}" @checked($loop->first)><span>{{ $track['label'] }}<small>{{ $track['hint'] }}</small></span></label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="fee-field">
                        <legend>Période</legend>
                        <div class="fee-chips">
                            <label class="fee-chip"><input type="radio" name="fee-view" value="1"><span>Semestre 1</span></label>
                            <label class="fee-chip"><input type="radio" name="fee-view" value="2"><span>Semestre 2</span></label>
                            <label class="fee-chip"><input type="radio" name="fee-view" value="all" checked><span>Année complète</span></label>
                        </div>
                        <p class="fee-field__hint" data-fee-view-hint hidden></p>
                    </fieldset>
                </form>

                <div class="fee-receipt-col">
                    <div class="fee-receipt" data-fee-receipt>
                        <div class="fee-receipt__slot" aria-hidden="true"></div>
                        <div class="fee-receipt__paper" data-fee-paper>
                            <p class="fee-receipt__brand">Université Adventiste Zurcher</p>
                            <p class="fee-receipt__title">Relevé de frais {{ $fees['year'] }}</p>
                            <p class="fee-receipt__summary" data-fee-summary></p>
                            <ol class="fee-lines" data-fee-lines></ol>
                            <div class="fee-total"><span>Total</span><strong data-fee-total>0</strong><small>Ar</small></div>
                            <p class="fee-receipt__foot">Montants en Ariary, d'après les tableaux officiels {{ $fees['year'] }}.</p>
                        </div>
                    </div>
                    <p class="fee-test" data-fee-test hidden><strong>{{ $f($fees['test_fee']['amount']) }}&nbsp;Ar</strong> {{ $fees['test_fee']['text'] }} Non compris dans le total.</p>
                    <p class="sr-only" role="status" aria-live="polite" data-fee-live></p>
                </div>

                <div class="fee-insight">
                    <div class="fee-mix">
                        <h3 class="fee-h3">Où va chaque ariary</h3>
                        <div class="fee-mix__bar" data-fee-bar role="img" aria-label="Répartition des frais"></div>
                        <ul class="fee-mix__legend" data-fee-legend role="list"></ul>
                    </div>
                    <div class="fee-compare" data-fee-compare hidden>
                        <h3 class="fee-h3">Interne ou externe&nbsp;?</h3>
                        <div data-fee-compare-rows></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- 3. LE PAIEMENT : plans du Master, moyens de paiement --}}
    <section class="fee-pay" data-fee-pay aria-labelledby="fee-pay-title">
        <div class="container-site grid gap-14 lg:grid-cols-[1.15fr_0.85fr] lg:gap-20">
            <div>
                <h2 id="fee-pay-title" class="fee-h2 fee-h2--light" data-reveal>Payer en une ou plusieurs fois</h2>
                <p class="fee-pay__lead" data-reveal>Pour le <strong>Master</strong>, trois plans de paiement au choix.</p>

                <ul class="fee-plans" role="list">
                    @foreach ($fees['payment']['plans'] as $plan)
                        <li class="fee-plan" data-fee-plan>
                            <p class="fee-plan__label">{{ $plan['label'] }}</p>
                            <div class="fee-plan__bar" aria-hidden="true">
                                @foreach ($plan['parts'] as [$share, $when])
                                    <span class="fee-plan__part" data-fee-part><b>{{ $share }}</b></span>
                                @endforeach
                            </div>
                            <p class="fee-plan__text">{{ $plan['text'] }}</p>
                        </li>
                    @endforeach
                </ul>
                <p class="fee-pay__licence" data-reveal><strong>Licence&nbsp;:</strong> pour les modes de paiement et les échéanciers, contactez le service Finance (coordonnées ci-dessous).</p>
            </div>

            <div class="fee-methods" data-reveal>
                <h3 class="fee-h3 fee-h3--light">Régler un Master</h3>
                <ul role="list">
                    @foreach ($fees['payment']['methods'] as $method)
                        <li class="fee-method">
                            <p class="fee-method__label">{{ $method['label'] }}@if ($method['holder'])<small> · {{ $method['holder'] }}</small>@endif</p>
                            <p class="fee-method__row"><span class="fee-method__value">{{ $method['value'] }}</span><button type="button" class="fee-copy" data-fee-copy="{{ $method['value'] }}" aria-label="Copier le numéro {{ $method['label'] }}">Copier</button></p>
                        </li>
                    @endforeach
                </ul>
                <p class="fee-method__note">Frais d'étude du dossier&nbsp;: 17&nbsp;000&nbsp;Ar, à régler par Mobile Money ou virement bancaire.</p>
            </div>
        </div>
    </section>

    {{-- 4. LES TABLEAUX : tous les montants, lisibles sans JavaScript --}}
    <section id="fee-tables" class="fee-tables" data-fee-tables aria-labelledby="fee-tables-title">
        <div class="container-site">
            <h2 id="fee-tables-title" class="fee-h2" data-reveal>Les tableaux officiels</h2>
            <p class="fee-tables__lead" data-reveal>Tous les montants {{ $fees['year'] }}, tels que publiés. Ouvrez un tableau pour le consulter.</p>

            <div class="fee-tables__list">
                @foreach ($departments as $deptKey => $department)
                    @foreach ($department['semesters'] as $number => $semester)
                        <details class="fee-detail" data-fee-detail>
                            <summary><span>{{ $department['label'] }}</span><em>Licence · semestre {{ $number }}</em></summary>
                            <div class="fee-detail__scroll" tabindex="0" role="region" aria-label="Tableau des frais, {{ $department['label'] }}, semestre {{ $number }}">
                                <table class="fee-grid">
                                    <caption class="sr-only">Frais de Licence, {{ $department['label'] }}, semestre {{ $number }}, en Ariary</caption>
                                    <thead>
                                        <tr><th rowspan="2" scope="col">Liste des frais</th>@foreach ($years as $label)<th colspan="2" scope="colgroup">{{ $label }}</th>@endforeach</tr>
                                        <tr>@foreach ($years as $label)<th scope="col">Interne</th><th scope="col">Externe</th>@endforeach</tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($semester['rows'] as $row)
                                            <tr>
                                                <th scope="row">{{ $row['label'] }}@if ($row['note'])<small>{{ $row['note'] }}</small>@endif</th>
                                                @foreach ($row['values'] as $value)<td>{{ $f($value) }}</td>@endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot><tr><th scope="row">Total</th>@foreach ($semester['totals'] as $total)<td>{{ $f($total) }}</td>@endforeach</tr></tfoot>
                                </table>
                            </div>
                        </details>
                    @endforeach
                @endforeach

                @foreach ($tracks as $trackKey => $track)
                    <details class="fee-detail" data-fee-detail>
                        <summary><span>{{ $track['label'] }}</span><em>Master · {{ $track['hint'] }}</em></summary>
                        <div class="fee-detail__scroll" tabindex="0" role="region" aria-label="Tableau des frais, {{ $track['label'] }}">
                            <table class="fee-grid fee-grid--master">
                                <caption class="sr-only">Frais de {{ $track['label'] }}, en Ariary</caption>
                                <thead><tr><th scope="col">Liste des frais</th>@foreach ($track['semesters'] as $number => $semester)<th scope="col">Semestre {{ $number }}</th>@endforeach<th scope="col">Observation</th></tr></thead>
                                <tbody>
                                    @php($firstSemester = collect($track['semesters'])->first())
                                    @foreach ($firstSemester['rows'] as $index => $row)
                                        <tr>
                                            <th scope="row">{{ $row['label'] }}</th>
                                            @foreach ($track['semesters'] as $semester)<td>{{ $f($semester['rows'][$index]['amount'] ?? null) }}</td>@endforeach
                                            <td class="fee-grid__obs">{{ $row['note'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot><tr><th scope="row">Total</th>@foreach ($track['semesters'] as $semester)<td>{{ $f($semester['total']) }}</td>@endforeach<td></td></tr></tfoot>
                            </table>
                        </div>
                    </details>
                @endforeach
            </div>

            @if ($posters->isNotEmpty())
                <div class="fee-posters" data-reveal>
                    <h3 class="fee-h3">Les tableaux en image</h3>
                    <ul role="list">
                        @foreach ($posters as $i => $poster)
                            <li><a href="{{ $poster->absoluteUrl() }}" target="_blank" rel="noopener noreferrer"><x-ico name="document" class="size-5" /><span>{{ $posterLabels[$i] ?? $poster->title }}</span><span class="sr-only"> (image, nouvel onglet)</span></a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>

    {{-- 5. LA FINANCE --}}
    <section class="fee-contact on-dark" data-fee-contact aria-labelledby="fee-contact-title">
        <div class="container-site grid items-end gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:gap-20">
            <div>
                <h2 id="fee-contact-title" class="fee-contact__title" data-reveal>Une question&nbsp;?</h2>
                <p class="fee-contact__lead" data-reveal>Modes de paiement, échéanciers, bourses et aide financière&nbsp;: le service Finance vous répond.</p>
            </div>
            <address class="fee-address not-italic" data-reveal>
                <p class="flex gap-3"><x-ico name="phone" class="mt-1 size-5 shrink-0 text-gold-300" /><a href="tel:+261{{ ltrim(str_replace(' ', '', $finance['phone']), '0') }}">{{ $finance['phone'] }}</a></p>
                <p class="flex gap-3"><x-ico name="mail" class="mt-1 size-5 shrink-0 text-gold-300" /><a href="mailto:{{ $finance['email'] }}">{{ $finance['email'] }}</a></p>
                <p class="flex gap-3"><x-ico name="clock" class="mt-1 size-5 shrink-0 text-gold-300" /><span>Bureau de la Finance : {{ $finance['hours'] }}</span></p>
            </address>
        </div>
    </section>
</div>
</x-layouts.app>
