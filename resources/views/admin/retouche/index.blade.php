<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Retouche photo · Université Adventiste Zurcher</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/retouche-photo.css') }}?v={{ filemtime(public_path('css/admin/retouche-photo.css')) }}">
</head>
<body class="rp-page">
{{--
    Retouche photo : un atelier façon Lightroom, entièrement dans le navigateur (WebGL 2) : lumière, couleur, couleur par teinte, effets, recadrage,
    masques (personne, fond, cheveux, peau, vêtements, pinceau, dégradés), changement de fond d'après le détourage automatique, préréglage
    « Studio UAZ », filigrane, export. Repris de l'application « registrar » de l'université (voir docs/DECISIONS.md, D-078).
    Scripts : public/js/admin/retouche/ (moteur = rendu, masque = détourage, filtres, sauvegarde = IndexedDB, atelier = l'interface).
--}}
@php
/* Curseurs : [cle, libelle, min, max, pas, unite] */
$rpGroupes = [
	'lumiere' => [
		['exposition', 'Exposition', -4, 4, 0.05, 'IL'],
		['contraste', 'Contraste', -100, 100, 1, ''],
		['hautesLumieres', 'Hautes lumières', -100, 100, 1, ''],
		['ombres', 'Ombres', -100, 100, 1, ''],
		['blancs', 'Blancs', -100, 100, 1, ''],
		['noirs', 'Noirs', -100, 100, 1, ''],
	],
	'couleur' => [
		['temperature', 'Température', -100, 100, 1, ''],
		['teinte', 'Teinte', -100, 100, 1, ''],
		['vibrance', 'Vibrance', -100, 100, 1, ''],
		['saturation', 'Saturation', -100, 100, 1, ''],
	],
	'portrait' => [
		['lissage', 'Lissage', 0, 100, 1, ''],
		['peauTemperature', 'Température', -100, 100, 1, ''],
		['peauTeinte', 'Teinte', -100, 100, 1, ''],
		['peauSaturation', 'Saturation', -100, 100, 1, ''],
		['peauLuminosite', 'Luminosité', -100, 100, 1, ''],
	],
	'effets' => [
		['clarte', 'Clarté', -100, 100, 1, ''],
		['nettete', 'Netteté', 0, 100, 1, ''],
		['vignette', 'Vignetage', -100, 100, 1, ''],
	],
];
/* Masques : types (cle de TYPES_MASQUE dans src/js/retouche-photo.js, icone,
   nom court, description) et reglages locaux, ajoutes aux reglages globaux
   dans la zone du masque. */
$rpTypesMasque = [
	'Trouvés sur la photo' => [
		['sujet', 'bi-person-fill', 'Sujet', 'La personne entière'],
		['fond', 'bi-image', 'Arrière-plan', 'Tout sauf la personne'],
		['cheveux', 'bi-scissors', 'Cheveux', 'Les cheveux'],
		['peauVisage', 'bi-emoji-smile', 'Visage', 'La peau du visage'],
		['peauCorps', 'bi-person-arms-up', 'Peau', 'La peau du corps : cou, bras, mains'],
		['vetements', 'bi-person-standing-dress', 'Vêtements', 'Les vêtements'],
	],
	'À tracer' => [
		['pinceau', 'bi-brush', 'Pinceau', 'Peindre la zone à la main'],
		['lineaire', 'bi-distribute-vertical', 'Linéaire', 'Dégradé linéaire : un ciel, un sol, un côté'],
		['radial', 'bi-record-circle', 'Radial', 'Dégradé radial : un halo autour d\'un point'],
	],
];
$rpMasqueReglages = [
	'Lumière' => [
		['m_exposition', 'Exposition', -4, 4, 0.05, 'IL'],
		['m_contraste', 'Contraste', -100, 100, 1, ''],
		['m_hautesLumieres', 'Hautes lumières', -100, 100, 1, ''],
		['m_ombres', 'Ombres', -100, 100, 1, ''],
		['m_blancs', 'Blancs', -100, 100, 1, ''],
		['m_noirs', 'Noirs', -100, 100, 1, ''],
	],
	'Couleur' => [
		['m_temperature', 'Température', -100, 100, 1, ''],
		['m_teinte', 'Teinte', -100, 100, 1, ''],
		['m_saturation', 'Saturation', -100, 100, 1, ''],
	],
	'Détail' => [
		['m_clarte', 'Clarté', -100, 100, 1, ''],
		['m_nettete', 'Netteté', -100, 100, 1, ''],
	],
];
$rpCurseur = function (array $c, string $prefixe = 'rp-') {
	[$cle, $libelle, $min, $max, $pas, $unite] = $c;
	$id = $prefixe . $cle;
	echo '<div class="rp-curseur" data-reglage="' . $cle . '">'
		. '<label class="rp-curseur__nom" for="' . $id . '" title="Double-clic : remettre à zéro">' . htmlspecialchars($libelle) . '</label>'
		. '<input type="number" class="rp-curseur__valeur" data-pour="' . $id . '" min="' . $min . '" max="' . $max . '" step="' . $pas . '" value="0" aria-label="' . htmlspecialchars($libelle) . ' (valeur)">'
		. ($unite !== '' ? '<span class="rp-curseur__unite">' . $unite . '</span>' : '')
		. '<input type="range" class="rp-curseur__piste rp-piste--' . $cle . '" id="' . $id . '" min="' . $min . '" max="' . $max . '" step="' . $pas . '" value="0">'
		. '</div>';
};
$rpTeintes = [
	'rouge' => ['Rouges', '#e5484d'], 'orange' => ['Oranges', '#f2994a'], 'jaune' => ['Jaunes', '#f2c94c'],
	'vert' => ['Verts', '#46a758'], 'aqua' => ['Turquoises', '#12a5b0'], 'bleu' => ['Bleus', '#3e63dd'],
	'violet' => ['Violets', '#8e4ec6'], 'magenta' => ['Magentas', '#d6409f'],
];
/* Fonds de studio : [cle, nom, centre, bords, hauteur de la lumiere (0 haut, 1 bas)].
   « Gris perle » et « Gris bleu » sont mesures sur deux portraits de studio
   fournis comme modeles (13/10/2026) : la lumiere tombe derriere la tete et
   s'assombrit vers les coins. */
$rpFondsStudio = [
	['perle', 'Gris perle', '#dde0ea', '#c6cbd6', 0.34],
	['grisBleu', 'Gris bleu', '#a9b7d0', '#586780', 0.32],
	['blanc', 'Blanc', '#f7f7f9', '#eceef1', 0.5],
	['sable', 'Sable', '#e8dfd2', '#b7a690', 0.34],
	['ciel', 'Bleu ciel', '#d4e3f3', '#93b2d6', 0.34],
	['ardoise', 'Ardoise', '#8b929d', '#3b414b', 0.32],
	['zurcher', 'Bleu Zurcher', '#4a5a9e', '#1a2250', 0.32],
	['noir', 'Noir', '#46464c', '#0e0e11', 0.32],
];
/* Portrait : eclairages de studio (des masques poses sur le visage detecte)
   et teints de peau. [cle, icone, nom, description] */
$rpEclairages = [
	['aucun', 'bi-slash-circle', 'Aucun', 'Sans éclairage ajouté'],
	['doux', 'bi-brightness-alt-high', 'Doux', 'Une boîte à lumière de face : visage éclairé, ombres adoucies'],
	['studio', 'bi-lamp', 'Studio', 'La personne nette et détachée du fond, le visage un peu plus lumineux'],
	['lateral', 'bi-circle-half', 'Latéral', 'La lumière vient d\'un côté, l\'ombre de l\'autre : du relief'],
	['eclat', 'bi-stars', 'Éclat', 'Peau lumineuse, regard net : la photo de studio retouchée'],
];
$rpTeints = [
	['naturel', 'Naturel', 'Une teinte juste, ni rouge ni jaune'],
	['chaleureux', 'Chaud', 'Plus chaud, comme sous une lumière de studio dorée'],
	['dore', 'Doré', 'Un hâle doré, plus soutenu'],
	['lumineux', 'Clair', 'Teinte naturelle, peau plus lumineuse'],
	['doux', 'Poudré', 'Moins saturé, un peu plus clair'],
];
$rpCouleursFond = [
	'#ffffff' => 'Blanc', '#eef0f3' => 'Blanc cassé', '#d9dde3' => 'Gris clair', '#dce8f5' => 'Bleu ciel',
	'#222e65' => 'Bleu Zurcher', '#88724b' => 'Or Zurcher', '#1b1b1f' => 'Noir',
];
/* Filigrane de l'export (src/js/retouche-photo.js, dessinerFiligrane) :
   emplacements sur la grille 3 x 3 de Lightroom, couleurs du texte, curseurs
   [cle, nom, min, max, pas, unite, valeur de depart]. */
/* Grilles de composition, comme les incrustations du recadrage de Lightroom
   (src/js/retouche-photo.js, dessinerGrille) : [cle, nom, a quoi elle sert]. */
$rpGrilles = [
	['aucune', 'Aucune', 'Pas de grille sur la photo ; le recadrage garde la règle des tiers'],
	['tiers', 'Tiers', 'Règle des tiers : le sujet, ou les yeux, sur une ligne ou un croisement'],
	['quadrillage', 'Quadrillage', 'Des cases carrées, pour redresser un horizon ou aligner des verticales'],
	['or', "Nombre d'or", "Comme les tiers, un peu plus près du centre (rapport 1 : 1,618)"],
	['spirale', 'Spirale', "Spirale d'or : le sujet au cœur de la spirale ; Maj+O la pivote"],
	['diagonales', 'Diagonales', 'Lignes à 45° depuis les coins, pour une composition en biais'],
	['triangles', 'Triangles', "Triangles d'or : une diagonale et ses perpendiculaires ; Maj+O la retourne"],
	['centre', 'Centre', "Croix centrale : portrait symétrique, photo d'identité"],
];
$rpFiligranePositions = [
	'hg' => 'En haut à gauche', 'hc' => 'En haut au centre', 'hd' => 'En haut à droite',
	'cg' => 'Au milieu à gauche', 'cc' => 'Au centre', 'cd' => 'Au milieu à droite',
	'bg' => 'En bas à gauche', 'bc' => 'En bas au centre', 'bd' => 'En bas à droite',
];
$rpFiligraneCouleurs = [
	'#ffffff' => 'Blanc', '#1b1b1f' => 'Noir', '#9aa0a8' => 'Gris',
	'#222e65' => 'Bleu Zurcher', '#88724b' => 'Or Zurcher',
];
$rpFiligraneCurseurs = [
	['taille', 'Taille', 2, 100, 1, '%', 30, 'Largeur du filigrane, en pourcentage de la largeur de la photo'],
	['opacite', 'Opacité', 5, 100, 1, '%', 70, ''],
	['marge', 'Marge', 0, 25, 0.5, '%', 3, 'Distance aux bords de la photo'],
	['espacement', 'Espacement', 0, 100, 1, '', 40, 'Espace entre les répétitions de la mosaïque'],
	['angle', 'Angle', -90, 90, 1, '°', 0, ''],
];
/* Formats du cadrage : [valeur, nom court, nom complet, [largeur, hauteur] ou null].
   La valeur est enregistree telle quelle dans les reglages (r.ratio). */
$rpFormats = [
	'Général' => [
		['libre', 'Libre', 'Libre', null],
		['origine', "D'origine", "Format d'origine", null],
		['1:1', '1:1', 'Carré 1:1', [1, 1]],
		['perso', 'Perso.', 'Personnalisé', null],
	],
	'Portrait' => [
		['4:5', '4:5', 'Portrait 4:5', [4, 5]],
		['3:4', '3:4', 'Portrait 3:4', [3, 4]],
		['2:3', '2:3', 'Portrait 2:3', [2, 3]],
		['9:16', '9:16', 'Portrait 9:16 (story)', [9, 16]],
		['35:45', 'Identité', "Photo d'identité 35 × 45", [35, 45]],
	],
	'Paysage' => [
		['5:4', '5:4', 'Paysage 5:4', [5, 4]],
		['4:3', '4:3', 'Paysage 4:3', [4, 3]],
		['3:2', '3:2', 'Paysage 3:2', [3, 2]],
		['16:9', '16:9', 'Paysage 16:9', [16, 9]],
		['21:9', '21:9', 'Panoramique 21:9', [21, 9]],
	],
];
$logoUrl = asset('images/uaz-seal-384.png');
@endphp
<header class="rp-haut">
    <a class="rp-haut__retour" href="{{ url('/admin') }}" title="Retour au back-office"><i class="bi bi-chevron-left" aria-hidden="true"></i><span>Quitter</span></a>
    <span class="rp-haut__titre"><i class="bi bi-camera" aria-hidden="true"></i> Retouche photo</span>
    @if (Route::has('admin.studio.index'))
        <a class="rp-haut__lien" href="{{ route('admin.studio.index') }}"><i class="bi bi-easel2" aria-hidden="true"></i><span>Studio UAZ</span></a>
    @endif
</header>
<div class="rp-hote">
					<div class="rp-app" id="rpApp" data-compte="{{ auth()->id() }}" data-profil="{{ route('admin.retouche.people') }}" data-logo="{{ $logoUrl }}" data-csrf="{{ csrf_token() }}">

						<!-- ============ PLAN DE TRAVAIL ============ -->
						<div class="rp-centre">
							<div class="rp-barre" role="toolbar" aria-label="Outils de retouche">
								<button type="button" class="rp-btn rp-btn--primaire" id="rpAjouter">
									<i class="bi bi-images" aria-hidden="true"></i><span>Ajouter des photos</span>
								</button>
								<input type="file" id="rpFichiers" accept="image/jpeg,image/png,image/webp" multiple hidden>
								<span class="rp-barre__sep" aria-hidden="true"></span>
								<button type="button" class="rp-btn rp-btn--icone" id="rpAnnuler" title="Annuler (Ctrl+Z)" aria-label="Annuler" disabled><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
								<button type="button" class="rp-btn rp-btn--icone" id="rpRetablir" title="Rétablir (Ctrl+Y)" aria-label="Rétablir" disabled><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
								<span class="rp-barre__sep" aria-hidden="true"></span>
								<button type="button" class="rp-btn rp-btn--outil" id="rpAvantApres" aria-pressed="false" title="Avant / après (touche \)" disabled>
									<i class="bi bi-layout-split" aria-hidden="true"></i><span>Avant / après</span>
								</button>
								<button type="button" class="rp-btn rp-btn--outil" id="rpRecadrer" aria-pressed="false" title="Recadrer (touche R)" disabled>
									<i class="bi bi-crop" aria-hidden="true"></i><span>Recadrer</span>
								</button>
								<button type="button" class="rp-btn rp-btn--outil" id="rpPinceau" aria-pressed="false" title="Corriger le détourage au pinceau (touche B)" disabled>
									<i class="bi bi-brush" aria-hidden="true"></i><span>Détourage</span>
								</button>
								<button type="button" class="rp-btn rp-btn--outil" id="rpMasques" aria-pressed="false" title="Masques : retoucher une seule zone de la photo (touche M)" disabled>
									<i class="bi bi-mask" aria-hidden="true"></i><span>Masques</span>
								</button>
								<button type="button" class="rp-btn rp-btn--outil" id="rpGrilleBouton" aria-expanded="false" aria-controls="rpGrilleMenu" title="Grille de composition (touche O)" disabled>
									<i class="bi bi-grid-3x3" aria-hidden="true"></i><span>Grille</span>
								</button>
								<span class="rp-barre__espace"></span>
								<div class="rp-zoom" role="group" aria-label="Zoom">
									<button type="button" class="rp-btn rp-btn--petit" id="rpZoomAjuster" aria-pressed="true" disabled>Ajuster</button>
									<button type="button" class="rp-btn rp-btn--petit" id="rpZoom100" aria-pressed="false" disabled>100 %</button>
									<output class="rp-zoom__val" id="rpZoomVal" aria-live="off"></output>
								</div>

								{{-- Choix de la grille : s'ouvre sous le bouton « Grille » (src/js/retouche-photo.js, ouvrirMenuGrille). --}}
								<div class="rp-grille-menu" id="rpGrilleMenu" role="dialog" aria-labelledby="rpGrilleMenuTitre" hidden>
									<p class="rp-grille-menu__titre" id="rpGrilleMenuTitre">Grille de composition</p>
									<div class="rp-grilles" role="radiogroup" aria-labelledby="rpGrilleMenuTitre">
										@foreach ($rpGrilles as [$cle, $nom, $aide])
										<label class="rp-grille-choix" title="{!! htmlspecialchars($aide) !!}">
											<input type="radio" name="rpGrille" value="{!! $cle !!}"{!! $cle === 'aucune' ? ' checked' : '' !!}>
											<span class="rp-grille-choix__vue" aria-hidden="true">@if ($cle === 'aucune')<i class="bi bi-slash-lg"></i>@else<svg data-grille="{!! $cle !!}" preserveAspectRatio="none"></svg>@endif</span>
											<span class="rp-grille-choix__nom">{!! htmlspecialchars($nom) !!}</span>
										</label>
										@endforeach
									</div>
									<div class="rp-grille-menu__options">
										<button type="button" class="rp-btn rp-btn--petit" id="rpGrillePivoter" title="Maj+O" hidden><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Pivoter</button>
										<label class="rp-champ rp-champ--ligne" id="rpGrilleDensiteBloc" hidden><span class="rp-libelle">Cases</span><input type="range" id="rpGrilleDensite" min="4" max="24" step="1" value="8"></label>
									</div>
									<p class="rp-aide" id="rpGrilleAide"></p>
									<p class="rp-aide">Touche O : grille suivante ; Maj+O : pivoter. La grille aide à placer le sujet et ne s'exporte pas.</p>
								</div>
							</div>

							<div class="rp-scene" id="rpScene">
								<canvas class="rp-canevas" id="rpCanvas" aria-label="Aperçu de la photo retouchée" role="img"></canvas>
								{{-- Apercu du filigrane de l'export, par-dessus la photo (src/js/retouche-photo.js, majApercuFiligrane). --}}
								<canvas class="rp-filigrane-apercu" id="rpFiligraneApercu" hidden aria-hidden="true"></canvas>
								{{-- Grille de composition sur la photo (src/js/retouche-photo.js, majGrille). --}}
								<svg class="rp-grille rp-grille-traits" id="rpGrille" preserveAspectRatio="none" style="display: none" aria-hidden="true"></svg>
								<span class="rp-grille-nom" id="rpGrilleNom" role="status" aria-live="polite" hidden></span>

								<div class="rp-cadre" id="rpCadre" hidden>
									<svg class="rp-cadre__grille rp-grille-traits" id="rpGrilleCadre" preserveAspectRatio="none" aria-hidden="true"></svg>
									<span class="rp-cadre__taille" id="rpCadreTaille" aria-hidden="true"></span>
									@foreach (['n', 's', 'e', 'o', 'ne', 'no', 'se', 'so'] as $poignee)
									<span class="rp-cadre__poignee rp-cadre__poignee--{!! $poignee !!}" data-poignee="{!! $poignee !!}" aria-hidden="true"></span>
									@endforeach
								</div>
								<div class="rp-coupure" id="rpCoupure" hidden>
									<span class="rp-coupure__etiquette rp-coupure__etiquette--avant">Avant</span>
									<span class="rp-coupure__etiquette rp-coupure__etiquette--apres">Après</span>
									<span class="rp-coupure__poignee" aria-hidden="true"><i class="bi bi-arrows"></i></span>
								</div>
								<div class="rp-curseur-pinceau" id="rpCurseurPinceau" hidden aria-hidden="true"></div>
								{{-- Poignees du degrade du masque en cours (src/js/retouche-photo.js, majPoignees). --}}
								<svg class="rp-poignees" id="rpPoignees" style="display: none" aria-hidden="true">
									<defs><clipPath id="rpPoigneesClip"><rect x="0" y="0" width="0" height="0"></rect></clipPath></defs>
									<g class="rp-poignees__traits" clip-path="url(#rpPoigneesClip)"></g>
									<g class="rp-poignees__points"></g>
								</svg>

								<!-- Barres d'outils contextuelles -->
								<div class="rp-flottant" id="rpOutilsRecadrage" hidden role="group" aria-label="Recadrage">
									<label class="rp-flottant__champ">
										<span>Format</span>
										<select id="rpRatio">
											@foreach ($rpFormats as $groupe => $liste)
											<optgroup label="{!! htmlspecialchars($groupe) !!}">
												@foreach ($liste as [$v, , $complet])
												<option value="{!! $v !!}">{!! htmlspecialchars($complet) !!}</option>
												@endforeach
											</optgroup>
											@endforeach
										</select>
									</label>
									<button type="button" class="rp-btn rp-btn--icone" id="rpInverserFlottant" title="Inverser portrait et paysage (touche X)" aria-label="Inverser portrait et paysage"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></button>
									<button type="button" class="rp-btn rp-btn--icone" id="rpTournerG" title="Pivoter à gauche" aria-label="Pivoter à gauche"><i class="bi bi-arrow-90deg-left" aria-hidden="true"></i></button>
									<button type="button" class="rp-btn rp-btn--icone" id="rpTournerD" title="Pivoter à droite" aria-label="Pivoter à droite"><i class="bi bi-arrow-90deg-right" aria-hidden="true"></i></button>
									<button type="button" class="rp-btn rp-btn--icone" id="rpMiroir" title="Miroir horizontal" aria-label="Miroir horizontal"><i class="bi bi-symmetry-vertical" aria-hidden="true"></i></button>
									<button type="button" class="rp-btn rp-btn--petit" id="rpCadrerPersonne" title="Cadre la personne comme un portrait de studio"><i class="bi bi-person-bounding-box" aria-hidden="true"></i> Cadrer la personne</button>
									<button type="button" class="rp-btn rp-btn--petit" id="rpCadreEntier">Image entière</button>
									<button type="button" class="rp-btn rp-btn--petit rp-btn--primaire" id="rpCadreValider">Terminé</button>
								</div>

								<div class="rp-flottant" id="rpOutilsPinceau" hidden role="group" aria-label="Pinceau de détourage">
									<div class="rp-segment" role="radiogroup" aria-label="Effet du pinceau">
										<label><input type="radio" name="rpPinceauMode" value="ajouter" checked><span><i class="bi bi-plus-circle" aria-hidden="true"></i> Ajouter à la personne</span></label>
										<label><input type="radio" name="rpPinceauMode" value="retirer"><span><i class="bi bi-dash-circle" aria-hidden="true"></i> Rendre au fond</span></label>
									</div>
									<label class="rp-flottant__champ"><span>Taille</span><input type="range" id="rpPinceauTaille" min="8" max="400" value="70"></label>
									<label class="rp-flottant__champ"><span>Douceur</span><input type="range" id="rpPinceauDouceur" min="0" max="100" value="50"></label>
									<button type="button" class="rp-btn rp-btn--petit" id="rpPinceauEffacer" title="Efface toutes les corrections au pinceau">Tout effacer</button>
									<button type="button" class="rp-btn rp-btn--petit rp-btn--primaire" id="rpPinceauValider">Terminé</button>
									<p class="rp-flottant__aide">Le rouge montre le fond. Maintenir Alt inverse le pinceau.</p>
								</div>

								<div class="rp-flottant rp-flottant--bas" id="rpOutilsMasque" hidden role="group" aria-label="Masque en cours">
									<span class="rp-flottant__titre"><i class="bi bi-mask" id="rpOutilsMasqueIcone" aria-hidden="true"></i> <span id="rpOutilsMasqueNom"></span></span>
									<div class="rp-segment" role="radiogroup" aria-label="Outil du masque">
										<label><input type="radio" name="rpMasquePinceau" value="non" checked><span><i class="bi bi-cursor" aria-hidden="true"></i> <span class="rp-segment__texte" id="rpMasqueSansPinceau">Déplacer</span></span></label>
										<label><input type="radio" name="rpMasquePinceau" value="ajouter"><span title="Peindre pour agrandir la zone"><i class="bi bi-brush" aria-hidden="true"></i> <span class="rp-segment__texte">Peindre</span></span></label>
										<label><input type="radio" name="rpMasquePinceau" value="retirer"><span title="Gommer pour réduire la zone"><i class="bi bi-eraser" aria-hidden="true"></i> <span class="rp-segment__texte">Gommer</span></span></label>
									</div>
									<label class="rp-flottant__champ" data-si-pinceau hidden><span>Taille</span><input type="range" id="rpMasqueTaille" min="8" max="400" value="70"></label>
									<button type="button" class="rp-btn rp-btn--petit rp-btn--primaire" id="rpMasqueTermine" title="Terminer ce masque (Échap)">Terminé</button>
								</div>

								<div class="rp-vide" id="rpVide">
									<div class="rp-vide__boite">
										<i class="bi bi-camera rp-vide__icone" aria-hidden="true"></i>
										<p class="rp-vide__titre">Glissez vos photos ici</p>
										<p class="rp-vide__texte">JPEG, PNG ou WebP, une ou plusieurs à la fois. Photos et retouches sont gardées pour votre compte : vous les retrouvez sur tout appareil après connexion.</p>
										<button type="button" class="rp-btn rp-btn--primaire" id="rpChoisir"><i class="bi bi-folder2-open" aria-hidden="true"></i> Choisir des photos</button>
									</div>
								</div>
								<div class="rp-depot" id="rpDepot" hidden aria-hidden="true"><span><i class="bi bi-download"></i> Déposez pour ajouter</span></div>
								<div class="rp-etat" id="rpEtat" role="status" aria-live="polite" hidden>
									<span class="rp-etat__roue" aria-hidden="true"></span><span id="rpEtatTexte"></span>
								</div>
							</div>

							<div class="rp-pellicule" id="rpPellicule" hidden>
								<ol class="rp-pellicule__liste" id="rpVignettes" aria-label="Photos ouvertes"></ol>
								<div class="rp-pellicule__actions">
									<button type="button" class="rp-btn rp-btn--petit" id="rpSynchro" title="Copie les réglages de la photo affichée (sauf le recadrage) sur toutes les autres">
										<i class="bi bi-files" aria-hidden="true"></i> Copier ces réglages sur toutes
									</button>
									<button type="button" class="rp-btn rp-btn--petit" id="rpStudioTout" title="Applique le préréglage Studio UAZ à chaque photo, mesurée à part">
										<i class="bi bi-stars" aria-hidden="true"></i> Studio UAZ sur toutes
									</button>
									<div class="rp-pellicule__pied">
										<span class="rp-sauve" id="rpSauve" data-etat="aucune" role="status" aria-live="polite" title="Photos et retouches sont gardées pour votre compte, dans ce navigateur et sur le serveur de l'université : vous les retrouvez sur tout appareil après connexion. « Tout fermer » les efface partout." hidden><i class="bi bi-check2-circle" aria-hidden="true"></i><span id="rpSauveTexte"></span></span>
										<button type="button" class="rp-btn rp-btn--petit rp-btn--discret" id="rpToutFermer" title="Ferme toutes les photos et efface leur sauvegarde dans ce navigateur">
											<i class="bi bi-x-circle" aria-hidden="true"></i> Tout fermer
										</button>
									</div>
								</div>
							</div>
						</div>

						<!-- ============ PANNEAU DES RÉGLAGES ============ -->
						<aside class="rp-panneau" id="rpPanneau" aria-label="Réglages de la photo">
							<fieldset class="rp-panneau__corps" id="rpReglages" disabled>

								<section class="rp-bloc rp-bloc--histo" aria-label="Histogramme">
									<canvas class="rp-histo" id="rpHisto" width="600" height="160" aria-hidden="true"></canvas>
									<p class="rp-infos" id="rpInfos">Aucune photo</p>
								</section>

								<section class="rp-bloc rp-studio" aria-labelledby="rpStudioTitre">
									<button type="button" class="rp-studio__bouton" id="rpStudio" aria-describedby="rpStudioTexte">
										<span class="rp-studio__nuancier" aria-hidden="true"><span></span><span></span></span>
										<span class="rp-studio__texte">
											<span class="rp-studio__titre" id="rpStudioTitre">Studio UAZ</span>
											<span class="rp-studio__desc" id="rpStudioTexte">Blancs neutres, peau lumineuse, noirs profonds, fond blanc de studio : réglé sur cette photo.</span>
										</span>
										<i class="bi bi-magic rp-studio__icone" aria-hidden="true"></i>
									</button>
									<div class="rp-studio__options">
										<label class="rp-case"><input type="checkbox" id="rpStudioFond" checked> Fond blanc de studio</label>
										<label class="rp-case" title="Dans le format choisi sous « Cadrage », ou celui de la photo"><input type="checkbox" id="rpStudioCadre" checked> Cadrer sur la personne</label>
									</div>
									<div class="rp-perso">
										<label class="rp-perso__champ">
											<span class="rp-libelle">Mes préréglages</span>
											<select id="rpPersoListe"><option value="">—</option></select>
										</label>
										<button type="button" class="rp-btn rp-btn--petit" id="rpPersoAppliquer" disabled>Appliquer</button>
										<button type="button" class="rp-btn rp-btn--icone rp-btn--petit" id="rpPersoSupprimer" title="Supprimer ce préréglage" aria-label="Supprimer ce préréglage" disabled><i class="bi bi-trash3" aria-hidden="true"></i></button>
									</div>
									<div class="rp-perso rp-perso--nouveau">
										<input type="text" id="rpPersoNom" maxlength="40" placeholder="Nom du préréglage" aria-label="Nom du nouveau préréglage" autocomplete="off">
										<button type="button" class="rp-btn rp-btn--petit" id="rpPersoEnregistrer">Enregistrer les réglages</button>
									</div>
									<button type="button" class="rp-lien" id="rpToutRemettre"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Revenir à la photo d'origine</button>
								</section>

								<details class="rp-bloc rp-volet" id="rpVoletFiltres" open>
									<summary class="rp-volet__titre">Filtres</summary>
									<div class="rp-volet__corps">
										<div class="rp-filtres" id="rpFiltres" role="radiogroup" aria-label="Filtre"></div>
										<div class="rp-sous" id="rpFiltreForceBloc" hidden>
											@php $rpCurseur(['filtreForce', 'Intensité du filtre', 0, 100, 1, '']); @endphp
										</div>
									</div>
								</details>

								<details class="rp-bloc rp-volet" id="rpVoletCadrage" open>
									<summary class="rp-volet__titre">Cadrage</summary>
									<div class="rp-volet__corps">
										<div class="rp-formats" id="rpFormats" role="radiogroup" aria-label="Format du cadrage">
											@foreach ($rpFormats as $groupe => $liste)
											<div class="rp-formats__groupe">
												<span class="rp-formats__titre" aria-hidden="true">{!! htmlspecialchars($groupe) !!}</span>
												<div class="rp-formats__grille">
													@foreach ($liste as [$v, $court, $complet, $dims])
													<label class="rp-format" title="{!! htmlspecialchars($complet) !!}">
														<input type="radio" name="rpFormat" value="{!! $v !!}" aria-label="{!! htmlspecialchars($complet) !!}">
														@if ($dims)
														<span class="rp-format__forme" style="--rp-a: {!! $dims[0] !!}; --rp-b: {!! $dims[1] !!}" aria-hidden="true"></span>
														@else
														<span class="rp-format__forme rp-format__forme--{!! $v !!}" aria-hidden="true"><i class="bi {!! ['libre' => 'bi-bounding-box-circles', 'origine' => 'bi-image', 'perso' => 'bi-rulers'][$v] !!}"></i></span>
														@endif
														<span class="rp-format__nom" aria-hidden="true">{!! htmlspecialchars($court) !!}</span>
													</label>
													@endforeach
												</div>
											</div>
											@endforeach
										</div>

										<div class="rp-sous" id="rpPersoBloc" hidden>
											<div class="rp-dims">
												<label class="rp-champ"><span class="rp-libelle">Largeur</span><input type="number" id="rpPersoL" min="1" max="8000" step="1" inputmode="numeric"></label>
												<span class="rp-dims__fois" aria-hidden="true">×</span>
												<label class="rp-champ"><span class="rp-libelle">Hauteur</span><input type="number" id="rpPersoH" min="1" max="8000" step="1" inputmode="numeric"></label>
											</div>
											<label class="rp-case"><input type="checkbox" id="rpPersoExact"> Exporter exactement à ces dimensions, en pixels</label>
										</div>

										<div class="rp-cadrage__actions">
											<button type="button" class="rp-btn rp-btn--petit" id="rpCadrerVisage" title="Place le visage comme sur un portrait de studio, dans le format choisi"><i class="bi bi-person-bounding-box" aria-hidden="true"></i> Cadrer sur la personne</button>
											<button type="button" class="rp-btn rp-btn--petit" id="rpAjusterCadre" title="Déplacer et redimensionner le cadre sur la photo (touche R)"><i class="bi bi-crop" aria-hidden="true"></i> Ajuster</button>
											<button type="button" class="rp-btn rp-btn--icone rp-btn--petit" id="rpInverser" title="Inverser portrait et paysage (touche X)" aria-label="Inverser portrait et paysage"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></button>
										</div>
										<p class="rp-aide rp-cadrage__info" id="rpCadrageInfo" aria-live="polite"></p>
										<button type="button" class="rp-lien" id="rpCadrerTout" hidden><i class="bi bi-collection" aria-hidden="true"></i> Cadrer toutes les photos dans ce format</button>
									</div>
								</details>

								<details class="rp-bloc rp-volet" id="rpVoletFond" open>
									<summary class="rp-volet__titre">Fond</summary>
									<div class="rp-volet__corps">
										<div class="rp-fonds" id="rpFondModes" role="radiogroup" aria-label="Fond">
											<label class="rp-fond"><input type="radio" name="rpFond" value="original" checked><span class="rp-fond__echantillon rp-fond__echantillon--original"></span><span>D'origine</span></label>
											<label class="rp-fond"><input type="radio" name="rpFond" value="studio"><span class="rp-fond__echantillon rp-fond__echantillon--studio"></span><span>Studio</span></label>
											<label class="rp-fond"><input type="radio" name="rpFond" value="couleur"><span class="rp-fond__echantillon rp-fond__echantillon--couleur" id="rpFondEchCouleur"></span><span>Couleur</span></label>
											<label class="rp-fond"><input type="radio" name="rpFond" value="flou"><span class="rp-fond__echantillon rp-fond__echantillon--flou"></span><span>Flou</span></label>
											<label class="rp-fond"><input type="radio" name="rpFond" value="image"><span class="rp-fond__echantillon rp-fond__echantillon--image" id="rpFondEchImage"></span><span>Image</span></label>
											<label class="rp-fond"><input type="radio" name="rpFond" value="transparent"><span class="rp-fond__echantillon rp-fond__echantillon--transparent"></span><span>Transparent</span></label>
										</div>
										<p class="rp-libelle rp-libelle--groupe">Fonds de studio</p>
										<div class="rp-fonds-studio" role="group" aria-label="Fonds de studio">
											@foreach ($rpFondsStudio as [$cle, $nom, $a, $b, $centre])
											<button type="button" class="rp-fond-studio" data-fond-studio="{!! $cle !!}" data-a="{!! $a !!}" data-b="{!! $b !!}" data-centre="{!! $centre !!}" aria-pressed="false" title="Fond de studio {!! htmlspecialchars(mb_strtolower($nom)) !!}">
												<span class="rp-fond-studio__vue" style="--rp-a: {!! $a !!}; --rp-b: {!! $b !!}; --rp-y: {!! round($centre * 100) !!}%" aria-hidden="true"></span>
												<span class="rp-fond-studio__nom">{!! htmlspecialchars($nom) !!}</span>
											</button>
											@endforeach
										</div>
										<p class="rp-detourage" id="rpDetourage" data-etat="aucun">
											<i class="bi bi-person-bounding-box" aria-hidden="true"></i>
											<span id="rpDetourageTexte">La personne sera détourée au premier changement de fond.</span>
										</p>

										<div class="rp-sous" data-pour-fond="studio" hidden>
											<div class="rp-paire">
												<label class="rp-couleur"><span class="rp-libelle">Centre</span><input type="color" id="rpStudioA" value="#f7f7f9"></label>
												<label class="rp-couleur"><span class="rp-libelle">Bords</span><input type="color" id="rpStudioB" value="#eceef1"></label>
											</div>
										</div>
										<div class="rp-sous" data-pour-fond="couleur" hidden>
											<div class="rp-pastilles" role="group" aria-label="Couleurs proposées">
												@foreach ($rpCouleursFond as $hex => $nom)
												<button type="button" class="rp-pastille" data-couleur="{!! $hex !!}" style="--rp-pastille: {!! $hex !!}" title="{!! $nom !!}" aria-label="{!! $nom !!}"></button>
												@endforeach
												<label class="rp-pastille rp-pastille--libre" title="Autre couleur"><input type="color" id="rpFondCouleur" value="#ffffff" aria-label="Autre couleur"><i class="bi bi-eyedropper" aria-hidden="true"></i></label>
											</div>
										</div>
										<div class="rp-sous" data-pour-fond="flou" hidden>
											@php $rpCurseur(['fondFlou', 'Intensité du flou', 0, 100, 1, '']); @endphp
										</div>
										<div class="rp-sous" data-pour-fond="image" hidden>
											<button type="button" class="rp-btn rp-btn--petit" id="rpImageFondChoisir"><i class="bi bi-image" aria-hidden="true"></i> Choisir l'image de fond</button>
											<input type="file" id="rpImageFond" accept="image/jpeg,image/png,image/webp" hidden>
											<span class="rp-aide" id="rpImageFondNom">Aucune image choisie</span>
										</div>
										<div class="rp-sous" data-pour-fond="transparent" hidden>
											<p class="rp-aide">Le fond transparent s'exporte en PNG ou en WebP.</p>
										</div>

										<div class="rp-sous rp-sous--bord" data-pour-fond="studio couleur flou image transparent" hidden>
											<p class="rp-libelle rp-libelle--groupe">Bord de la personne</p>
											@php $rpCurseur(['contour', 'Contour', -100, 100, 1, '']); @endphp
											@php $rpCurseur(['durete', 'Netteté du bord', 0, 100, 1, '']); @endphp
											<label class="rp-case"><input type="checkbox" id="rpPourtour" checked> Retirer la couleur de l'ancien fond dans les cheveux</label>
										</div>
									</div>
								</details>

								<details class="rp-bloc rp-volet" open>
									<summary class="rp-volet__titre">Lumière</summary>
									<div class="rp-volet__corps">@php foreach ($rpGroupes['lumiere'] as $c) { $rpCurseur($c); } @endphp</div>
								</details>

								<details class="rp-bloc rp-volet" open>
									<summary class="rp-volet__titre">Couleur</summary>
									<div class="rp-volet__corps">
										<div class="rp-balance">
											<span class="rp-libelle">Balance des blancs</span>
											<button type="button" class="rp-btn rp-btn--petit" id="rpBalanceAuto" title="Neutralise la dominante de couleur">Auto</button>
											<button type="button" class="rp-btn rp-btn--icone rp-btn--petit" id="rpPipette" aria-pressed="false" title="Cliquez ensuite sur un blanc ou un gris de la photo" aria-label="Pipette : cliquer sur un gris de la photo"><i class="bi bi-eyedropper" aria-hidden="true"></i></button>
										</div>
										@php foreach ($rpGroupes['couleur'] as $c) { $rpCurseur($c); } @endphp
									</div>
								</details>

								<details class="rp-bloc rp-volet" open>
									<summary class="rp-volet__titre">Portrait</summary>
									<div class="rp-volet__corps">
										<p class="rp-libelle rp-libelle--groupe">Éclairage studio</p>
										<div class="rp-eclairages" id="rpEclairages" role="group" aria-label="Éclairage studio">
											@foreach ($rpEclairages as [$cle, $icone, $nom, $desc])
											<button type="button" class="rp-type-masque rp-eclairage" data-eclairage="{!! $cle !!}" aria-pressed="false" title="{!! htmlspecialchars($desc) !!}">
												<i class="bi {!! $icone !!}" aria-hidden="true"></i><span>{!! htmlspecialchars($nom) !!}</span>
											</button>
											@endforeach
										</div>
										<p class="rp-aide">Posé sur le visage détecté, avec des masques que vous pouvez ajuster ensuite dans « Masques ».</p>
										<p class="rp-libelle rp-libelle--groupe">Teint</p>
										<div class="rp-teints-peau" id="rpTeints" role="group" aria-label="Teint de la peau">
											@foreach ($rpTeints as [$cle, $nom, $desc])
											<button type="button" class="rp-teint" data-teint="{!! $cle !!}" aria-pressed="false" title="{!! htmlspecialchars($desc) !!}">
												<span class="rp-teint__pastille" aria-hidden="true"></span><span class="rp-teint__nom">{!! htmlspecialchars($nom) !!}</span>
											</button>
											@endforeach
										</div>
										<p class="rp-aide">Le teint change la teinte de la peau et garde sa carnation : une peau foncée reste foncée. Réglages fins ci-dessous.</p>
										<p class="rp-libelle rp-libelle--groupe">Peau</p>
										<p class="rp-aide">Ces réglages ne touchent que la peau, repérée par le détourage.</p>
										@php foreach ($rpGroupes['portrait'] as $c) { $rpCurseur($c); } @endphp
									</div>
								</details>

								<details class="rp-bloc rp-volet">
									<summary class="rp-volet__titre">Couleur par teinte</summary>
									<div class="rp-volet__corps">
										<div class="rp-teintes" role="radiogroup" aria-label="Teinte à régler">
											@php($premier = true)
@foreach ($rpTeintes as $cle => [$nom, $hex])
											<label class="rp-teinte" title="{!! $nom !!}"><input type="radio" name="rpTeinte" value="{!! $cle !!}" {!! $premier ? 'checked' : '' !!}><span style="--rp-teinte: {!! $hex !!}" aria-hidden="true"></span><span class="rp-visuel-masque">{!! $nom !!}</span></label>
											@php($premier = false)
@endforeach
										</div>
										<p class="rp-libelle rp-libelle--groupe" id="rpTeinteNom">Rouges</p>
										@php $rpCurseur(['tslT', 'Teinte', -100, 100, 1, '']); @endphp
										@php $rpCurseur(['tslS', 'Saturation', -100, 100, 1, '']); @endphp
										@php $rpCurseur(['tslL', 'Luminance', -100, 100, 1, '']); @endphp
									</div>
								</details>

								<details class="rp-bloc rp-volet">
									<summary class="rp-volet__titre">Virage partiel</summary>
									<div class="rp-volet__corps">
										<p class="rp-libelle rp-libelle--groupe">Ombres</p>
										@php $rpCurseur(['virageOT', 'Teinte', 0, 360, 1, '°']); @endphp
										@php $rpCurseur(['virageOS', 'Saturation', 0, 100, 1, '']); @endphp
										<p class="rp-libelle rp-libelle--groupe">Hautes lumières</p>
										@php $rpCurseur(['virageHT', 'Teinte', 0, 360, 1, '°']); @endphp
										@php $rpCurseur(['virageHS', 'Saturation', 0, 100, 1, '']); @endphp
									</div>
								</details>

								<details class="rp-bloc rp-volet" open>
									<summary class="rp-volet__titre">Effets</summary>
									<div class="rp-volet__corps">@php foreach ($rpGroupes['effets'] as $c) { $rpCurseur($c); } @endphp</div>
								</details>

								<details class="rp-bloc rp-volet" id="rpVoletMasques" open>
									<summary class="rp-volet__titre">Masques</summary>
									<div class="rp-volet__corps">
										<p class="rp-aide" id="rpMasquesVide">Un masque limite une retouche à une zone : éclaircir le visage, assombrir le fond, adoucir un ciel… Choisissez la zone, puis réglez-la.</p>
										@foreach ($rpTypesMasque as $groupe => $types)
										<p class="rp-libelle rp-libelle--groupe">{!! htmlspecialchars($groupe) !!}</p>
										<div class="rp-types-masque" role="group" aria-label="Nouveau masque : {!! htmlspecialchars($groupe) !!}">
											@foreach ($types as [$type, $icone, $court, $complet])
											<button type="button" class="rp-type-masque" data-type-masque="{!! $type !!}" title="{!! htmlspecialchars($complet) !!}">
												<i class="bi {!! $icone !!}" aria-hidden="true"></i><span>{!! htmlspecialchars($court) !!}</span>
											</button>
											@endforeach
										</div>
										@endforeach

										<ol class="rp-masques" id="rpListeMasques" aria-label="Masques de la photo"></ol>

										<div class="rp-masque" id="rpMasque" hidden>
											<p class="rp-aide" id="rpMasqueAide" aria-live="polite"></p>
											<div class="rp-masque__pinceau" data-si-pinceau hidden>
												<label class="rp-champ rp-champ--ligne"><span class="rp-libelle">Douceur du pinceau</span><input type="range" id="rpMasqueDouceur" min="0" max="100" value="50"></label>
											</div>
											<button type="button" class="rp-lien" id="rpMasqueEffacer" hidden><i class="bi bi-eraser" aria-hidden="true"></i> Effacer ce qui a été peint sur ce masque</button>
											<label class="rp-champ" data-pour-masque="pinceau lineaire radial"><span class="rp-libelle">Limiter à</span>
												<select id="rpMasqueLimite">
													<option value="">Toute la photo</option>
													<option value="sujet">La personne</option>
													<option value="fond">L'arrière-plan</option>
												</select>
											</label>
											<div class="rp-masque__options">
												<label class="rp-case"><input type="checkbox" id="rpMasqueInverser"> Inverser la zone</label>
												<label class="rp-case"><input type="checkbox" id="rpMasqueVoir" checked> Zone en rouge au survol de la photo</label>
											</div>
											@php $rpCurseur(['m_quantite', 'Intensité', 0, 100, 1, '%'], 'rpm-'); @endphp
											<div data-pour-masque="radial">@php $rpCurseur(['m_plume', 'Contour progressif', 0, 100, 1, ''], 'rpm-'); @endphp</div>
											@foreach ($rpMasqueReglages as $groupe => $liste)
											<p class="rp-libelle rp-libelle--groupe">{!! htmlspecialchars($groupe) !!}</p>
											@php foreach ($liste as $c) { $rpCurseur($c, 'rpm-'); } @endphp
											@endforeach
											<button type="button" class="rp-lien" id="rpMasqueZero"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Remettre les réglages du masque à zéro</button>
										</div>
									</div>
								</details>

								<details class="rp-bloc rp-volet" id="rpVoletFiligrane" open>
									<summary class="rp-volet__titre">Filigrane</summary>
									<div class="rp-volet__corps">
										<label class="rp-bascule">
											<input type="checkbox" id="rpFiligraneActif" role="switch">
											<span>Ajouter un filigrane aux photos exportées</span>
										</label>
										<p class="rp-aide" id="rpFiligraneVide">Un texte ou un logo posé sur chaque export, à l'emplacement et à la taille de votre choix. La photo d'origine n'est jamais marquée.</p>

										<div class="rp-filigrane" id="rpFiligrane" hidden>
											<div class="rp-segment rp-segment--plein" role="radiogroup" aria-label="Type de filigrane">
												<label><input type="radio" name="rpFiligraneType" value="texte" checked><span><i class="bi bi-fonts" aria-hidden="true"></i> Texte</span></label>
												<label><input type="radio" name="rpFiligraneType" value="logo"><span><i class="bi bi-patch-check" aria-hidden="true"></i> Logo</span></label>
											</div>

											<div class="rp-sous" data-pour-filigrane="texte">
												<label class="rp-champ"><span class="rp-libelle">Texte</span>
													<textarea id="rpFiligraneTexte" rows="2" maxlength="160" spellcheck="false" aria-describedby="rpFiligraneTexteAide"></textarea>
												</label>
												<p class="rp-aide" id="rpFiligraneTexteAide">Entrée pour passer à la ligne.</p>
												<div class="rp-filigrane__police">
													<label class="rp-champ"><span class="rp-libelle">Police</span>
														<select id="rpFiligranePolice">
															<option value="sans">Moderne</option>
															<option value="serif">Classique</option>
															<option value="mono">Machine à écrire</option>
														</select>
													</label>
													<div class="rp-filigrane__styles" role="group" aria-label="Style du texte">
														<button type="button" class="rp-btn rp-btn--icone" id="rpFiligraneGras" aria-pressed="true" title="Gras" aria-label="Gras"><i class="bi bi-type-bold" aria-hidden="true"></i></button>
														<button type="button" class="rp-btn rp-btn--icone" id="rpFiligraneItalique" aria-pressed="false" title="Italique" aria-label="Italique"><i class="bi bi-type-italic" aria-hidden="true"></i></button>
													</div>
												</div>
												<span class="rp-libelle">Couleur</span>
												<div class="rp-pastilles" role="group" aria-label="Couleur du texte">
													@foreach ($rpFiligraneCouleurs as $hex => $nom)
													<button type="button" class="rp-pastille" data-couleur-filigrane="{!! $hex !!}" style="--rp-pastille: {!! $hex !!}" title="{!! $nom !!}" aria-label="{!! $nom !!}" aria-pressed="false"></button>
													@endforeach
													<label class="rp-pastille rp-pastille--libre" title="Autre couleur"><input type="color" id="rpFiligraneCouleur" value="#ffffff" aria-label="Autre couleur du texte"><i class="bi bi-eyedropper" aria-hidden="true"></i></label>
												</div>
											</div>

											<div class="rp-sous" data-pour-filigrane="logo" hidden>
												<div class="rp-logos" role="group" aria-label="Logo">
													<button type="button" class="rp-logo-choix" data-logo="universite" aria-pressed="true">
														<span class="rp-logo-choix__vue" style="--rp-logo: url('{{ $logoUrl }}')" aria-hidden="true"></span>
														<span class="rp-logo-choix__nom">L'université</span>
													</button>
													<button type="button" class="rp-logo-choix" data-logo="perso" aria-pressed="false" title="Choisir une image sur cet appareil">
														<span class="rp-logo-choix__vue rp-logo-choix__vue--vide" id="rpFiligraneLogoVue" aria-hidden="true"><i class="bi bi-upload"></i></span>
														<span class="rp-logo-choix__nom">Mon logo…</span>
													</button>
												</div>
												<input type="file" id="rpFiligraneFichier" accept="image/png,image/webp,image/jpeg" hidden>
												<p class="rp-aide" id="rpFiligraneLogoNom">Un PNG au fond transparent donne le meilleur résultat.</p>
												<div class="rp-champ"><span class="rp-libelle" id="rpFiligraneTeinteNom">Couleur du logo</span>
													<div class="rp-segment rp-segment--plein" role="radiogroup" aria-labelledby="rpFiligraneTeinteNom">
														<label><input type="radio" name="rpFiligraneTeinte" value="origine" checked><span>D'origine</span></label>
														<label><input type="radio" name="rpFiligraneTeinte" value="blanc"><span>Blanc</span></label>
														<label><input type="radio" name="rpFiligraneTeinte" value="noir"><span>Noir</span></label>
													</div>
												</div>
											</div>

											<label class="rp-case"><input type="checkbox" id="rpFiligraneOmbre" checked> Ombre douce, pour rester lisible sur toute photo</label>

											<span class="rp-libelle">Emplacement</span>
											<div class="rp-filigrane__place">
												<div class="rp-ancres" role="group" aria-label="Emplacement du filigrane">
													@foreach ($rpFiligranePositions as $pos => $nom)
													<button type="button" class="rp-ancre" data-position="{!! $pos !!}" aria-pressed="false" title="{!! $nom !!}" aria-label="{!! $nom !!}"><span aria-hidden="true"></span></button>
													@endforeach
												</div>
												<button type="button" class="rp-mosaique" data-position="mosaique" aria-pressed="false" title="Le filigrane répété sur toute la photo">
													<i class="bi bi-grid-3x3" aria-hidden="true"></i>
													<span class="rp-mosaique__nom">Mosaïque</span>
													<span class="rp-mosaique__aide">répété sur toute la photo</span>
												</button>
											</div>

											@foreach ($rpFiligraneCurseurs as [$cle, $libelle, $min, $max, $pas, $unite, $depart, $titre])
											<div class="rp-curseur" data-filigrane="{!! $cle !!}"{!! $cle === 'espacement' ? ' hidden' : '' !!}>
												<label class="rp-curseur__nom" for="rpf-{!! $cle !!}" title="{!! htmlspecialchars($titre !== '' ? $titre . ' ; double-clic : valeur de départ' : 'Double-clic : valeur de départ') !!}">{!! htmlspecialchars($libelle) !!}</label>
												<input type="number" class="rp-curseur__valeur" min="{!! $min !!}" max="{!! $max !!}" step="{!! $pas !!}" value="{!! $depart !!}" aria-label="{!! htmlspecialchars($libelle) !!} (valeur)">
												@if ($unite !== '')<span class="rp-curseur__unite">{!! $unite !!}</span>@endif
												<input type="range" class="rp-curseur__piste" id="rpf-{!! $cle !!}" min="{!! $min !!}" max="{!! $max !!}" step="{!! $pas !!}" value="{!! $depart !!}">
											</div>
											@endforeach

											<p class="rp-aide">Le filigrane apparaît sur la photo pendant la retouche et s'ajoute à chaque export, ZIP compris. Il reste le même d'une photo à l'autre.</p>
										</div>
									</div>
								</details>

								<section class="rp-bloc rp-export" aria-labelledby="rpExportTitre">
									<h2 class="rp-volet__titre rp-volet__titre--fixe" id="rpExportTitre">Exporter</h2>
									<div class="rp-export__grille">
										<label class="rp-champ"><span class="rp-libelle">Format</span>
											<select id="rpFormat">
												<option value="image/jpeg">JPEG</option>
												<option value="image/png">PNG</option>
												<option value="image/webp">WebP</option>
											</select>
										</label>
										<label class="rp-champ"><span class="rp-libelle">Taille</span>
											<select id="rpTaille">
												<option value="0">Taille réelle</option>
												<option value="3000">3000 px</option>
												<option value="2000">2000 px</option>
												<option value="1200">1200 px</option>
												<option value="800">800 px</option>
											</select>
										</label>
									</div>
									<div class="rp-curseur rp-curseur--qualite" id="rpQualiteBloc">
										<label class="rp-curseur__nom" for="rpQualite">Qualité</label>
										<input type="number" class="rp-curseur__valeur" data-pour="rpQualite" min="50" max="100" step="1" value="95" aria-label="Qualité (valeur)">
										<input type="range" class="rp-curseur__piste" id="rpQualite" min="50" max="100" step="1" value="95">
									</div>
									<label class="rp-champ"><span class="rp-libelle" title="Accentue légèrement le détail après le redimensionnement, comme « netteté pour l'écran » dans Lightroom : une photo réduite en a besoin">Netteté à l'export</span>
										<select id="rpNetteteSortie">
											<option value="aucune">Aucune</option>
											<option value="legere">Légère</option>
											<option value="standard" selected>Standard (conseillée)</option>
											<option value="forte">Forte</option>
										</select>
									</label>
									<label class="rp-champ"><span class="rp-libelle">Nom du fichier</span>
										<input type="text" id="rpNom" maxlength="120" autocomplete="off" spellcheck="false">
									</label>
									<p class="rp-aide" id="rpDimensions"></p>
									<div class="rp-export__boutons">
										<button type="button" class="rp-btn rp-btn--primaire" id="rpExporter"><i class="bi bi-download" aria-hidden="true"></i> Exporter la photo</button>
										<button type="button" class="rp-btn" id="rpExporterTout" hidden><i class="bi bi-file-zip" aria-hidden="true"></i> Tout exporter (ZIP)</button>
										<button type="button" class="rp-btn" id="rpProfilOuvrir"><i class="bi bi-person-bounding-box" aria-hidden="true"></i> Mettre en photo de profil</button>
										{{-- La photo venue du Studio UAZ (« Retoucher dans l'editeur ») y retourne, dans son emplacement. --}}
										<button type="button" class="rp-btn rp-btn--primaire" id="rpStudioRenvoyer" hidden><i class="bi bi-box-arrow-in-left" aria-hidden="true"></i> Renvoyer au Studio UAZ</button>
									</div>
								</section>
							</fieldset>
						</aside>

						{{-- La photo retouchée devient la photo d'un membre de l'équipe ou d'un témoignage (atelier.js, « Photo de profil » ; RetouchePhotoController). --}}
						<dialog class="rp-profil" id="rpProfil" aria-labelledby="rpProfilTitre">
							<div class="rp-profil__tete">
								<h2 class="rp-profil__titre" id="rpProfilTitre">Mettre en photo de profil</h2>
								<button type="button" class="rp-btn rp-btn--icone rp-btn--petit rp-profil__fermer" id="rpProfilFermer" aria-label="Fermer"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
							</div>
							<div class="rp-profil__corps">
								<figure class="rp-profil__nouvelle">
									<div class="rp-profil__cadre">
										<img id="rpProfilApercu" alt="La photo retouchée, telle qu'elle sera enregistrée" hidden>
										<span class="rp-profil__prepa" id="rpProfilPrepa"><span class="rp-etat__roue" aria-hidden="true"></span> Préparation…</span>
									</div>
									<figcaption class="rp-aide" id="rpProfilDims"></figcaption>
								</figure>
								<div class="rp-profil__choix">
									<div class="rp-profil__types" role="radiogroup" aria-label="Photo de profil de">
										<label class="rp-profil__type"><input type="radio" name="rpProfilType" value="equipe" checked><span><i class="bi bi-people" aria-hidden="true"></i> Équipe</span></label>
										<label class="rp-profil__type"><input type="radio" name="rpProfilType" value="temoignage"><span><i class="bi bi-chat-quote" aria-hidden="true"></i> Témoignage</span></label>
									</div>
									<label class="rp-champ"><span class="rp-libelle" id="rpProfilQLibelle">Nom ou fonction</span>
										<input type="search" id="rpProfilQ" maxlength="80" autocomplete="off" spellcheck="false" aria-controls="rpProfilListe">
									</label>
									<ul class="rp-profil__liste" id="rpProfilListe" aria-live="polite" aria-label="Personnes trouvées"></ul>
									<p class="rp-aide rp-profil__info" id="rpProfilInfo"></p>
								</div>
							</div>
							<div class="rp-profil__pied">
								<div class="rp-profil__choisie" id="rpProfilChoisie" hidden>
									<span class="rp-profil__avant" id="rpProfilAvant"></span>
									<i class="bi bi-arrow-right" aria-hidden="true"></i>
									<span class="rp-profil__apres"><img id="rpProfilApres" alt=""></span>
									<span class="rp-profil__qui"><strong id="rpProfilNom"></strong><span class="rp-aide" id="rpProfilDetail"></span></span>
								</div>
								<p class="rp-aide rp-profil__note" id="rpProfilNote">Sans filigrane, en JPEG. La nouvelle photo est ajoutée à la médiathèque et remplace l'ancienne sur la fiche ; l'ancienne reste dans la médiathèque.</p>
								<div class="rp-profil__actions">
									<button type="button" class="rp-btn" id="rpProfilAnnuler">Annuler</button>
									<button type="button" class="rp-btn rp-btn--primaire" id="rpProfilValider" disabled><i class="bi bi-check2" aria-hidden="true"></i> <span id="rpProfilValiderTexte">Remplacer la photo</span></button>
								</div>
							</div>
						</dialog>
					</div>
</div>
<script src="{{ asset('js/admin/retouche/toast.js') }}?v={{ filemtime(public_path('js/admin/retouche/toast.js')) }}"></script>
<script src="{{ asset('js/admin/retouche/sauvegarde.js') }}?v={{ filemtime(public_path('js/admin/retouche/sauvegarde.js')) }}"></script>
<script src="{{ asset('js/admin/retouche/filtres.js') }}?v={{ filemtime(public_path('js/admin/retouche/filtres.js')) }}"></script>
<script src="{{ asset('js/admin/retouche/moteur.js') }}?v={{ filemtime(public_path('js/admin/retouche/moteur.js')) }}"></script>
<script src="{{ asset('js/admin/retouche/masque.js') }}?v={{ filemtime(public_path('js/admin/retouche/masque.js')) }}"></script>
<script src="{{ asset('js/admin/retouche/atelier.js') }}?v={{ filemtime(public_path('js/admin/retouche/atelier.js')) }}"></script>
</body>
</html>
