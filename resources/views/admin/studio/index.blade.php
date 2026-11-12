<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Studio UAZ · Université Adventiste Zurcher</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/studio.css') }}?v={{ filemtime(public_path('css/admin/studio.css')) }}">
</head>
<body class="st-page">
{{--
    Studio UAZ : l'éditeur de visuels de l'université (écrans de direct, annonces, versets, félicitations…). Tout le dessin se fait dans le navigateur
    (public/js/admin/studio/) : moteur.js (canvas 2D, un seul code pour l'aperçu et l'export), modeles.js (les modèles, à enrichir sans toucher à l'éditeur),
    editeur.js (cette interface), zip.js. Le serveur garde les visuels de chaque personne (StudioController). Voir docs/DECISIONS.md, D-078.
--}}
<header class="st-haut">
    <a class="st-haut__retour" href="{{ url('/admin') }}" title="Retour au back-office"><i class="bi bi-chevron-left" aria-hidden="true"></i><span>Quitter</span></a>
    <span class="st-haut__titre"><i class="bi bi-easel2" aria-hidden="true"></i> Studio UAZ</span>
    <label class="st-haut__nom">
        <span class="st-sr">Nom du visuel</span>
        <input type="text" id="stNom" maxlength="120" placeholder="Nom du visuel" autocomplete="off" aria-label="Nom du visuel">
    </label>
    <span class="st-haut__etat" id="stEtat" role="status" aria-live="polite">Prêt</span>
    <div class="st-haut__historique" role="group" aria-label="Historique">
        <button type="button" class="st-btn st-btn--clair st-btn--icone" id="stAnnuler" title="Annuler (Ctrl+Z)" aria-label="Annuler" disabled><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
        <button type="button" class="st-btn st-btn--clair st-btn--icone" id="stRetablir" title="Rétablir (Ctrl+Y)" aria-label="Rétablir" disabled><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
    </div>
    <a class="st-btn st-btn--clair" href="{{ route('admin.retouche.index') }}" title="Ouvrir l’atelier de retouche photo"><i class="bi bi-camera" aria-hidden="true"></i><span>Retouche photo</span></a>
    <div class="st-menu">
        <button type="button" class="st-btn st-btn--or" id="stExporter" aria-haspopup="true" aria-expanded="false"><i class="bi bi-download" aria-hidden="true"></i><span>Exporter</span><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
        <div class="st-menu__liste" id="stMenuExport" role="menu" hidden>
            <button type="button" role="menuitem" data-export="png-1"><strong>PNG</strong><span>Taille du format : prêt à publier</span></button>
            <button type="button" role="menuitem" data-export="png-2"><strong>PNG haute définition (×2)</strong><span>Impression, grand écran, retouche ultérieure</span></button>
            <button type="button" role="menuitem" data-export="jpg-1"><strong>JPEG</strong><span>Léger, pour Facebook ou WhatsApp</span></button>
            <button type="button" role="menuitem" data-export="zip"><strong>Tous les formats (.zip)</strong><span>16:9, 1:1, 4:5 et 9:16 d’un coup</span></button>
            <button type="button" role="menuitem" data-export="media"><strong>Ajouter à la médiathèque</strong><span>Pour l’insérer ensuite dans une newsletter ou une page</span></button>
        </div>
    </div>
</header>

<aside class="st-panneau" aria-label="Panneau du Studio">
    <div class="st-resizer" role="separator" aria-orientation="vertical" aria-label="Largeur du panneau : glissez, ou utilisez les flèches gauche et droite (double-clic : taille d’origine)" tabindex="0" id="stResizer"></div>
    <div class="st-onglets" role="tablist">
        <button type="button" role="tab" class="st-onglet is-on" data-onglet="modeles" aria-selected="true">Modèles</button>
        <button type="button" role="tab" class="st-onglet" data-onglet="contenu" aria-selected="false">Contenu</button>
        <button type="button" role="tab" class="st-onglet" data-onglet="elements" aria-selected="false">Éléments</button>
        <button type="button" role="tab" class="st-onglet" data-onglet="visuels" aria-selected="false">Mes visuels</button>
    </div>
    <div class="st-volet" data-volet="modeles" id="stModeles"></div>
    <div class="st-volet" data-volet="contenu" id="stContenu" hidden></div>
    <div class="st-volet" data-volet="elements" id="stElements" hidden></div>
    <div class="st-volet" data-volet="visuels" id="stVisuels" hidden></div>
</aside>

<main class="st-scene" id="stScene">
    <div class="st-barre">
        <div class="st-formats" id="stFormats" role="tablist" aria-label="Format du visuel"></div>
        <label class="st-case" id="stZoneBloc" hidden><input type="checkbox" id="stZone"> Zones couvertes par l’interface (Story)</label>
        <span class="st-barre__espace"></span>
        <span class="st-dims" id="stDims"></span>
    </div>
    <div class="st-toile" id="stToile">
        <div class="st-cadre" id="stCadre">
            <canvas id="stCanvas" aria-label="Aperçu du visuel" role="img"></canvas>
            <div class="st-zone st-zone--haut" id="stZoneHaut" hidden></div>
            <div class="st-zone st-zone--bas" id="stZoneBas" hidden></div>
            {{-- Au-dessus du dessin : on y clique, glisse et sélectionne les éléments (comme les blocs de l'éditeur de newsletters). --}}
            <div class="st-surcouche" id="stSurcouche">
                <div class="st-survol" id="stSurvol" hidden></div>
                <div class="st-sel" id="stSel" hidden><span class="st-poignee" aria-hidden="true"></span></div>
                <div class="st-barre-el" id="stBarreEl" role="toolbar" aria-label="Actions de l’élément" hidden></div>
            </div>
        </div>
    </div>
    <ul class="st-alertes" id="stAlertes" aria-live="polite"></ul>
</main>

{{-- La médiathèque : choisir ou envoyer la photo d'un emplacement --}}
<dialog class="st-dialogue" id="stMedias" aria-labelledby="stMediasTitre">
    <div class="st-dialogue__tete">
        <h2 id="stMediasTitre">Choisir une photo</h2>
        <button type="button" class="st-btn st-btn--icone" id="stMediasFermer" aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <div class="st-dialogue__outils">
        <input type="search" id="stMediasRecherche" placeholder="Rechercher dans la médiathèque…" aria-label="Rechercher une photo" autocomplete="off">
        <label class="st-btn st-btn--clair st-btn--fichier"><i class="bi bi-upload" aria-hidden="true"></i><span>Envoyer une photo</span><input type="file" id="stMediasFichier" accept="image/jpeg,image/png,image/webp" hidden></label>
    </div>
    <p class="st-dialogue__etat" id="stMediasEtat" role="status"></p>
    <div class="st-grille" id="stMediasGrille"></div>
    <div class="st-dialogue__pied"><button type="button" class="st-btn st-btn--clair" id="stMediasPlus" hidden>Charger plus</button></div>
</dialog>

<div class="st-popover" id="stPopover" role="dialog" aria-label="Modifier l’élément" hidden></div>

<div class="st-toasts" id="stToasts" role="status" aria-live="polite"></div>

<script id="st-boot" type="application/json">@json($boot)</script>
<script src="{{ asset('js/admin/studio/moteur.js') }}?v={{ filemtime(public_path('js/admin/studio/moteur.js')) }}"></script>
<script src="{{ asset('js/admin/studio/modeles.js') }}?v={{ filemtime(public_path('js/admin/studio/modeles.js')) }}"></script>
<script src="{{ asset('js/admin/studio/zip.js') }}?v={{ filemtime(public_path('js/admin/studio/zip.js')) }}"></script>
<script src="{{ asset('js/admin/studio/editeur.js') }}?v={{ filemtime(public_path('js/admin/studio/editeur.js')) }}"></script>
</body>
</html>
