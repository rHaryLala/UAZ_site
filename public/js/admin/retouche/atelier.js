/* =============================================================================
   Retouche photo — l'atelier — src/retouche-photo.php

   Rendu : src/js/retouche-photo.moteur.js (WebGL 2). Detourage et textures de
   travail : src/js/retouche-photo.masque.js (MODNet et MediaPipe, a la demande).

   Chaque photo ouverte garde ses reglages et son historique (Ctrl+Z, Ctrl+Y).
   Ses masques (reglages.masques) portent chacun une zone et des reglages
   locaux ; leur pinceau est dans photo.calques, comme les corrections du
   detourage dans photo.retouches.
   Les donnees lourdes (image de travail, detourage, textures) ne restent que
   pour les dernieres photos affichees ; les autres les recalculent au retour.

   Le preregle « Studio UAZ » mesure la photo (la peau du visage, detouree)
   avant de poser ses valeurs : la balance des blancs ramene la peau a la
   teinte de la photo de reference (un portrait de studio), l'exposition vise
   sa luminosite, puis la courbe des tons, le fond blanc de studio et le
   cadrage portrait. Applique a une serie, il donne des photos homogenes.
   ========================================================================== */
(function () {
	'use strict';

	const $ = (id) => document.getElementById(id);
	const app = $('rpApp');
	if (!app) { return; }
	const M = window.RetoucheMoteur, D = window.RetoucheMasque;
	const scene = $('rpScene'), canvas = $('rpCanvas');

	const toast = (type, texte, duree) => {
		if (window.Toast && typeof window.Toast[type] === 'function') { return window.Toast[type](echapper(texte), duree ? { duration: duree } : {}); }
		return null;
	};
	function echapper(t) { const d = document.createElement('div'); d.textContent = String(t); return d.innerHTML; }

	let moteur = null;
	try { moteur = M && M.creer(canvas); } catch (e) { console.error(e); moteur = null; }
	if (!moteur) {
		const boite = document.querySelector('.rp-vide__boite');
		if (boite) {
			boite.querySelector('.rp-vide__titre').textContent = 'La retouche ne peut pas démarrer dans ce navigateur';
			boite.querySelector('.rp-vide__texte').textContent = "Elle a besoin de WebGL 2, absent ou désactivé ici. Ouvrez cette page dans Chrome, Edge ou Firefox à jour ; si c'est déjà le cas, activez l'accélération matérielle dans les paramètres du navigateur.";
			const b = $('rpChoisir'); if (b) { b.hidden = true; }
		}
		$('rpAjouter').disabled = true;
		return;
	}

	/* --- Constantes ------------------------------------------------------- */
	const TAILLE_MAX = Math.min(moteur.maxTexture || 4096, 8192);
	const PILE_MAX = 60;
	const GARDER = 3;           // photos dont on garde detourage et textures
	const CLE_PERSO = 'retouche-photo.prereglages';
	const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

	/* Reference du preregle Studio UAZ, mesuree sur le portrait de studio
	   fourni comme modele (peau du visage, lumiere lineaire) : la balance des
	   blancs ramene la peau a ces rapports vert/rouge et bleu/rouge, et
	   l'exposition rapproche sa luminance de PEAU_LUMINANCE. */
	const PEAU_CIBLE = [0.7054, 0.4457, 0.3782];
	const PEAU_LUMINANCE = 0.2126 * PEAU_CIBLE[0] + 0.7152 * PEAU_CIBLE[1] + 0.0722 * PEAU_CIBLE[2];
	/* Part de la correction de la peau confiee a la balance des blancs de toute
	   l image ; le reste passe par les reglages « Portrait », sur la peau seule.
	   Tout confier a la balance globale bleuissait le reste : sur la photo
	   modele, le chemisier rose virait au lilas. */
	const PART_GLOBALE = 0.6;
	const STUDIO_TONS = { contraste: 20, hautesLumieres: 0, ombres: 10, blancs: 32, noirs: -60, vibrance: 4, saturation: -4, clarte: 4, nettete: 32, lissage: 18, vignette: 0, virageOT: 225, virageOS: 30, virageHT: 35, virageHS: 8 };

	const TSL_CLES = M.TSL_ORDRE;
	const TEINTES_NOMS = { rouge: 'Rouges', orange: 'Oranges', jaune: 'Jaunes', vert: 'Verts', aqua: 'Turquoises', bleu: 'Bleus', violet: 'Violets', magenta: 'Magentas' };

	/* Masques : une retouche limitee a une zone. Zone trouvee par le detourage
	   (classe : carte de src/js/retouche-photo.masque.js, multipliee par la
	   personne), peinte, ou en degrade. Le pinceau du masque complete toute
	   zone (photo.calques[id] : 128 neutre, plus = ajoute, moins = retire). */
	const MASQUES_MAX = 8;
	const TYPES_MASQUE = {
		sujet: { nom: 'Sujet', icone: 'bi-person-fill', detourage: true },
		fond: { nom: 'Arrière-plan', icone: 'bi-image', detourage: true },
		cheveux: { nom: 'Cheveux', icone: 'bi-scissors', detourage: true, classe: 'cheveux' },
		peauVisage: { nom: 'Peau du visage', icone: 'bi-emoji-smile', detourage: true, classe: 'visage' },
		peauCorps: { nom: 'Peau du corps', icone: 'bi-person-arms-up', detourage: true, classe: 'peauCorps' },
		vetements: { nom: 'Vêtements', icone: 'bi-person-standing-dress', detourage: true, classe: 'vetements' },
		pinceau: { nom: 'Pinceau', icone: 'bi-brush' },
		lineaire: { nom: 'Dégradé linéaire', icone: 'bi-distribute-vertical', degrade: true },
		radial: { nom: 'Dégradé radial', icone: 'bi-record-circle', degrade: true },
	};
	/* Teints de peau : la teinte visee (degres, HSV sRGB), la saturation et la
	   luminosite de la peau (curseurs Portrait). Naturel et Chaleureux : les
	   teintes mesurees sur les deux portraits de studio fournis comme modeles
	   (16 et 22 degres, 13/10/2026). La carnation de la personne est gardee :
	   seule la teinte change, comme le ferait un eclairage. */
	const TEINTS = {
		naturel: { h: 16, sat: 0, lum: 0 },
		chaleureux: { h: 21, sat: 8, lum: 4 },
		dore: { h: 26, sat: 14, lum: 6 },
		lumineux: { h: 18, sat: 0, lum: 16 },
		doux: { h: 15, sat: -14, lum: 8 },
	};
	const LOCAUX = ['exposition', 'contraste', 'hautesLumieres', 'ombres', 'blancs', 'noirs', 'temperature', 'teinte', 'saturation', 'clarte', 'nettete'];
	const LOCAUX_NOMS = { exposition: 'Exposition', contraste: 'Contraste', hautesLumieres: 'Hautes lumières', ombres: 'Ombres', blancs: 'Blancs', noirs: 'Noirs', temperature: 'Température', teinte: 'Teinte', saturation: 'Saturation', clarte: 'Clarté', nettete: 'Netteté' };
	const AIDES_MASQUE = {
		detourage: "En rouge : la zone du masque, trouvée par le détourage. « Peindre » l'agrandit, « Gommer » la réduit (Alt inverse le pinceau).",
		pinceau: 'Peignez la zone à retoucher, gommez ce qui déborde. Alt inverse le pinceau ; les touches [ et ] changent sa taille.',
		lineaire: "Glissez sur la photo pour tracer le dégradé : plein effet au départ, aucun à l'arrivée. Les poignées le déplacent.",
		radial: 'Glissez sur la photo pour tracer le cercle, puis ajustez-le : la poignée de côté le fait aussi tourner.',
		attente: 'Détection de la personne… la zone apparaît dans un instant.',
	};

	function reglagesDefaut() {
		const tsl = {};
		TSL_CLES.forEach((k) => { tsl[k] = [0, 0, 0]; });
		return {
			exposition: 0, contraste: 0, hautesLumieres: 0, ombres: 0, blancs: 0, noirs: 0,
			temperature: 0, teinte: 0, vibrance: 0, saturation: 0,
			clarte: 0, nettete: 0, lissage: 0, vignette: 0,
			virageOT: 220, virageOS: 0, virageHT: 40, virageHS: 0,
			peauTemperature: 0, peauTeinte: 0, peauSaturation: 0, peauLuminosite: 0,
			tsl,
			fond: { mode: 'original', couleur: '#ffffff', studioA: '#f7f7f9', studioB: '#eceef1', centreY: 0.5, flou: 70, contour: 0, durete: 0, pourtour: true, image: null },
			teint: null, eclairage: null,   // preregles du volet Portrait (pour les montrer choisis)
			rotation: 0, miroir: false, cadre: { x: 0, y: 0, w: 1, h: 1 }, ratio: 'libre',
			perso: { l: 1080, h: 1080, exact: false },
			filtre: { nom: 'aucun', force: 100 },
			masques: [],
		};
	}
	const DEFAUT = reglagesDefaut();
	const copie = (o) => JSON.parse(JSON.stringify(o));

	/* --- Etat ------------------------------------------------------------- */
	const photos = [];
	let active = null;
	let compteur = 0;
	let recents = [];
	const imagesFond = new Map();     // id -> { image, url, nom }
	let imageFondChargee = null;      // id de l'image de fond dans le moteur
	let teinteCourante = 'rouge';
	const etat = {
		mode: null,           // null | 'recadrer' | 'pinceau' | 'masque'
		masqueId: null,       // masque en cours (mode 'masque')
		voirZone: true,       // la zone du masque en rouge (souris sur la photo)
		survol: false,        // la souris est sur la photo
		flashZone: false,     // la zone se montre un instant au choix du masque
		pipette: false,
		avant: null,          // abscisse de la coupure (0..1) ou null
		zoom: null,           // null = ajuster ; sinon pixels ecran (physiques) par pixel image
		centre: { x: 0.5, y: 0.5 },
		geo: null,
		occupe: false,
	};
	const pinceau = { taille: 70, douceur: 50, mode: 'ajouter' };
	const pinceauMasque = { mode: 'non' };   // 'non' | 'ajouter' | 'retirer' ; taille et douceur : celles du pinceau

	/* --- Elements --------------------------------------------------------- */
	const el = {
		fichiers: $('rpFichiers'), vide: $('rpVide'), depot: $('rpDepot'), etat: $('rpEtat'), etatTexte: $('rpEtatTexte'),
		pellicule: $('rpPellicule'), vignettes: $('rpVignettes'), reglages: $('rpReglages'),
		annuler: $('rpAnnuler'), retablir: $('rpRetablir'), avantApres: $('rpAvantApres'), recadrer: $('rpRecadrer'), pinceau: $('rpPinceau'),
		zoomAjuster: $('rpZoomAjuster'), zoom100: $('rpZoom100'), zoomVal: $('rpZoomVal'),
		cadre: $('rpCadre'), coupure: $('rpCoupure'), curseur: $('rpCurseurPinceau'),
		outilsRecadrage: $('rpOutilsRecadrage'), outilsPinceau: $('rpOutilsPinceau'), ratio: $('rpRatio'),
		histo: $('rpHisto'), infos: $('rpInfos'), detourage: $('rpDetourage'), detourageTexte: $('rpDetourageTexte'),
		format: $('rpFormat'), taille: $('rpTaille'), qualite: $('rpQualite'), qualiteBloc: $('rpQualiteBloc'), netteteSortie: $('rpNetteteSortie'), nom: $('rpNom'), dimensions: $('rpDimensions'),
		exporter: $('rpExporter'), exporterTout: $('rpExporterTout'),
		persoListe: $('rpPersoListe'), persoNom: $('rpPersoNom'),
		masques: $('rpMasques'), voletMasques: $('rpVoletMasques'), listeMasques: $('rpListeMasques'), masque: $('rpMasque'),
		masquesVide: $('rpMasquesVide'), outilsMasque: $('rpOutilsMasque'), poignees: $('rpPoignees'),
		grilleBouton: $('rpGrilleBouton'),
	};

	/* ======================================================================
	   Ouverture des photos
	   ====================================================================== */
	/* Decode une photo, reduite si elle depasse ce que la carte graphique tient. */
	async function decoder(f) {
		let image = await createImageBitmap(f);
		const largeur0 = image.width, hauteur0 = image.height;
		let reduite = false;
		const L = Math.max(largeur0, hauteur0);
		if (L > TAILLE_MAX) {
			const s = TAILLE_MAX / L;
			const petite = await createImageBitmap(f, { resizeWidth: Math.round(largeur0 * s), resizeHeight: Math.round(hauteur0 * s), resizeQuality: 'high' });
			image.close(); image = petite; reduite = true;
		}
		return { image, largeur0, hauteur0, reduite };
	}

	function nouvellePhoto(id, nom, fichier, d) {
		return Object.assign({
			id, nom, fichier,
			reglages: reglagesDefaut(), pile: [], index: -1, retouches: null,
			calques: {}, texMasques: null,   // pinceau des masques (par id), zones calculees pour le moteur
			detourageSauve: null,            // le detourage tel qu'enregistre (src/js/retouche-photo.sauvegarde.js)
			t: null, flou: null, seg: null, tex: null, mesures: null, detourage: null,
			modifie: false, exportee: false, nomExport: null, vignette: null, vignetteBlob: null,
			profil: null,                    // a qui elle a ete donnee en photo de profil (« Mettre en photo de profil »)
			studio: null,                    // venue du Studio UAZ : son emplacement, pour « Renvoyer au Studio UAZ »
		}, d);
	}

	async function ajouterFichiers(liste) {
		await repriseTerminee; // les numeros des photos reprises d'abord
		const tous = Array.from(liste || []);
		const fichiers = tous.filter((f) => TYPES.includes(f.type));
		const ignores = tous.length - fichiers.length;
		if (ignores > 0) {
			toast('warning', ignores === 1
				? "Un fichier n'a pas été ouvert : seules les photos JPEG, PNG et WebP s'ouvrent ici (une photo HEIC d'iPhone est à convertir d'abord en JPEG)."
				: ignores + " fichiers n'ont pas été ouverts : seules les photos JPEG, PNG et WebP s'ouvrent ici (une photo HEIC d'iPhone est à convertir d'abord en JPEG).", 9000);
		}
		for (const f of fichiers) {
			try {
				// numero unique d'un appareil a l'autre : les photos se retrouvent sur le serveur sans se confondre
				const id = window.RetoucheSauvegarde ? RetoucheSauvegarde.nouvelId() : ++compteur;
				const photo = nouvellePhoto(id, f.name.replace(/\.[^.]+$/, '') || 'photo', f, await decoder(f));
				photo.pile.push({ r: JSON.stringify(photo.reglages), m: null, c: photo.calques }); photo.index = 0;
				photos.push(photo);
				creerVignette(photo);
				sauverFichier(photo);
				planifierSauvegarde(photo);
				if (!active) { await activer(photo); }
			} catch (e) {
				console.error(e);
				toast('error', '« ' + f.name + " » n'a pas pu être ouverte : le fichier est peut-être abîmé.");
			}
		}
		majPellicule();
	}

	async function activer(photo) {
		if (!photo || active === photo) { return; }
		quitterModes();
		active = photo;
		moteur.source(photo.image);
		if (!photo.t) { photo.t = D.travail(photo.image); photo.flou = D.textureFlou(photo.t); }
		// corrections au pinceau reprises d'une seance precedente : a la taille de travail
		if (photo.retouches && photo.retouches.length !== photo.t.w * photo.t.h) { photo.retouches = null; }
		Object.keys(photo.calques).forEach((id) => {
			if (photo.calques[id].length !== photo.t.w * photo.t.h) { photo.calques = Object.assign({}, photo.calques); delete photo.calques[id]; }
		});
		moteur.travail('flou', photo.flou, photo.t.w, photo.t.h);
		cuireMasques(photo);
		if (photo.seg) {
			if (!photo.tex) { photo.tex = D.textures(photo.t, photo.seg, photo.retouches); }
			envoyerTextures(photo);
		}
		chargerImageFond(photo.reglages);
		recents = [photo].concat(recents.filter((p) => p !== photo));
		planifierSauvegarde(null); // la photo affichee
		elaguer();
		etat.zoom = null; etat.centre = { x: 0.5, y: 0.5 };
		el.nom.value = photo.nomExport || (photo.nom + '-retouche');
		majInterface();
		majPellicule();
		demanderRendu();
		// un detourage enregistre se reprend d'emblee : masques, teint et fonds sont prets
		if (besoinDetourage(photo.reglages) || retouchesPresentes(photo) || photo.detourageSauve) { assurerDetourage(photo); }
		else { majDetourage(photo.seg ? 'pret' : 'aucun'); }
	}

	/* Libere la memoire des photos les moins recemment affichees. */
	function elaguer() {
		recents.slice(GARDER).forEach((p) => { p.t = null; p.flou = null; p.seg = null; p.tex = null; p.texMasques = null; });
	}

	function envoyerTextures(photo) {
		const { w, h } = photo.t;
		moteur.travail('lisse', photo.tex.lisse, w, h);
		moteur.travail('premierPlan', photo.tex.premierPlan, w, h);
		moteur.travail('fondFlou', photo.tex.fondFlou, w, h);
		moteur.travail('masque', photo.tex.masque, w, h);
	}

	function retouchesPresentes(p) {
		if (!p.retouches) { return false; }
		for (let i = 0; i < p.retouches.length; i += 7) { if (p.retouches[i] !== 128) { return true; } }
		return false;
	}
	const besoinDetourage = (r) => r.fond.mode !== 'original' || r.lissage > 0
		|| !!(r.peauTemperature || r.peauTeinte || r.peauSaturation || r.peauLuminosite)
		|| (r.masques || []).some((m) => m.actif !== false && ((TYPES_MASQUE[m.type] && TYPES_MASQUE[m.type].detourage) || !!m.limite));

	/* ======================================================================
	   Detourage
	   ====================================================================== */
	function majDetourage(etatD, texte) {
		el.detourage.dataset.etat = etatD;
		el.detourageTexte.textContent = texte || {
			aucun: 'La personne sera détourée au premier changement de fond.',
			pret: 'Personne détourée. Le pinceau « Détourage » corrige un bord manqué.',
		}[etatD] || '';
	}

	function assurerDetourage(photo) {
		if (photo.seg) { return Promise.resolve(true); }
		if (photo.detourage) { return photo.detourage; }
		photo.detourage = (async function () {
			const premiere = !D.pret;
			const texte = photo.detourageSauve ? 'Reprise du détourage enregistré…'
				: (premiere ? 'Préparation du détourage : la première fois, environ 45 Mo à télécharger…' : 'Détourage de la personne…');
			if (active === photo) { majDetourage('encours', texte); }
			afficherEtat(texte);
			try {
				if (!photo.t) { photo.t = D.travail(photo.image); photo.flou = D.textureFlou(photo.t); }
				/* Le detourage enregistre avec la photo (sauvegarde automatique) :
				   repris en un instant au lieu d'etre refait (une vingtaine de
				   secondes, et les modeles a recharger). */
				let seg = null;
				if (photo.detourageSauve && window.RetoucheSauvegarde) {
					try { seg = await RetoucheSauvegarde.deballerDetourage(photo.detourageSauve, photo.t.w, photo.t.h); } catch (e) { console.warn('Detourage enregistre illisible', e); }
				}
				const repris = !!seg;
				if (!seg) {
					if (photo.detourageSauve) { afficherEtat(premiere ? 'Préparation du détourage : la première fois, environ 45 Mo à télécharger…' : 'Détourage de la personne…'); }
					seg = await D.segmenter(photo.image, photo.t, afficherEtat);
					sauverDetourage(photo, seg);
				}
				if (!photo.retouches) { photo.retouches = new Uint8Array(photo.t.w * photo.t.h).fill(128); }
				afficherEtat('Préparation du fond…');
				await new Promise((r) => setTimeout(r, 0));
				photo.seg = seg;
				photo.tex = D.textures(photo.t, seg, photo.retouches);
				photo.mesures = D.mesurer(photo.t, seg);
				if (active === photo) {
					envoyerTextures(photo);
					cuireMasques(photo);   // les masques Sujet, Cheveux... ont maintenant leur zone
					majOutilsMasque();
					majPortrait();         // les pastilles de teint prennent la peau mesuree
					demanderRendu();
					if (seg.personne) { majDetourage('pret'); }
					else { majDetourage('erreur', "Aucune personne trouvée : le pinceau « Détourage » permet de la désigner à la main."); }
				}
				if (!seg.personne && !repris) { toast('warning', "Aucune personne n'a été trouvée sur « " + photo.nom + " » : le fond reste celui d'origine. Le pinceau « Détourage » permet de désigner la personne à la main.", 9000); }
				return true;
			} catch (e) {
				console.error(e);
				if (active === photo) { majDetourage('erreur', "Le détourage n'a pas pu se charger. Vérifiez la connexion à Internet, puis réessayez."); }
				toast('error', "Le détourage n'a pas pu se charger (connexion à Internet ?). Lumière, couleur et recadrage restent disponibles.", 9000);
				return false;
			} finally {
				photo.detourage = null;
				cacherEtat();
			}
		})();
		return photo.detourage;
	}

	/* ======================================================================
	   Historique
	   ====================================================================== */
	function memoriser(photo) {
		const p = photo || active;
		if (!p) { return; }
		const instant = { r: JSON.stringify(p.reglages), m: p.retouches, c: p.calques };
		const haut = p.pile[p.index];
		if (haut && haut.r === instant.r && haut.m === instant.m && haut.c === instant.c) { return; }
		p.pile.length = p.index + 1;
		p.pile.push(instant);
		if (p.pile.length > PILE_MAX) { p.pile.shift(); }
		p.index = p.pile.length - 1;
		p.modifie = true; p.exportee = false;
		majBoutonsPile();
		planifierVignette(p);
		planifierSauvegarde(p);
	}

	function revenir(sens) {
		const p = active;
		if (!p) { return; }
		const i = p.index + sens;
		if (i < 0 || i >= p.pile.length) { return; }
		p.index = i;
		const instant = p.pile[i];
		const avantFond = p.reglages.fond.image;
		p.reglages = JSON.parse(instant.r);
		if (!p.reglages.masques) { p.reglages.masques = []; }
		if (instant.m !== p.retouches) { p.retouches = instant.m; appliquerRetouches(p, 0, p.t ? p.t.h : 0); }
		if (instant.c && instant.c !== p.calques) { p.calques = instant.c; }
		cuireMasques(p);
		if (etat.mode === 'masque' && !masqueChoisi()) { basculerMasque(false); }
		if (p.reglages.fond.image !== avantFond) { chargerImageFond(p.reglages); }
		p.modifie = p.index > 0;
		majInterface();
		demanderRendu();
		planifierVignette(p);
		planifierSauvegarde(p);
		if (besoinDetourage(p.reglages)) { assurerDetourage(p); }
	}

	function majBoutonsPile() {
		el.annuler.disabled = !active || active.index <= 0;
		el.retablir.disabled = !active || active.index >= active.pile.length - 1;
	}

	/* Le canal B du masque porte les corrections au pinceau (128 = aucune).
	   Elles changent aussi la personne des masques Sujet, Arriere-plan... */
	function appliquerRetouches(p, y0, y1) {
		if (!p.tex || !p.t) { return; }
		const w = p.t.w, m = p.retouches, tex = p.tex.masque;
		for (let i = y0 * w; i < y1 * w; i++) { tex[i * 4 + 2] = m ? m[i] : 128; }
		if (active === p && y1 > y0) { moteur.masquePartiel(tex, w, y0, y1); }
		(p.reglages.masques || []).forEach((mq, i) => {
			if (i < MASQUES_MAX && TYPES_MASQUE[mq.type] && TYPES_MASQUE[mq.type].detourage) { cuireLignes(p, i, y0, y1); }
		});
	}

	/* ======================================================================
	   Masques : zones calculees pour le moteur
	   ====================================================================== */
	/* Un canal par masque : texMasques.a pour les masques 1 a 4, b pour 5 a 8.
	   Carte (detourage, pinceau) : la zone toute faite, inversion comprise.
	   Degrade : le pinceau seul (128 neutre), le moteur calcule le degrade. */
	function cuireCanal(p, i, m, y0, y1) {
		const w = p.t.w, tab = i < 4 ? p.texMasques.a : p.texMasques.b, ch = i & 3;
		const k0 = y0 * w, k1 = y1 * w;
		if (!m) { for (let k = k0; k < k1; k++) { tab[k * 4 + ch] = 0; } return; }
		const cal = p.calques[m.id] || null;
		const def = TYPES_MASQUE[m.type] || {};
		if (def.degrade) { for (let k = k0; k < k1; k++) { tab[k * 4 + ch] = cal ? cal[k] : 128; } return; }
		const s = p.seg, alpha = def.detourage && s ? s.alpha : null, ret = p.retouches;
		const classe = alpha && def.classe ? s[def.classe] : null;
		// sans detourage (pas encore fait) : pas de zone, inversee ou non
		const inverse = m.inverse && (!def.detourage || !!alpha);
		for (let k = k0; k < k1; k++) {
			let base = 0;
			if (alpha) {
				let suj = alpha[k] + (ret ? (ret[k] - 128) / 127 : 0);
				suj = suj < 0 ? 0 : (suj > 1 ? 1 : suj);
				if (m.type === 'sujet') { base = suj; }
				else if (m.type === 'fond') { base = 1 - suj; }
				else if (classe) { let c = (classe[k] - 0.25) * 2; c = c < 0 ? 0 : (c > 1 ? 1 : c); base = c * suj; }
			}
			if (inverse) { base = 1 - base; }
			const v = base + (cal ? (cal[k] - 128) / 127 : 0);
			tab[k * 4 + ch] = v <= 0 ? 0 : (v >= 1 ? 255 : v * 255 + 0.5);
		}
	}

	/** Recalcule toutes les zones de la photo et les envoie au moteur si elle est affichee. */
	function cuireMasques(p) {
		if (!p || !p.t) { return; }
		const n = p.t.w * p.t.h, liste = p.reglages.masques || [];
		if (!p.texMasques) { p.texMasques = { a: new Uint8Array(n * 4), b: new Uint8Array(n * 4) }; }
		for (let i = 0; i < MASQUES_MAX; i++) { cuireCanal(p, i, liste[i], 0, p.t.h); }
		if (p === active) { moteur.calques(p.texMasques.a, p.texMasques.b, p.t.w, p.t.h); }
	}

	/** Une bande de lignes d'un masque (apres un coup de pinceau). */
	function cuireLignes(p, i, y0, y1) {
		if (!p.t || !p.texMasques || y1 <= y0) { return; }
		cuireCanal(p, i, (p.reglages.masques || [])[i], y0, y1);
		if (p === active) { moteur.calquesPartiel(i < 4 ? 'a' : 'b', i < 4 ? p.texMasques.a : p.texMasques.b, p.t.w, y0, y1); }
	}

	/* ======================================================================
	   Curseurs et champs
	   ====================================================================== */
	const ACCES = {
		fondFlou: [(r) => r.fond.flou, (r, v) => { r.fond.flou = v; }, 70],
		contour: [(r) => r.fond.contour, (r, v) => { r.fond.contour = v; }, 0],
		durete: [(r) => r.fond.durete, (r, v) => { r.fond.durete = v; }, 0],
		tslT: [(r) => r.tsl[teinteCourante][0], (r, v) => { r.tsl[teinteCourante][0] = v; }, 0],
		tslS: [(r) => r.tsl[teinteCourante][1], (r, v) => { r.tsl[teinteCourante][1] = v; }, 0],
		tslL: [(r) => r.tsl[teinteCourante][2], (r, v) => { r.tsl[teinteCourante][2] = v; }, 0],
		filtreForce: [(r) => (r.filtre || { force: 100 }).force, (r, v) => { r.filtre = Object.assign({ nom: 'aucun' }, r.filtre, { force: v }); }, 100],
		// le masque en cours : son intensite, son contour progressif (radial), ses reglages
		m_quantite: [(r) => { const m = masqueDe(r); return m ? m.quantite : 100; }, (r, v) => { const m = masqueDe(r); if (m) { m.quantite = v; } }, 100],
		m_plume: [(r) => { const m = masqueDe(r); return m && m.geo ? m.geo.plume : 50; }, (r, v) => { const m = masqueDe(r); if (m && m.geo) { m.geo.plume = v; } }, 50],
	};
	LOCAUX.forEach((k) => {
		ACCES['m_' + k] = [(r) => { const m = masqueDe(r); return m ? (m.r[k] || 0) : 0; }, (r, v) => { const m = masqueDe(r); if (m) { m.r[k] = v; } }, 0];
	});
	function masqueDe(r) { return etat.masqueId ? (r.masques || []).find((m) => m.id === etat.masqueId) || null : null; }
	function masqueChoisi() { return active ? masqueDe(active.reglages) : null; }
	const lire = (cle) => (ACCES[cle] ? ACCES[cle][0](active.reglages) : active.reglages[cle]);
	const ecrire = (cle, v) => { if (ACCES[cle]) { ACCES[cle][1](active.reglages, v); } else { active.reglages[cle] = v; } };
	const defaut = (cle) => (ACCES[cle] ? ACCES[cle][2] : DEFAUT[cle]);

	const curseurs = Array.from(document.querySelectorAll('.rp-curseur[data-reglage]'));
	function afficherValeur(bloc, v) {
		const piste = bloc.querySelector('input[type="range"]'), champ = bloc.querySelector('.rp-curseur__valeur');
		const pas = parseFloat(piste.step) || 1;
		piste.value = v;
		champ.value = pas < 1 ? Number(v).toFixed(2) : Math.round(v);
		bloc.classList.toggle('rp-curseur--modifie', Math.abs(v - defaut(bloc.dataset.reglage)) > 1e-9);
	}

	curseurs.forEach((bloc) => {
		const cle = bloc.dataset.reglage;
		const piste = bloc.querySelector('input[type="range"]'), champ = bloc.querySelector('.rp-curseur__valeur');
		const borne = (v) => Math.min(parseFloat(piste.max), Math.max(parseFloat(piste.min), v));
		const changer = (v, garder) => {
			if (!active) { return; }
			const avant = lire(cle);
			ecrire(cle, v);
			afficherValeur(bloc, v);
			demanderRendu();
			if ((cle === 'lissage' || cle.indexOf('peau') === 0) && v !== 0 && avant === 0) { assurerDetourage(active); }
			if (cle.indexOf('peau') === 0 && active.reglages.teint) { active.reglages.teint = null; majPortrait(); }
			if (cle.indexOf('m_') === 0) { majListeMasques(); if (cle === 'm_plume') { majPoignees(); } }
			if (garder) { memoriser(); }
		};
		piste.addEventListener('input', () => changer(parseFloat(piste.value), false));
		piste.addEventListener('change', () => changer(parseFloat(piste.value), true));
		champ.addEventListener('change', () => {
			const v = parseFloat(String(champ.value).replace(',', '.'));
			changer(Number.isFinite(v) ? borne(v) : defaut(cle), true);
		});
		champ.addEventListener('keydown', (e) => { if (e.key === 'Enter') { champ.blur(); } });
		bloc.querySelector('.rp-curseur__nom').addEventListener('dblclick', () => changer(defaut(cle), true));
		piste.addEventListener('dblclick', () => changer(defaut(cle), true));
	});

	// Qualite d'export : meme presentation, hors reglages de la photo
	(function () {
		const champ = el.qualiteBloc.querySelector('.rp-curseur__valeur');
		el.qualite.addEventListener('input', () => { champ.value = el.qualite.value; });
		champ.addEventListener('change', () => {
			const v = Math.min(100, Math.max(50, parseInt(champ.value, 10) || 95));
			champ.value = v; el.qualite.value = v;
			planifierSauvegarde(null);
		});
	})();

	/* Remet toute l'interface aux valeurs de la photo active. */
	function majInterface() {
		majBoutonStudio();
		const actif = !!active;
		el.reglages.disabled = !actif;
		[el.avantApres, el.recadrer, el.pinceau, el.masques, el.grilleBouton, el.zoomAjuster, el.zoom100].forEach((b) => { b.disabled = !actif; });
		if (!actif) { ouvrirMenuGrille(false); }
		el.vide.hidden = actif;
		majBoutonsPile();
		majMasques();
		if (!actif) { el.infos.textContent = 'Aucune photo'; return; }
		const r = active.reglages;
		curseurs.forEach((bloc) => afficherValeur(bloc, lire(bloc.dataset.reglage)));
		document.querySelectorAll('input[name="rpFond"]').forEach((i) => { i.checked = i.value === r.fond.mode; });
		majSousFond();
		majFiltres();
		majFondsStudio();
		majPortrait();
		$('rpStudioA').value = r.fond.studioA; $('rpStudioB').value = r.fond.studioB;
		$('rpFondCouleur').value = r.fond.couleur;
		$('rpFondEchCouleur').style.setProperty('--rp-fond-couleur', r.fond.couleur);
		document.querySelectorAll('.rp-pastille[data-couleur]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.couleur === r.fond.couleur)));
		$('rpPourtour').checked = !!r.fond.pourtour;
		majCadrage();
		const info = imagesFond.get(r.fond.image);
		$('rpImageFondNom').textContent = info ? info.nom : 'Aucune image choisie';
		const t = moteur.tailleSortie(r);
		el.infos.textContent = active.largeur0 + ' × ' + active.hauteur0 + ' px' + (active.reduite ? ' (réduite à ' + active.image.width + ' × ' + active.image.height + ' pour la retouche)' : '') + ' · ' + active.nom;
		majExport(t);
	}

	function majSousFond() {
		const mode = active ? active.reglages.fond.mode : 'original';
		document.querySelectorAll('[data-pour-fond]').forEach((s) => { s.hidden = !s.dataset.pourFond.split(' ').includes(mode); });
	}

	/* Dimensions de l'export : celles du format personnalise quand elles sont
	   demandees exactes, sinon le cadre, ramene au grand cote choisi. Avec un
	   plafond (photo de profil) : le cadre, ramene a ce grand cote au plus. */
	function dimensionsExport(r, t, plafond) {
		t = t || moteur.tailleSortie(r);
		if (!plafond && r.ratio === 'perso' && r.perso && r.perso.exact) {
			const w = Math.round(r.perso.l), h = Math.round(r.perso.h);
			return { w, h, exact: true, agrandie: w > t.w * 1.01 };
		}
		const max = plafond || parseInt(el.taille.value, 10) || 0;
		const s = max && Math.max(t.w, t.h) > max ? max / Math.max(t.w, t.h) : 1;
		const w = Math.max(1, Math.round(t.w * s)), k = ratioPixels(r);
		// la hauteur suit la largeur et le format : un carre reste carre au pixel pres
		return { w, h: Math.max(1, k ? Math.round(w / k) : Math.round(t.h * s)), exact: false, agrandie: false };
	}

	function majExport(t) {
		if (!active) { return; }
		t = t || moteur.tailleSortie(active.reglages);
		const d = dimensionsExport(active.reglages, t);
		el.taille.disabled = d.exact;
		const transparent = active.reglages.fond.mode === 'transparent';
		if (transparent && el.format.value === 'image/jpeg') {
			el.format.value = 'image/png';
			toast('info', 'Le fond transparent ne passe pas en JPEG : le format d\'export devient PNG.');
		}
		el.qualiteBloc.hidden = el.format.value === 'image/png';
		const fmt = el.format.options[el.format.selectedIndex].textContent;
		el.dimensions.textContent = 'Export : ' + d.w + ' × ' + d.h + ' px, ' + fmt + (transparent ? ', fond transparent' : '') + (filigrane.actif ? ', avec filigrane' : '')
			+ (d.exact ? ' (dimensions du format personnalisé' + (d.agrandie ? ' ; le cadre n\'a que ' + t.w + ' × ' + t.h + ' px, la photo sera agrandie' : '') + ')' : '') + '.';
		el.exporterTout.hidden = photos.length < 2;
	}
	el.format.addEventListener('change', () => { majExport(); planifierSauvegarde(null); });
	el.taille.addEventListener('change', () => { majExport(); planifierSauvegarde(null); });
	el.qualite.addEventListener('change', () => planifierSauvegarde(null));
	el.nom.addEventListener('change', () => { if (active) { active.nomExport = el.nom.value.trim() || null; planifierSauvegarde(active); } });

	/* ======================================================================
	   Fond
	   ====================================================================== */
	document.querySelectorAll('input[name="rpFond"]').forEach((radio) => {
		radio.addEventListener('change', () => {
			if (!active || !radio.checked) { return; }
			changerFond(radio.value);
		});
	});

	function changerFond(mode) {
		active.reglages.fond.mode = mode;
		if (mode === 'image' && !imagesFond.get(active.reglages.fond.image)) {
			const dernier = Array.from(imagesFond.keys()).pop();
			if (dernier) { active.reglages.fond.image = dernier; chargerImageFond(active.reglages); }
			else { $('rpImageFond').click(); }
		}
		majInterface();
		memoriser();
		demanderRendu();
		if (mode !== 'original') { assurerDetourage(active); }
	}

	document.querySelectorAll('.rp-pastille[data-couleur]').forEach((b) => {
		b.addEventListener('click', () => {
			if (!active) { return; }
			active.reglages.fond.couleur = b.dataset.couleur;
			if (active.reglages.fond.mode !== 'couleur') { changerFond('couleur'); return; }
			majInterface(); memoriser(); demanderRendu();
		});
	});
	[['rpFondCouleur', 'couleur'], ['rpStudioA', 'studioA'], ['rpStudioB', 'studioB']].forEach(([id, cle]) => {
		const champ = $(id);
		champ.addEventListener('input', () => {
			if (!active) { return; }
			active.reglages.fond[cle] = champ.value;
			if (cle === 'couleur') { $('rpFondEchCouleur').style.setProperty('--rp-fond-couleur', champ.value); }
			else { majFondsStudio(); }
			demanderRendu();
		});
		champ.addEventListener('change', () => {
			if (!active) { return; }
			active.reglages.fond[cle] = champ.value;
			if (cle === 'couleur' && active.reglages.fond.mode !== 'couleur') { changerFond('couleur'); return; }
			majInterface(); memoriser();
		});
	});
	$('rpPourtour').addEventListener('change', (e) => {
		if (!active) { return; }
		active.reglages.fond.pourtour = e.target.checked; memoriser(); demanderRendu();
	});

	$('rpImageFondChoisir').addEventListener('click', () => $('rpImageFond').click());
	$('rpImageFond').addEventListener('change', async (e) => {
		const f = e.target.files && e.target.files[0];
		e.target.value = '';
		if (!f || !active) { return; }
		if (!TYPES.includes(f.type)) { toast('warning', "L'image de fond doit être un JPEG, un PNG ou un WebP."); return; }
		try {
			let image = await createImageBitmap(f);
			if (Math.max(image.width, image.height) > TAILLE_MAX) {
				const s = TAILLE_MAX / Math.max(image.width, image.height);
				const p = await createImageBitmap(f, { resizeWidth: Math.round(image.width * s), resizeHeight: Math.round(image.height * s), resizeQuality: 'high' });
				image.close(); image = p;
			}
			const id = 'f' + Date.now();
			imagesFond.set(id, { image, nom: f.name, url: URL.createObjectURL(f) });
			sauverFond(id, f);
			$('rpFondEchImage').style.backgroundImage = 'url("' + imagesFond.get(id).url + '")';
			active.reglages.fond.image = id;
			chargerImageFond(active.reglages);
			if (active.reglages.fond.mode !== 'image') { changerFond('image'); return; }
			majInterface(); memoriser(); demanderRendu();
		} catch (err) {
			console.error(err);
			toast('error', "L'image de fond n'a pas pu être ouverte.");
		}
	});

	function chargerImageFond(r) {
		const id = r.fond.image;
		if (id === imageFondChargee) { return; }
		const info = imagesFond.get(id);
		moteur.imageFond(info ? info.image : null);
		imageFondChargee = info ? id : null;
	}

	/* ======================================================================
	   Couleur : balance des blancs, teintes
	   ====================================================================== */
	const lin = (v) => (v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4));
	const borne = (v, a, b) => Math.min(b, Math.max(a, v));

	/* Temperature et teinte qui amenent la couleur c (lineaire) aux rapports de
	   la cible (gris neutre par defaut). Voir RetoucheMoteur.gains. */
	function balanceVers(c, cible) {
		const t = cible || [1, 1, 1];
		const lbr = Math.log2((t[2] / c[2]) / (t[0] / c[0]));
		const lgr = Math.log2((t[1] / c[1]) / (t[0] / c[0]));
		const kt = -lbr / 1.5, kn = -lgr / 1.5;
		return { temperature: Math.round(borne(kt / 1.5 * 100, -100, 100)), teinte: Math.round(borne(kn / 1.5 * 100, -100, 100)) };
	}

	function moyenneImage(t, x0, y0, x1, y1) {
		const s = [0, 0, 0]; let n = 0;
		for (let y = Math.max(0, y0); y < Math.min(t.h, y1); y++) {
			for (let x = Math.max(0, x0); x < Math.min(t.w, x1); x++) {
				const i = y * t.w + x; s[0] += lin(t.r[i]); s[1] += lin(t.g[i]); s[2] += lin(t.b[i]); n++;
			}
		}
		return n ? s.map((v) => Math.max(1e-4, v / n)) : null;
	}

	$('rpBalanceAuto').addEventListener('click', () => {
		if (!active || !active.t) { return; }
		const m = active.mesures;
		const c = m && m.peau ? m.peau : moyenneImage(active.t, 0, 0, active.t.w, active.t.h);
		const b = balanceVers(c, m && m.peau ? PEAU_CIBLE : null);
		active.reglages.temperature = b.temperature; active.reglages.teinte = b.teinte;
		majInterface(); memoriser(); demanderRendu();
		toast('success', m && m.peau ? 'Balance des blancs réglée sur la peau du visage.' : 'Balance des blancs réglée sur la moyenne de la photo.');
	});
	$('rpPipette').addEventListener('click', () => basculerPipette(!etat.pipette));
	function basculerPipette(oui) {
		etat.pipette = !!oui && !!active;
		$('rpPipette').setAttribute('aria-pressed', String(etat.pipette));
		majCurseurScene();
		if (etat.pipette) { toast('info', 'Cliquez sur un blanc ou un gris de la photo (un mur, une chemise blanche).'); }
	}

	document.querySelectorAll('input[name="rpTeinte"]').forEach((radio) => {
		radio.addEventListener('change', () => {
			if (!radio.checked) { return; }
			teinteCourante = radio.value;
			$('rpTeinteNom').textContent = TEINTES_NOMS[teinteCourante];
			if (active) { ['tslT', 'tslS', 'tslL'].forEach((k) => afficherValeur(document.querySelector('[data-reglage="' + k + '"]'), lire(k))); }
		});
	});

	/* ======================================================================
	   Filtres facon Instagram (src/js/retouche-photo.filtres.js)
	   ====================================================================== */
	const FILTRES = window.RetoucheFiltres ? window.RetoucheFiltres.liste : [];
	const voletFiltres = $('rpVoletFiltres'), grilleFiltres = $('rpFiltres');
	const canevasFiltres = new Map(); // nom du filtre -> sa vignette
	FILTRES.forEach((f) => {
		const etiquette = document.createElement('label');
		etiquette.className = 'rp-filtre';
		etiquette.title = f.libelle;
		const radio = document.createElement('input');
		radio.type = 'radio'; radio.name = 'rpFiltre'; radio.value = f.nom;
		radio.setAttribute('aria-label', f.libelle);
		const cv = document.createElement('canvas');
		cv.className = 'rp-filtre__image'; cv.width = 96; cv.height = 96;
		cv.setAttribute('aria-hidden', 'true');
		const nom = document.createElement('span');
		nom.className = 'rp-filtre__nom'; nom.textContent = f.libelle;
		nom.setAttribute('aria-hidden', 'true');
		etiquette.append(radio, cv, nom);
		grilleFiltres.appendChild(etiquette);
		canevasFiltres.set(f.nom, cv);
		radio.addEventListener('change', () => { if (radio.checked) { choisirFiltre(f.nom); } });
	});
	if (!FILTRES.length) { voletFiltres.hidden = true; }

	function choisirFiltre(nom) {
		if (!active) { return; }
		const avant = active.reglages.filtre || {};
		// un autre filtre arrive a pleine intensite, comme sur Instagram
		active.reglages.filtre = { nom, force: avant.nom === nom ? avant.force : 100 };
		majInterface(); memoriser(); demanderRendu();
	}
	function majFiltres() {
		const f = (active && active.reglages.filtre) || { nom: 'aucun', force: 100 };
		grilleFiltres.querySelectorAll('input[name="rpFiltre"]').forEach((i) => { i.checked = i.value === f.nom; });
		$('rpFiltreForceBloc').hidden = f.nom === 'aucun';
	}

	/* Les vignettes montrent la photo telle qu'elle est reglee, avec chaque
	   filtre. Refaites un instant apres le dernier reglage, volet ouvert
	   seulement ; le choix du filtre ou son intensite ne les changent pas. */
	let minuterieFiltres = 0, cleFiltres = '';
	function planifierFiltres() {
		clearTimeout(minuterieFiltres);
		minuterieFiltres = setTimeout(dessinerFiltres, 650);
	}
	function dessinerFiltres() {
		if (!active || !voletFiltres.open || !FILTRES.length) { return; }
		const base = copie(active.reglages);
		delete base.filtre;
		const cle = active.id + '|' + moteur.masqueOk + '|' + JSON.stringify(base);
		if (cle === cleFiltres) { return; }
		cleFiltres = cle;
		FILTRES.forEach((f) => {
			const r = Object.assign(copie(base), { filtre: { nom: f.nom, force: 100 } });
			const { px, w, h } = moteur.rendreReduit(r, 160, { grainEchelle: 0.6 });
			const cv = canevasFiltres.get(f.nom);
			if (cv.width !== w || cv.height !== h) { cv.width = w; cv.height = h; }
			cv.getContext('2d').putImageData(new ImageData(new Uint8ClampedArray(px.buffer), w, h), 0, 0);
		});
	}
	voletFiltres.addEventListener('toggle', () => { if (voletFiltres.open) { dessinerFiltres(); } });

	/* ======================================================================
	   Preregle Studio UAZ
	   ====================================================================== */
	/* Couleur moyenne (lineaire) de la peau du visage sur le rendu des reglages
	   r, image entiere : memes pixels que RetoucheMasque.mesurer (visage sans
	   les 15 % les plus sombres ni les 3 % les plus clairs). */
	function peauRendue(photo, r) {
		const s = photo.seg, t = photo.t;
		if (!s || !t) { return null; }
		const rr = copie(r);
		rr.cadre = { x: 0, y: 0, w: 1, h: 1 }; rr.rotation = 0; rr.miroir = false; rr.vignette = 0; rr.fond.mode = 'original'; rr.masques = [];
		const { px, w, h } = moteur.rendreReduit(rr, 640, {});
		const vals = [];
		for (let y = 0; y < h; y++) {
			const ty = Math.min(t.h - 1, Math.floor((y + 0.5) / h * t.h));
			for (let x = 0; x < w; x++) {
				const i = ty * t.w + Math.min(t.w - 1, Math.floor((x + 0.5) / w * t.w));
				if (s.visage[i] > 0.6 && s.alpha[i] > 0.8) {
					const k = (y * w + x) * 4;
					vals.push([px[k] / 255, px[k + 1] / 255, px[k + 2] / 255]);
				}
			}
		}
		if (vals.length < 60) { return null; }
		const yp = (v) => 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2];
		vals.sort((a, b) => yp(a) - yp(b));
		const garde = vals.slice(Math.floor(vals.length * 0.15), Math.floor(vals.length * 0.97));
		const m = [0, 0, 0];
		garde.forEach((v) => { m[0] += lin(v[0]); m[1] += lin(v[1]); m[2] += lin(v[2]); });
		return m.map((x) => Math.max(1e-4, x / garde.length));
	}

	async function appliquerStudio(photo, options) {
		const ok = await assurerDetourage(photo);
		const avant = photo.reglages;
		const r = reglagesDefaut();
		r.rotation = avant.rotation; r.miroir = avant.miroir;
		r.fond.image = avant.fond.image;
		r.masques = copie(avant.masques || []);   // le preregle garde les masques de la photo
		r.eclairage = avant.eclairage || null;
		const m = photo.mesures;
		let peau = m && m.peau;
		let gainsPeau = null;
		if (peau) {
			const b = balanceVers(peau, PEAU_CIBLE);
			r.temperature = Math.round(b.temperature * PART_GLOBALE); r.teinte = Math.round(b.teinte * PART_GLOBALE);
		} else if (photo.t) {
			const c = moyenneImage(photo.t, 0, 0, photo.t.w, photo.t.h);
			const b = balanceVers(c, null);
			r.temperature = Math.round(b.temperature * 0.7); r.teinte = Math.round(b.teinte * 0.7);
		}
		if (peau) {
			gainsPeau = M.gains(r.temperature, r.teinte);
			const y = 0.2126 * peau[0] * gainsPeau[0] + 0.7152 * peau[1] * gainsPeau[1] + 0.0722 * peau[2] * gainsPeau[2];
			r.exposition = Math.round(borne(Math.log2(PEAU_LUMINANCE / Math.max(y, 1e-3)) * 0.8, -1, 1.5) * 20) / 20;
		}
		Object.assign(r, STUDIO_TONS);
		if (!ok || !photo.seg) { r.lissage = 0; }
		/* La peau est mesuree sur le rendu reel (la courbe des tons et la
		   compression des hautes lumieres deplacent sa couleur) et amenee a la
		   reference par les reglages « Portrait », en quelques passes.
		   L'exposition vise une luminance entre celle de la peau de la photo
		   et celle du modele (moyenne geometrique 40/60) : une peau foncee
		   reste foncee, mais lumineuse. */
		if (peau && photo === active && photo.seg) {
			r.exposition = 0;
			let yCible = null;
			for (let k = 0; k < 4; k++) {
				const rendu = peauRendue(photo, r);
				if (!rendu) { break; }
				const y = 0.2126 * rendu[0] + 0.7152 * rendu[1] + 0.0722 * rendu[2];
				if (yCible == null) { yCible = Math.pow(y, 0.4) * Math.pow(PEAU_LUMINANCE, 0.6); }
				const d = balanceVers(rendu, PEAU_CIBLE);
				const dEv = Math.log2(yCible / Math.max(y, 1e-3));
				r.peauTemperature = Math.round(borne(r.peauTemperature + d.temperature, -100, 100));
				r.peauTeinte = Math.round(borne(r.peauTeinte + d.teinte, -100, 100));
				r.exposition = Math.round(borne(r.exposition + dEv, -1, 1.5) * 20) / 20;
				if (Math.abs(d.temperature) <= 1 && Math.abs(d.teinte) <= 1 && Math.abs(dEv) < 0.04) { break; }
			}
		}
		const personne = ok && photo.seg && photo.seg.personne;
		if (options.fond && personne) { r.fond.mode = 'studio'; }
		r.perso = copie(persoDe(avant));
		r.filtre = copie(avant.filtre || r.filtre); // le filtre choisi reste, Studio ou pas
		r.cadre = copie(avant.cadre); r.ratio = avant.ratio;
		if (options.cadre && personne) {
			// le format deja choisi est garde ; a defaut, celui de la photo
			r.ratio = avant.ratio && avant.ratio !== 'libre' ? avant.ratio : 'origine';
			const c = cadrePortrait(photo, r);
			if (c) { r.cadre = c; } else { r.ratio = avant.ratio; }
		}
		photo.reglages = r;
		memoriser(photo);
		return { ok, personne: !!personne, peau: !!peau };
	}

	$('rpStudio').addEventListener('click', async () => {
		if (!active || etat.occupe) { return; }
		etat.occupe = true;
		const p = active;
		try {
			const res = await appliquerStudio(p, { fond: $('rpStudioFond').checked, cadre: $('rpStudioCadre').checked });
			if (active === p) {
				chargerImageFond(p.reglages);
				majInterface(); demanderRendu();
			}
			if (!res.ok) { toast('warning', 'Studio UAZ appliqué sans le détourage : lumière et couleur seulement.'); }
			else if (!res.peau) { toast('info', "Studio UAZ appliqué. Aucun visage net n'a été trouvé : la balance des blancs suit la moyenne de la photo."); }
			else { toast('success', 'Studio UAZ appliqué : chaque réglage reste modifiable, et Ctrl+Z revient en arrière.'); }
		} finally { etat.occupe = false; }
	});

	/* Cadrage sur la personne, dans le format de r (celui de la photo si le
	   cadre est libre). Regle du portrait de reference (la photo modele) : du
	   haut des cheveux au menton, la tete fait 34 % de la hauteur, le haut des
	   cheveux a 10 % du bord ; photo d identite : 74 % de la hauteur, a 9 % du
	   bord (normes des photos d identite : 32 a 36 mm sur 45). Visage centre ; si le cadre
	   deborde de l'image, il retrecit en gardant son format. */
	function cadrePortrait(photo, r) {
		const s = photo.seg;
		if (!s || !s.personne) { return null; }
		const W0 = photo.image.width, H0 = photo.image.height;
		const tourne = r.rotation % 2 !== 0;
		const W = tourne ? H0 : W0, H = tourne ? W0 : H0;
		const versOriente = (x, y) => {
			const m = M.matriceOrientation(r.rotation, r.miroir); // [a, c, b, d]
			const u = x / W0 - 0.5, v = y / H0 - 0.5;
			return { x: (m[0] * u + m[1] * v + 0.5) * W, y: (m[2] * u + m[3] * v + 0.5) * H };
		};
		const boiteOrientee = (b) => {
			const pts = [versOriente(b.x0, b.y0), versOriente(b.x1, b.y0), versOriente(b.x0, b.y1), versOriente(b.x1, b.y1)];
			return { x0: Math.min(...pts.map((p) => p.x)), x1: Math.max(...pts.map((p) => p.x)), y0: Math.min(...pts.map((p) => p.y)), y1: Math.max(...pts.map((p) => p.y)) };
		};
		const pers = boiteOrientee(s.personne);
		const tete = s.teteBoite ? boiteOrientee(s.teteBoite) : pers;
		const visage = s.visageBoite ? boiteOrientee(s.visageBoite) : null;
		const ratio = ratioPixels(r) || W / H;
		const identite = r.ratio === '35:45' || r.ratio === '45:35';
		// haut de la tete : le detourage (MODNet, precis), sauf s'il touche le bord ;
		// menton : la peau du visage deborde un peu sur le cou, il est a 86 % de sa hauteur
		const crane = pers.y0 > 2 ? pers.y0 : tete.y0;
		const menton = visage ? visage.y0 + 0.86 * (visage.y1 - visage.y0) : 0;
		let w, h;
		if (visage && menton > crane) {
			h = (menton - crane) / (identite ? 0.74 : 0.34);
			w = h * ratio;
		} else { // sans visage net : la personne sur la moitie de la largeur
			w = Math.max((pers.x1 - pers.x0) / 0.5, (tete.x1 - tete.x0) / 0.36);
			h = w / ratio;
		}
		const s2 = Math.min(1, W / w, H / h);
		w *= s2; h *= s2;
		const cx = visage ? (visage.x0 + visage.x1) / 2 : (tete.x0 + tete.x1) / 2;
		const x = borne(cx - w / 2, 0, W - w);
		const y = borne(crane - (identite ? 0.09 : 0.1) * h, 0, H - h);
		return { x: x / W, y: y / H, w: w / W, h: h / H };
	}

	/* --- Mes preregles (dans ce navigateur) ---------------------------------- */
	function lirePerso() {
		try { const v = JSON.parse(localStorage.getItem(CLE_PERSO) || '[]'); return Array.isArray(v) ? v : []; } catch (e) { return []; }
	}
	function ecrirePerso(liste) {
		try { localStorage.setItem(CLE_PERSO, JSON.stringify(liste)); return true; } catch (e) { return false; }
	}
	function majPerso() {
		const liste = lirePerso();
		el.persoListe.textContent = '';
		const vide = document.createElement('option'); vide.value = ''; vide.textContent = liste.length ? 'Choisir…' : 'Aucun pour l\'instant';
		el.persoListe.appendChild(vide);
		liste.forEach((p, i) => { const o = document.createElement('option'); o.value = String(i); o.textContent = p.nom; el.persoListe.appendChild(o); });
		$('rpPersoAppliquer').disabled = $('rpPersoSupprimer').disabled = true;
	}
	el.persoListe.addEventListener('change', () => {
		const choisi = el.persoListe.value !== '';
		$('rpPersoAppliquer').disabled = $('rpPersoSupprimer').disabled = !choisi;
	});
	function sansGeometrie(r) {
		const c = copie(r);
		delete c.cadre; delete c.rotation; delete c.miroir; delete c.ratio; delete c.perso;
		return c;
	}
	function fusionner(base, ajout) {
		const r = copie(base);
		Object.keys(ajout).forEach((k) => {
			if (k === 'fond' || k === 'tsl') { r[k] = Object.assign({}, r[k], copie(ajout[k])); } else { r[k] = ajout[k]; }
		});
		return r;
	}
	$('rpPersoEnregistrer').addEventListener('click', () => {
		if (!active) { return; }
		const nom = el.persoNom.value.trim();
		if (!nom) { el.persoNom.focus(); toast('warning', 'Donnez un nom au préréglage.'); return; }
		const liste = lirePerso().filter((p) => p.nom !== nom);
		const r = sansGeometrie(active.reglages);
		r.fond.image = null;
		delete r.masques; delete r.eclairage;   // propres a une photo : le preregle ne les emporte pas
		liste.push({ nom, reglages: r });
		if (!ecrirePerso(liste)) { toast('error', "Le navigateur refuse d'enregistrer le préréglage (stockage plein ou désactivé)."); return; }
		el.persoNom.value = '';
		majPerso();
		toast('success', 'Préréglage « ' + nom + ' » enregistré dans ce navigateur.');
	});
	$('rpPersoAppliquer').addEventListener('click', () => {
		const p = lirePerso()[parseInt(el.persoListe.value, 10)];
		if (!p || !active) { return; }
		const garde = { cadre: active.reglages.cadre, rotation: active.reglages.rotation, miroir: active.reglages.miroir, ratio: active.reglages.ratio, perso: active.reglages.perso, image: active.reglages.fond.image, masques: active.reglages.masques || [] };
		active.reglages = fusionner(reglagesDefaut(), p.reglages);
		Object.assign(active.reglages, { cadre: garde.cadre, rotation: garde.rotation, miroir: garde.miroir, ratio: garde.ratio, perso: garde.perso || persoDe(active.reglages), masques: garde.masques });
		if (active.reglages.fond.mode === 'image') { active.reglages.fond.image = garde.image; }
		majInterface(); memoriser(); demanderRendu();
		if (besoinDetourage(active.reglages)) { assurerDetourage(active); }
	});
	$('rpPersoSupprimer').addEventListener('click', async () => {
		const i = parseInt(el.persoListe.value, 10);
		const liste = lirePerso();
		if (!liste[i]) { return; }
		const ok = window.Toast && window.Toast.confirm
			? await window.Toast.confirm('Supprimer le préréglage « ' + liste[i].nom + ' » ?', { confirmText: 'Supprimer', danger: true })
			: true;
		if (!ok) { return; }
		liste.splice(i, 1); ecrirePerso(liste); majPerso();
	});

	$('rpToutRemettre').addEventListener('click', () => {
		if (!active) { return; }
		active.reglages = reglagesDefaut();
		if (etat.mode === 'masque') { basculerMasque(false); }
		cuireMasques(active);
		chargerImageFond(active.reglages);
		majInterface(); memoriser(); demanderRendu();
	});

	/* ======================================================================
	   Rendu et geometrie de la vue
	   ====================================================================== */
	let raf = 0;
	function demanderRendu() { if (!raf) { raf = requestAnimationFrame(rendre); } }

	let couleurScene = null;
	function lireCouleurScene() {
		const v = getComputedStyle(scene).backgroundColor.match(/\d+(\.\d+)?/g) || [30, 30, 33];
		couleurScene = [v[0] / 255, v[1] / 255, v[2] / 255];
	}
	new MutationObserver(() => { couleurScene = null; demanderRendu(); }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'class'] });

	/* Reglages tels qu'affiches : en recadrage, l'image entiere. */
	function reglagesVus() {
		if (etat.mode !== 'recadrer') { return active.reglages; }
		return Object.assign({}, active.reglages, { cadre: { x: 0, y: 0, w: 1, h: 1 } });
	}

	function geometrie() {
		const dpr = window.devicePixelRatio || 1;
		const cw = Math.max(1, Math.round(scene.clientWidth * dpr)), ch = Math.max(1, Math.round(scene.clientHeight * dpr));
		if (canvas.width !== cw || canvas.height !== ch) { canvas.width = cw; canvas.height = ch; }
		const r = reglagesVus();
		const t = moteur.tailleSortie(r);
		const marge = etat.mode === 'recadrer' ? 0.86 : 0.94;
		// en mode Masques, la photo ajustee se pose au-dessus de la barre du masque
		const bas = etat.mode === 'masque' && !el.outilsMasque.hidden ? (el.outilsMasque.offsetHeight + 18) * dpr : 0;
		const chUtile = Math.max(1, ch - bas);
		const ajuste = Math.min(cw * marge / t.w, chUtile * marge / t.h);
		const z = etat.zoom == null ? ajuste : etat.zoom;
		const l = t.w * z, h = t.h * z;
		let x, y;
		if (etat.zoom == null) { x = (cw - l) / 2; y = (chUtile - h) / 2; }
		else {
			x = cw / 2 - etat.centre.x * l; y = ch / 2 - etat.centre.y * h;
			x = l <= cw ? (cw - l) / 2 : borne(x, cw - l, 0);
			y = h <= ch ? (ch - h) / 2 : borne(y, ch - h, 0);
			etat.centre = { x: (cw / 2 - x) / l, y: (ch / 2 - y) / h };
		}
		return { dpr, cw, ch, x, y, l, h, z, t, r, ajuste };
	}

	function rendre() {
		raf = 0;
		if (!active) { return; }
		if (!couleurScene) { lireCouleurScene(); }
		const g = geometrie();
		etat.geo = g;
		moteur.dessiner(g.r, { x: g.x, y: g.y, l: g.l, h: g.h }, {
			avant: etat.avant != null && etat.mode !== 'recadrer' ? etat.avant : null,
			voirMasque: etat.mode === 'pinceau',
			masqueVoir: etat.mode === 'masque' && etat.voirZone && (etat.survol || etat.flashZone) ? indexMasqueChoisi() : -1,
			fondVue: couleurScene,
			grainEchelle: Math.min(1, Math.sqrt(g.z)), // le grain vu en reduction ne doit pas paraitre plus fort qu a l export
		});
		placerSurcouches(g);
		el.zoomVal.textContent = Math.round(g.z * 100) + ' %';
		el.zoomAjuster.setAttribute('aria-pressed', String(etat.zoom == null));
		el.zoom100.setAttribute('aria-pressed', String(etat.zoom === 1));
		planifierHisto();
		planifierFiltres();
		majExport(g.t.w && etat.mode !== 'recadrer' ? g.t : null);
	}

	function placerSurcouches(g) {
		const d = g.dpr;
		majInfoCadrage();
		if (etat.mode === 'recadrer') {
			const c = active.reglages.cadre;
			Object.assign(el.cadre.style, {
				left: (g.x + c.x * g.l) / d + 'px', top: (g.y + c.y * g.h) / d + 'px',
				width: c.w * g.l / d + 'px', height: c.h * g.h / d + 'px',
			});
		}
		el.cadre.hidden = etat.mode !== 'recadrer';
		majPoignees();
		majApercuFiligrane();
		majGrille(g);
		const coupure = etat.avant != null && etat.mode !== 'recadrer';
		el.coupure.hidden = !coupure;
		if (coupure) {
			Object.assign(el.coupure.style, {
				left: (g.x + etat.avant * g.l) / d + 'px',
				top: Math.max(0, g.y / d) + 'px',
				height: Math.min(g.ch, g.h) / d + 'px',
			});
		}
	}

	/* --- Grille de composition --------------------------------------------- */
	/* Comme les incrustations du recadrage de Lightroom : tiers, quadrillage,
	   nombre d'or, spirale d'or, diagonales, triangles d'or, centre. Sur la
	   photo hors des outils (pinceau, masques), et dans le cadre pendant le
	   recadrage (les tiers si aucune n'est choisie). Touche O : la suivante ;
	   Maj+O : pivoter la spirale ou retourner les triangles. Elle n'entre pas
	   dans l'export ; le choix est garde avec les outils. */
	const PHI = (1 + Math.sqrt(5)) / 2;
	const ORDRE_GRILLES = ['aucune', 'tiers', 'quadrillage', 'or', 'spirale', 'diagonales', 'triangles', 'centre'];
	const grille = { type: 'aucune', orientation: 0, densite: 8 };
	const gr = {
		svg: $('rpGrille'), cadre: $('rpGrilleCadre'), menu: $('rpGrilleMenu'), nom: $('rpGrilleNom'), aide: $('rpGrilleAide'),
		pivoter: $('rpGrillePivoter'), densite: $('rpGrilleDensite'), densiteBloc: $('rpGrilleDensiteBloc'),
	};
	const radiosGrille = Array.from(gr.menu.querySelectorAll('input[name="rpGrille"]'));
	const nomGrille = (t) => { const i = radiosGrille.find((r) => r.value === t); return i ? i.parentNode.querySelector('.rp-grille-choix__nom').textContent : t; };

	/* La spirale d'or dans un rectangle d'or (PHI x 1) : un quart de cercle par
	   carre, de carre en carre vers le centre ; et les cotes des carres. */
	const SPIRALE = (function () {
		let x = 0, y = 0, w = PHI, h = 1, d = 'M0 1', carres = '';
		const f = (v) => +v.toFixed(5);
		const arc = (s, ax, ay) => 'A' + f(s) + ' ' + f(s) + ' 0 0 1 ' + f(ax) + ' ' + f(ay);
		for (let k = 0; k < 10; k++) {
			const s = Math.min(w, h);
			switch (k % 4) {
				case 0: d += arc(s, x + s, y); carres += 'M' + f(x + s) + ' ' + f(y) + 'V' + f(y + h); x += s; w -= s; break;
				case 1: d += arc(s, x + s, y + s); carres += 'M' + f(x) + ' ' + f(y + s) + 'H' + f(x + w); y += s; h -= s; break;
				case 2: d += arc(s, x + w - s, y + s); carres += 'M' + f(x + w - s) + ' ' + f(y) + 'V' + f(y + h); w -= s; break;
				default: d += arc(s, x, y + h - s); carres += 'M' + f(x) + ' ' + f(y + h - s) + 'H' + f(x + w); h -= s;
			}
		}
		return { d, carres };
	})();

	/* Les traits d'une grille pour un cadre de w x h (pixels a l'ecran). */
	function traitsGrille(type, w, h, o, densite) {
		const r = (v) => Math.round(v * 10) / 10;
		const L = (x1, y1, x2, y2) => 'M' + r(x1) + ' ' + r(y1) + 'L' + r(x2) + ' ' + r(y2);
		const t = [];
		if (type === 'tiers' || type === 'or') {
			const a = type === 'tiers' ? 1 / 3 : 1 - 1 / PHI;
			[a, 1 - a].forEach((k) => t.push(L(w * k, 0, w * k, h), L(0, h * k, w, h * k)));
		} else if (type === 'quadrillage') {
			// des cases carrees, une ligne passant par le centre
			const pas = Math.max(w, h) / densite;
			for (let x = (w / 2) % pas; x < w; x += pas) { if (x > 0.5) { t.push(L(x, 0, x, h)); } }
			for (let y = (h / 2) % pas; y < h; y += pas) { if (y > 0.5) { t.push(L(0, y, w, y)); } }
		} else if (type === 'diagonales') {
			const m = Math.min(w, h);
			t.push(L(0, 0, m, m), L(w, 0, w - m, m), L(0, h, m, h - m), L(w, h, w - m, h - m));
		} else if (type === 'triangles') {
			// une diagonale, et la perpendiculaire tiree des deux autres coins
			const A = o & 1 ? [0, 0] : [0, h], B = o & 1 ? [w, h] : [w, 0];
			const autres = o & 1 ? [[w, 0], [0, h]] : [[0, 0], [w, h]];
			const dx = B[0] - A[0], dy = B[1] - A[1], n2 = dx * dx + dy * dy || 1;
			t.push(L(A[0], A[1], B[0], B[1]));
			autres.forEach(([x, y]) => { const k = ((x - A[0]) * dx + (y - A[1]) * dy) / n2; t.push(L(x, y, A[0] + k * dx, A[1] + k * dy)); });
		} else if (type === 'centre') {
			t.push(L(w / 2, 0, w / 2, h), L(0, h / 2, w, h / 2));
		}
		return t.join('');
	}

	const traceGrille = (d, fin) => '<path class="rp-grille__halo" d="' + d + '"/><path class="rp-grille__trait' + (fin ? ' rp-grille__trait--fin' : '') + '" d="' + d + '"/>';
	function dessinerGrille(svg, w, h, type, o, densite) {
		const sig = [type, Math.round(w * 2), Math.round(h * 2), o, densite].join('|');
		if (svg.dataset.sig === sig) { return; }
		svg.dataset.sig = sig;
		svg.setAttribute('viewBox', '0 0 ' + Math.max(1, w) + ' ' + Math.max(1, h));
		if (type === 'spirale') {
			// le rectangle d'or etire au cadre, couche s'il est en hauteur, retourne selon l'orientation
			const [a, b, c, d] = w >= h ? [w / PHI, 0, 0, h] : [0, h / PHI, w, 0];
			const sx = o & 1 ? -1 : 1, sy = o & 2 ? -1 : 1;
			const m = [sx * a, sy * b, sx * c, sy * d, o & 1 ? w : 0, o & 2 ? h : 0].map((v) => +v.toFixed(4));
			svg.innerHTML = '<g transform="matrix(' + m.join(' ') + ')">' + traceGrille(SPIRALE.carres, true) + traceGrille(SPIRALE.d) + '</g>';
		} else {
			svg.innerHTML = traceGrille(traitsGrille(type, w, h, o, densite));
		}
	}

	function majGrille(g) {
		const d = g.dpr, t = grille.type;
		const montrer = !!active && t !== 'aucune' && !etat.mode;
		gr.svg.style.display = montrer ? '' : 'none';
		if (montrer) {
			const w = g.l / d, h = g.h / d;
			Object.assign(gr.svg.style, { left: g.x / d + 'px', top: g.y / d + 'px', width: w + 'px', height: h + 'px' });
			dessinerGrille(gr.svg, w, h, t, grille.orientation, grille.densite);
		}
		if (etat.mode === 'recadrer' && active) {
			const c = active.reglages.cadre;
			dessinerGrille(gr.cadre, c.w * g.l / d, c.h * g.h / d, t === 'aucune' ? 'tiers' : t, grille.orientation, grille.densite);
		}
	}

	function majMenuGrille() {
		radiosGrille.forEach((i) => { i.checked = i.value === grille.type; });
		gr.pivoter.hidden = grille.type !== 'spirale' && grille.type !== 'triangles';
		gr.densiteBloc.hidden = grille.type !== 'quadrillage';
		gr.densite.value = grille.densite;
		const choisie = radiosGrille.find((i) => i.checked);
		gr.aide.textContent = choisie ? choisie.parentNode.title : '';
		el.grilleBouton.classList.toggle('rp-btn--allume', grille.type !== 'aucune');
		el.grilleBouton.title = 'Grille de composition : ' + nomGrille(grille.type).toLowerCase() + ' (touche O)';
		// les vignettes, dans l'orientation choisie
		gr.menu.querySelectorAll('svg[data-grille]').forEach((v) => dessinerGrille(v, 54, 36, v.dataset.grille, grille.orientation, 6));
	}
	let minuterieNomGrille = 0;
	function annoncerGrille(texte) {
		gr.nom.textContent = texte;
		gr.nom.hidden = false;
		clearTimeout(minuterieNomGrille);
		minuterieNomGrille = setTimeout(() => { gr.nom.hidden = true; }, 1400);
	}
	function choisirGrille(modif, annonce) {
		Object.assign(grille, modif);
		majMenuGrille();
		demanderRendu();
		planifierSauvegarde(null);
		if (annonce) { annoncerGrille(annonce); }
	}
	function grilleSuivante() {
		const t = ORDRE_GRILLES[(ORDRE_GRILLES.indexOf(grille.type) + 1) % ORDRE_GRILLES.length];
		choisirGrille({ type: t }, t === 'aucune' ? 'Sans grille' : 'Grille : ' + nomGrille(t));
	}
	function pivoterGrille() {
		if (grille.type !== 'spirale' && grille.type !== 'triangles') { return; }
		choisirGrille({ orientation: (grille.orientation + 1) % 4 }, grille.type === 'spirale' ? 'Spirale pivotée' : 'Triangles retournés');
	}

	function ouvrirMenuGrille(oui) {
		if (gr.menu.hidden === !oui) { return; }
		gr.menu.hidden = !oui;
		el.grilleBouton.setAttribute('aria-expanded', String(oui));
		if (!oui) { return; }
		majMenuGrille();
		// sous le bouton, sans deborder de la barre
		const barre = gr.menu.offsetParent.getBoundingClientRect(), b = el.grilleBouton.getBoundingClientRect();
		gr.menu.style.left = Math.max(8, Math.min(b.left - barre.left, barre.width - gr.menu.offsetWidth - 8)) + 'px';
		gr.menu.style.top = (b.bottom - barre.top + 6) + 'px';
		(radiosGrille.find((i) => i.checked) || radiosGrille[0]).focus();
	}
	el.grilleBouton.addEventListener('click', () => ouvrirMenuGrille(gr.menu.hidden));
	radiosGrille.forEach((i) => i.addEventListener('change', () => { if (i.checked) { choisirGrille({ type: i.value }); } }));
	gr.pivoter.addEventListener('click', pivoterGrille);
	gr.densite.addEventListener('input', () => choisirGrille({ densite: parseInt(gr.densite.value, 10) || 8 }));
	gr.menu.addEventListener('keydown', (e) => {
		if (e.key === 'Escape') { e.stopPropagation(); ouvrirMenuGrille(false); el.grilleBouton.focus(); }
	});
	document.addEventListener('pointerdown', (e) => {
		if (!gr.menu.hidden && !gr.menu.contains(e.target) && !el.grilleBouton.contains(e.target)) { ouvrirMenuGrille(false); }
	});
	majMenuGrille();

	/* --- Histogramme ------------------------------------------------------- */
	let minuterieHisto = 0;
	function planifierHisto() {
		clearTimeout(minuterieHisto);
		minuterieHisto = setTimeout(dessinerHisto, 140);
	}
	function dessinerHisto() {
		if (!active) { return; }
		const { px } = moteur.rendreReduit(active.reglages, 240, {});
		// rendu hors ecran : le canevas affiche n est pas touche, inutile de le redessiner
		const B = 128, hist = [new Float32Array(B), new Float32Array(B), new Float32Array(B)];
		for (let i = 0; i < px.length; i += 4) {
			hist[0][px[i] >> 1]++; hist[1][px[i + 1] >> 1]++; hist[2][px[i + 2] >> 1]++;
		}
		let max = 1;
		hist.forEach((h) => { for (let i = 2; i < B - 2; i++) { max = Math.max(max, h[i]); } });
		const cv = el.histo, g = cv.getContext('2d');
		g.clearRect(0, 0, cv.width, cv.height);
		g.globalCompositeOperation = 'lighter';
		['rgba(235,80,80,.62)', 'rgba(80,200,110,.62)', 'rgba(90,130,240,.66)'].forEach((couleur, k) => {
			g.beginPath();
			g.moveTo(0, cv.height);
			for (let i = 0; i < B; i++) {
				const v = Math.min(1, hist[k][i] / max);
				g.lineTo(i / (B - 1) * cv.width, cv.height - Math.sqrt(v) * (cv.height - 6));
			}
			g.lineTo(cv.width, cv.height);
			g.closePath();
			g.fillStyle = couleur;
			g.fill();
		});
		g.globalCompositeOperation = 'source-over';
	}

	/* --- Vignettes de la pellicule --------------------------------------------- */
	function creerVignette(photo) {
		const cv = document.createElement('canvas');
		cv.width = 168; cv.height = 120;
		const g = cv.getContext('2d');
		const s = Math.min(cv.width / photo.image.width, cv.height / photo.image.height);
		const w = photo.image.width * s, h = photo.image.height * s;
		g.drawImage(photo.image, (cv.width - w) / 2, (cv.height - h) / 2, w, h);
		photo.vignette = cv;
	}
	const minuteriesVignette = new Map();
	function planifierVignette(p) {
		if (p !== active) { majPellicule(); return; }
		clearTimeout(minuteriesVignette.get(p.id));
		minuteriesVignette.set(p.id, setTimeout(() => {
			if (p !== active) { return; }
			const { px, w, h } = moteur.rendreReduit(p.reglages, 168, {});
			const tmp = document.createElement('canvas'); tmp.width = w; tmp.height = h;
			tmp.getContext('2d').putImageData(new ImageData(new Uint8ClampedArray(px.buffer), w, h), 0, 0);
			const cv = p.vignette, g = cv.getContext('2d');
			g.clearRect(0, 0, cv.width, cv.height);
			const s = Math.min(cv.width / w, cv.height / h);
			g.drawImage(tmp, (cv.width - w * s) / 2, (cv.height - h * s) / 2, w * s, h * s);
			majPellicule();
			// gardee avec la sauvegarde : la pellicule reprise montre les photos retouchees
			cv.toBlob((b) => { if (b) { p.vignetteBlob = b; planifierSauvegarde(p); } }, 'image/jpeg', 0.82);
		}, 450));
	}

	function majPellicule() {
		el.pellicule.hidden = photos.length === 0;
		const plusieurs = photos.length > 1;
		$('rpSynchro').hidden = $('rpStudioTout').hidden = !plusieurs;
		$('rpCadrerTout').hidden = !plusieurs;
		el.exporterTout.hidden = !plusieurs;
		el.vignettes.textContent = '';
		photos.forEach((p, i) => {
			const li = document.createElement('li');
			li.className = 'rp-vignette-item';
			const b = document.createElement('button');
			b.type = 'button';
			b.className = 'rp-vignette';
			b.title = p.nom;
			b.setAttribute('aria-label', 'Photo ' + (i + 1) + ' : ' + p.nom + (p.modifie ? ' (retouchée)' : ''));
			if (p === active) { b.setAttribute('aria-current', 'true'); }
			b.appendChild(p.vignette);
			if (p.modifie) { const pt = document.createElement('span'); pt.className = 'rp-vignette__pastille'; pt.setAttribute('aria-hidden', 'true'); b.appendChild(pt); }
			b.addEventListener('click', () => activer(p));
			const x = document.createElement('button');
			x.type = 'button';
			x.className = 'rp-vignette__retirer';
			x.setAttribute('aria-label', 'Fermer ' + p.nom);
			x.title = 'Fermer cette photo';
			x.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
			x.addEventListener('click', (e) => { e.stopPropagation(); fermerPhoto(p); });
			li.appendChild(b); li.appendChild(x);
			el.vignettes.appendChild(li);
		});
	}

	async function fermerPhoto(p) {
		if (p.modifie && !p.exportee && window.Toast && window.Toast.confirm) {
			const ok = await window.Toast.confirm('Fermer « ' + p.nom + ' » ? Elle quitte l\'atelier et sa sauvegarde, sur cet appareil et sur le serveur : ses retouches non exportées seront perdues.', { confirmText: 'Fermer', danger: true });
			if (!ok) { return; }
		}
		const i = photos.indexOf(p);
		if (i < 0) { return; }
		photos.splice(i, 1);
		recents = recents.filter((x) => x !== p);
		oublierPhoto(p);
		try { p.image.close(); } catch (e) { /* deja liberee */ }
		if (active === p) {
			active = null;
			if (photos.length) { await activer(photos[Math.min(i, photos.length - 1)]); }
			else { quitterModes(); majInterface(); const g = moteur.gl; g.clearColor(0, 0, 0, 0); g.clear(g.COLOR_BUFFER_BIT); }
		}
		majPellicule();
	}

	/* ======================================================================
	   Etat d'un traitement
	   ====================================================================== */
	function afficherEtat(texte) { el.etatTexte.textContent = texte; el.etat.hidden = false; }
	function cacherEtat() { el.etat.hidden = true; }

	/* ======================================================================
	   Outils : avant/apres, zoom, recadrage, pinceau
	   ====================================================================== */
	function quitterModes() {
		if (etat.mode === 'recadrer') { basculerRecadrage(false); }
		if (etat.mode === 'pinceau') { basculerPinceau(false); }
		if (etat.mode === 'masque') { basculerMasque(false); }
		basculerPipette(false);
	}
	function majCurseurScene() {
		let mode = etat.zoom != null ? 'pan' : '';
		if (etat.mode === 'pinceau' || (etat.mode === 'masque' && pinceauMasque.mode !== 'non')) { mode = 'pinceau'; }
		else if (etat.mode === 'masque' && masqueChoisi() && TYPES_MASQUE[masqueChoisi().type].degrade) { mode = 'trace'; }
		scene.dataset.mode = etat.pipette ? 'pipette' : mode;
	}

	el.avantApres.addEventListener('click', () => basculerAvant());
	function basculerAvant(oui) {
		if (!active) { return; }
		const actif = oui == null ? etat.avant == null : oui;
		etat.avant = actif ? 0.5 : null;
		el.avantApres.setAttribute('aria-pressed', String(actif));
		demanderRendu();
	}

	el.zoomAjuster.addEventListener('click', () => { etat.zoom = null; majCurseurScene(); demanderRendu(); });
	el.zoom100.addEventListener('click', () => { etat.zoom = 1; majCurseurScene(); demanderRendu(); });

	function zoomer(facteur, clientX, clientY) {
		const g = etat.geo;
		if (!g) { return; }
		const b = canvas.getBoundingClientRect();
		const X = (clientX - b.left) * g.dpr, Y = (clientY - b.top) * g.dpr;
		const ox = (X - g.x) / g.l, oy = (Y - g.y) / g.h;
		const z = borne(g.z * facteur, Math.min(g.ajuste, 0.05), 8);
		if (z <= g.ajuste * 1.001 && facteur < 1) { etat.zoom = null; majCurseurScene(); demanderRendu(); return; }
		etat.zoom = z;
		const l = g.t.w * z, h = g.t.h * z;
		const x = X - ox * l, y = Y - oy * h;
		etat.centre = { x: (g.cw / 2 - x) / l, y: (g.ch / 2 - y) / h };
		majCurseurScene();
		demanderRendu();
	}
	scene.addEventListener('wheel', (e) => {
		if (!active || etat.mode === 'recadrer') { return; }
		e.preventDefault();
		zoomer(Math.pow(1.0015, -e.deltaY * (e.deltaMode === 1 ? 33 : 1)), e.clientX, e.clientY);
	}, { passive: false });

	/* Coordonnees d'un point de l'ecran : sortie (o), image orientee (p), source (q). */
	function versImage(clientX, clientY) {
		const g = etat.geo;
		const b = canvas.getBoundingClientRect();
		const X = (clientX - b.left) * g.dpr, Y = (clientY - b.top) * g.dpr;
		const o = { x: (X - g.x) / g.l, y: (Y - g.y) / g.h };
		const c = g.r.cadre;
		const p = { x: c.x + o.x * c.w, y: c.y + o.y * c.h };
		const m = M.matriceOrientation(g.r.rotation, g.r.miroir);
		const q = { x: m[0] * (p.x - 0.5) + m[2] * (p.y - 0.5) + 0.5, y: m[1] * (p.x - 0.5) + m[3] * (p.y - 0.5) + 0.5 };
		return { o, p, q };
	}

	/* --- Recadrage ----------------------------------------------------------- */
	el.recadrer.addEventListener('click', () => basculerRecadrage(etat.mode !== 'recadrer'));
	$('rpCadreValider').addEventListener('click', () => basculerRecadrage(false));
	function basculerRecadrage(oui) {
		if (!active) { return; }
		if (oui) {
			if (etat.mode === 'pinceau') { basculerPinceau(false); }
			if (etat.mode === 'masque') { basculerMasque(false); }
			etat.mode = 'recadrer'; etat.zoom = null;
		}
		else if (etat.mode === 'recadrer') { etat.mode = null; memoriser(); }
		el.recadrer.setAttribute('aria-pressed', String(etat.mode === 'recadrer'));
		el.outilsRecadrage.hidden = etat.mode !== 'recadrer';
		majCurseurScene();
		demanderRendu();
	}

	/* --- Formats du cadrage --------------------------------------------------- */
	const tailleOrientee = (r) => moteur.tailleSortie(Object.assign({}, r, { cadre: { x: 0, y: 0, w: 1, h: 1 } }));
	const persoDe = (r) => r.perso || { l: 1080, h: 1080, exact: false };

	/* Proportion largeur / hauteur du format, en pixels ; null : cadre libre. */
	function ratioPixels(r) {
		const v = r.ratio || 'libre';
		if (v === 'libre') { return null; }
		if (v === 'origine') { const t = tailleOrientee(r); return t.W / t.H; }
		if (v === 'perso') { const p = persoDe(r); return p.l > 0 && p.h > 0 ? p.l / p.h : null; }
		const [a, b] = v.split(':').map(Number);
		return a > 0 && b > 0 ? a / b : null;
	}
	/* La meme, dans les coordonnees du cadre (normalisees sur l'image orientee). */
	function ratioNormalise(r) {
		r = r || active.reglages;
		const k = ratioPixels(r);
		if (!k) { return null; }
		const t = tailleOrientee(r);
		return k * t.H / t.W;
	}
	function nomFormat(r) {
		const v = r.ratio || 'libre', p = persoDe(r);
		return { libre: 'libre', origine: "d'origine", '1:1': 'carré 1:1', '35:45': "photo d'identité 35 × 45", perso: 'personnalisé ' + p.l + ' × ' + p.h }[v] || v;
	}

	/* Le cadre prend le format a sa plus grande taille dans l image, centre la
	   ou etait l ancien cadre (un format choisi apres une photo d identite ne
	   reste pas minuscule). « Cadrer sur la personne » le resserre ensuite. */
	function appliquerRatio(r) {
		r = r || active.reglages;
		const k = ratioNormalise(r);
		if (!k) { return; }
		const c = r.cadre;
		const cx = c.x + c.w / 2, cy = c.y + c.h / 2;
		let w = 1, h = 1 / k;
		if (h > 1) { h = 1; w = k; }
		r.cadre = { x: borne(cx - w / 2, 0, 1 - w), y: borne(cy - h / 2, 0, 1 - h), w, h };
	}

	function choisirFormat(v) {
		if (!active) { return; }
		active.reglages.ratio = v;
		active.reglages.perso = persoDe(active.reglages);
		appliquerRatio();
		if (etat.mode !== 'recadrer') { basculerRecadrage(true); } // le cadre se voit et s'ajuste aussitot
		majInterface(); memoriser(); demanderRendu();
	}
	document.querySelectorAll('input[name="rpFormat"]').forEach((radio) => {
		radio.addEventListener('change', () => { if (radio.checked) { choisirFormat(radio.value); } });
	});
	el.ratio.addEventListener('change', () => choisirFormat(el.ratio.value));

	/* Format personnalise : largeur x hauteur (une proportion, ou des pixels). */
	[$('rpPersoL'), $('rpPersoH')].forEach((champ) => {
		champ.addEventListener('change', () => {
			if (!active) { return; }
			const r = active.reglages, p = Object.assign({}, persoDe(r));
			const v = Math.round(borne(parseFloat(String(champ.value).replace(',', '.')) || 0, 1, 8000));
			if (champ.id === 'rpPersoL') { p.l = v; } else { p.h = v; }
			r.perso = p; r.ratio = 'perso';
			appliquerRatio();
			majInterface(); memoriser(); demanderRendu();
		});
		champ.addEventListener('keydown', (e) => { if (e.key === 'Enter') { champ.blur(); } });
	});
	$('rpPersoExact').addEventListener('change', (e) => {
		if (!active) { return; }
		active.reglages.perso = Object.assign({}, persoDe(active.reglages), { exact: e.target.checked });
		majInterface(); memoriser();
	});

	/* Portrait <-> paysage : le format s'inverse, le cadre pivote d'un quart
	   autour de son centre et se ramene dans l'image. */
	function inverserFormat() {
		if (!active) { return; }
		const r = active.reglages, v = r.ratio || 'libre', t = tailleOrientee(r), c = r.cadre;
		if (v === 'perso') { const p = persoDe(r); r.perso = Object.assign({}, p, { l: p.h, h: p.l }); }
		else if (v === 'origine') { r.ratio = 'perso'; r.perso = { l: t.H, h: t.W, exact: false }; }
		else if (v !== 'libre') { const [a, b] = v.split(':'); r.ratio = b + ':' + a; }
		let w = c.h * t.H / t.W, h = c.w * t.W / t.H;
		const s = Math.min(1, 1 / w, 1 / h);
		w *= s; h *= s;
		const cx = c.x + c.w / 2, cy = c.y + c.h / 2;
		r.cadre = { x: borne(cx - w / 2, 0, 1 - w), y: borne(cy - h / 2, 0, 1 - h), w, h };
		majInterface(); memoriser(); demanderRendu();
	}
	$('rpInverser').addEventListener('click', inverserFormat);
	$('rpInverserFlottant').addEventListener('click', inverserFormat);
	$('rpAjusterCadre').addEventListener('click', () => basculerRecadrage(etat.mode !== 'recadrer'));

	/* Le panneau suit les reglages : format coche, champs, dimensions du cadre. */
	function majCadrage() {
		if (!active) { return; }
		const r = active.reglages, v = r.ratio || 'libre', p = persoDe(r);
		document.querySelectorAll('input[name="rpFormat"]').forEach((i) => { i.checked = i.value === v; });
		el.ratio.value = v;
		$('rpPersoBloc').hidden = v !== 'perso';
		$('rpPersoL').value = p.l; $('rpPersoH').value = p.h; $('rpPersoExact').checked = !!p.exact;
		$('rpCadrerTout').hidden = photos.length < 2;
		majInfoCadrage();
	}
	function majInfoCadrage() {
		if (!active) { return; }
		const r = active.reglages, t = tailleOrientee(r), c = r.cadre;
		const w = Math.round(c.w * t.W), k = ratioPixels(r), h = k ? Math.round(w / k) : Math.round(c.h * t.H);
		$('rpCadrageInfo').textContent = 'Cadre : ' + w + ' × ' + h + ' px, format ' + nomFormat(r) + '.';
		$('rpCadreTaille').textContent = w + ' × ' + h + ' px';
	}

	function tourner(sens) {
		const r = active.reglages, c = r.cadre;
		// sous miroir, le quart de tour de la source va dans l autre sens
		r.rotation = (r.rotation + (r.miroir ? -sens : sens) + 4) % 4;
		r.cadre = sens > 0 ? { x: 1 - c.y - c.h, y: c.x, w: c.h, h: c.w } : { x: c.y, y: 1 - c.x - c.w, w: c.h, h: c.w };
		memoriser(); majInterface(); demanderRendu();
	}
	$('rpTournerG').addEventListener('click', () => active && tourner(-1));
	$('rpTournerD').addEventListener('click', () => active && tourner(1));
	$('rpMiroir').addEventListener('click', () => {
		if (!active) { return; }
		const r = active.reglages;
		r.miroir = !r.miroir;
		r.cadre = Object.assign({}, r.cadre, { x: 1 - r.cadre.x - r.cadre.w });
		memoriser(); demanderRendu();
	});
	$('rpCadreEntier').addEventListener('click', () => {
		if (!active) { return; }
		active.reglages.cadre = { x: 0, y: 0, w: 1, h: 1 };
		active.reglages.ratio = 'libre';
		majInterface(); memoriser(); demanderRendu();
	});
	/* Cadrage automatique sur la personne, dans le format choisi (le format
	   d'origine si le cadre est libre). */
	async function cadrerSurPersonne() {
		if (!active || etat.occupe) { return; }
		const p = active;
		const ok = await assurerDetourage(p);
		if (!p.reglages.ratio || p.reglages.ratio === 'libre') { p.reglages.ratio = 'origine'; }
		const c = ok ? cadrePortrait(p, p.reglages) : null;
		if (!c) { toast('warning', "Aucune personne détectée : le cadrage automatique n'est pas possible sur cette photo."); return; }
		p.reglages.cadre = c;
		if (active === p) { majInterface(); demanderRendu(); }
		memoriser(p);
	}
	$('rpCadrerPersonne').addEventListener('click', cadrerSurPersonne);
	$('rpCadrerVisage').addEventListener('click', cadrerSurPersonne);

	/* Le format de la photo affichee pour toutes : chacune est cadree sur sa
	   personne (detouree au besoin), ou au centre si personne n'y est trouve. */
	$('rpCadrerTout').addEventListener('click', async () => {
		if (!active || photos.length < 2 || etat.occupe) { return; }
		etat.occupe = true;
		const depart = active;
		const ratio = depart.reglages.ratio && depart.reglages.ratio !== 'libre' ? depart.reglages.ratio : 'origine';
		const perso = copie(persoDe(depart.reglages));
		let auCentre = 0;
		try {
			for (let i = 0; i < photos.length; i++) {
				const p = photos[i];
				afficherEtat('Cadrage : photo ' + (i + 1) + ' sur ' + photos.length + '…');
				await activer(p);
				const ok = await assurerDetourage(p);
				p.reglages.ratio = ratio; p.reglages.perso = copie(perso);
				const c = ok ? cadrePortrait(p, p.reglages) : null;
				if (c) { p.reglages.cadre = c; }
				else { p.reglages.cadre = { x: 0, y: 0, w: 1, h: 1 }; appliquerRatio(p.reglages); auCentre++; }
				memoriser(p);
				majInterface(); rendre();
				planifierVignette(p);
				await new Promise((r) => setTimeout(r, 480));
			}
		} finally {
			cacherEtat();
			etat.occupe = false;
			if (depart && photos.includes(depart)) { await activer(depart); }
		}
		toast(auCentre ? 'warning' : 'success', photos.length + ' photos cadrées en ' + nomFormat(depart.reglages)
			+ (auCentre ? ' ; ' + auCentre + ' sans personne détectée, cadrée' + (auCentre > 1 ? 's' : '') + ' au centre.' : '.'));
	});

	// Glisser le cadre ou ses poignees
	el.cadre.addEventListener('pointerdown', (e) => {
		if (!active || etat.mode !== 'recadrer') { return; }
		e.preventDefault(); e.stopPropagation();
		const poignee = e.target.dataset.poignee || 'deplacer';
		const g = etat.geo, depart = Object.assign({}, active.reglages.cadre);
		const x0 = e.clientX, y0 = e.clientY;
		const k = ratioNormalise();
		el.cadre.setPointerCapture(e.pointerId);
		const bouger = (ev) => {
			const dx = (ev.clientX - x0) * g.dpr / g.l, dy = (ev.clientY - y0) * g.dpr / g.h;
			active.reglages.cadre = poignee === 'deplacer'
				? { x: borne(depart.x + dx, 0, 1 - depart.w), y: borne(depart.y + dy, 0, 1 - depart.h), w: depart.w, h: depart.h }
				: redimensionner(depart, poignee, dx, dy, k);
			demanderRendu();
		};
		const lacher = () => {
			el.cadre.removeEventListener('pointermove', bouger);
			el.cadre.removeEventListener('pointerup', lacher);
			el.cadre.removeEventListener('pointercancel', lacher);
			memoriser();
		};
		el.cadre.addEventListener('pointermove', bouger);
		el.cadre.addEventListener('pointerup', lacher);
		el.cadre.addEventListener('pointercancel', lacher);
	});

	function redimensionner(d, poignee, dx, dy, k) {
		const MIN = 0.03;
		let x0 = d.x, y0 = d.y, x1 = d.x + d.w, y1 = d.y + d.h;
		const o = poignee.includes('o'), e = poignee.includes('e'), n = poignee.includes('n'), s = poignee.includes('s');
		if (o) { x0 = borne(x0 + dx, 0, x1 - MIN); }
		if (e) { x1 = borne(x1 + dx, x0 + MIN, 1); }
		if (n) { y0 = borne(y0 + dy, 0, y1 - MIN); }
		if (s) { y1 = borne(y1 + dy, y0 + MIN, 1); }
		if (!k) { return { x: x0, y: y0, w: x1 - x0, h: y1 - y0 }; }
		let w = x1 - x0, h = y1 - y0;
		const coin = (o || e) && (n || s);
		// point fixe : le coin oppose, ou le milieu du cote oppose
		let ax, ay;
		if (coin) {
			if (w / h > k) { w = h * k; } else { h = w / k; }
			ax = o ? d.x + d.w : d.x; ay = n ? d.y + d.h : d.y;
		} else if (n || s) {
			w = h * k; ax = d.x + d.w / 2; ay = n ? d.y + d.h : d.y;
		} else {
			h = w / k; ay = d.y + d.h / 2; ax = o ? d.x + d.w : d.x;
		}
		const gx = coin || o || e ? (o ? -1 : 1) : 0, gy = coin || n || s ? (n ? -1 : 1) : 0;
		// etendue disponible depuis le point fixe, dans chaque direction
		const limiteX = gx < 0 ? ax : (gx > 0 ? 1 - ax : 2 * Math.min(ax, 1 - ax));
		const limiteY = gy < 0 ? ay : (gy > 0 ? 1 - ay : 2 * Math.min(ay, 1 - ay));
		const sEchelle = Math.min(1, limiteX / w, limiteY / h);
		w *= sEchelle; h *= sEchelle;
		const x = gx < 0 ? ax - w : (gx > 0 ? ax : ax - w / 2);
		const y = gy < 0 ? ay - h : (gy > 0 ? ay : ay - h / 2);
		return { x, y, w, h };
	}

	/* --- Pinceau de detourage -------------------------------------------------- */
	el.pinceau.addEventListener('click', () => basculerPinceau(etat.mode !== 'pinceau'));
	$('rpPinceauValider').addEventListener('click', () => basculerPinceau(false));
	function basculerPinceau(oui) {
		if (!active) { return; }
		if (oui) {
			if (etat.mode === 'recadrer') { basculerRecadrage(false); }
			if (etat.mode === 'masque') { basculerMasque(false); }
			etat.mode = 'pinceau';
			assurerDetourage(active).then(() => demanderRendu());
		} else if (etat.mode === 'pinceau') { etat.mode = null; }
		el.pinceau.setAttribute('aria-pressed', String(etat.mode === 'pinceau'));
		planifierSauvegarde(null);
		el.outilsPinceau.hidden = etat.mode !== 'pinceau';
		el.curseur.hidden = true;
		majCurseurScene();
		demanderRendu();
	}
	document.querySelectorAll('input[name="rpPinceauMode"]').forEach((r) => r.addEventListener('change', () => { if (r.checked) { pinceau.mode = r.value; } }));
	// taille et douceur : les memes pour le pinceau du detourage et celui des masques
	function regler(cle, v) {
		pinceau[cle] = v;
		(cle === 'taille' ? ['rpPinceauTaille', 'rpMasqueTaille'] : ['rpPinceauDouceur', 'rpMasqueDouceur']).forEach((id) => { $(id).value = v; });
		planifierSauvegarde(null);   // la taille et la douceur se retrouvent en revenant
	}
	['rpPinceauTaille', 'rpMasqueTaille'].forEach((id) => $(id).addEventListener('input', (e) => regler('taille', parseInt(e.target.value, 10))));
	['rpPinceauDouceur', 'rpMasqueDouceur'].forEach((id) => $(id).addEventListener('input', (e) => regler('douceur', parseInt(e.target.value, 10))));
	$('rpPinceauEffacer').addEventListener('click', () => {
		if (!active || !active.retouches) { return; }
		active.retouches = new Uint8Array(active.retouches.length).fill(128);
		appliquerRetouches(active, 0, active.t.h);
		memoriser(); demanderRendu();
	});

	function placerCurseurPinceau(e, alt) {
		const b = scene.getBoundingClientRect();
		const mode = etat.mode === 'masque' ? pinceauMasque.mode : pinceau.mode;
		Object.assign(el.curseur.style, { left: (e.clientX - b.left) + 'px', top: (e.clientY - b.top) + 'px', width: pinceau.taille + 'px', height: pinceau.taille + 'px' });
		el.curseur.dataset.mode = (mode === 'ajouter') !== alt ? 'ajouter' : 'retirer';
		el.curseur.hidden = false;
	}

	/* Un coup de pinceau dans une carte (128 neutre) a la taille de travail :
	   renvoie les lignes touchees [y0, y1[ ou null. */
	function tamponner(m, qx, qy, ajouter) {
		const t = active.t, g = etat.geo;
		const R = Math.max(1, (pinceau.taille / 2) * g.dpr / g.z * t.ech);
		const dur = 1 - (pinceau.douceur / 100) * 0.95;
		const cx = qx * t.w, cy = qy * t.h;
		const x0 = Math.max(0, Math.floor(cx - R)), x1 = Math.min(t.w - 1, Math.ceil(cx + R));
		const y0 = Math.max(0, Math.floor(cy - R)), y1 = Math.min(t.h - 1, Math.ceil(cy + R));
		const cible = ajouter ? 255 : 1;
		for (let y = y0; y <= y1; y++) {
			for (let x = x0; x <= x1; x++) {
				const d = Math.hypot(x + 0.5 - cx, y + 0.5 - cy) / R;
				if (d >= 1) { continue; }
				let f = d <= dur ? 1 : 1 - (d - dur) / (1 - dur);
				f = f * f * (3 - 2 * f) * 0.6;
				const i = y * t.w + x;
				m[i] = m[i] + (cible - m[i]) * f + 0.5;
			}
		}
		return y1 >= y0 ? [y0, y1 + 1] : null;
	}

	function peindre(qx, qy, ajouter) {
		const lignes = tamponner(active.retouches, qx, qy, ajouter);
		if (lignes) { appliquerRetouches(active, lignes[0], lignes[1]); }
	}

	/* Un trait au pinceau, du point d'appui au relachement : les tampons se
	   suivent tous les 30 % du rayon. tamponnerA(q, ajouter) pose un tampon. */
	function tracerAuPinceau(e, tamponnerA, sens, fin) {
		const g = etat.geo;
		let dernier = null;
		const trait = (ev) => {
			placerCurseurPinceau(ev, ev.altKey);
			const { q } = versImage(ev.clientX, ev.clientY);
			const ajouter = (sens() === 'ajouter') !== ev.altKey;
			if (dernier) {
				const t = active.t;
				const dist = Math.hypot((q.x - dernier.x) * t.w, (q.y - dernier.y) * t.h);
				const R = Math.max(1, (pinceau.taille / 2) * g.dpr / g.z * t.ech);
				const pas = Math.max(1, R * 0.3), n = Math.ceil(dist / pas);
				for (let i = 1; i <= n; i++) { tamponnerA({ x: dernier.x + (q.x - dernier.x) * i / n, y: dernier.y + (q.y - dernier.y) * i / n }, ajouter); }
			} else { tamponnerA(q, ajouter); }
			dernier = q;
			demanderRendu();
		};
		trait(e);
		const finir = () => {
			scene.removeEventListener('pointermove', trait);
			scene.removeEventListener('pointerup', finir);
			scene.removeEventListener('pointercancel', finir);
			fin();
		};
		scene.addEventListener('pointermove', trait);
		scene.addEventListener('pointerup', finir);
		scene.addEventListener('pointercancel', finir);
	}

	/* --- Masques : creation, liste, mode d'edition ------------------------------- */
	function indexMasqueChoisi() {
		return active && etat.masqueId ? (active.reglages.masques || []).findIndex((m) => m.id === etat.masqueId) : -1;
	}

	/* Un point de l'image orientee (0..1) dans la source. */
	function vueVersSource(px, py) {
		const r = active.reglages, m = M.matriceOrientation(r.rotation, r.miroir);
		return { x: m[0] * (px - 0.5) + m[2] * (py - 0.5) + 0.5, y: m[1] * (px - 0.5) + m[3] * (py - 0.5) + 0.5 };
	}
	/* Un point de la source sur l'ecran, en pixels CSS de la scene (inverse de versImage). */
	function versEcran(qx, qy) {
		const g = etat.geo, r = g.r, m = M.matriceOrientation(r.rotation, r.miroir), c = r.cadre;
		const dx = qx - 0.5, dy = qy - 0.5;
		const px = m[0] * dx + m[1] * dy + 0.5, py = m[2] * dx + m[3] * dy + 0.5;
		return { x: (g.x + (px - c.x) / c.w * g.l) / g.dpr, y: (g.y + (py - c.y) / c.h * g.h) / g.dpr };
	}
	const ratioSource = () => moteur.tailleSource.w / moteur.tailleSource.h;

	/* Degrade de depart, d'apres le cadre affiche : lineaire du haut du cadre
	   vers son milieu (un ciel), radial au centre, rond, sur 60 % du petit cote. */
	function geometrieDefaut(type) {
		const r = active.reglages, c = r.cadre;
		if (type === 'lineaire') {
			const a = vueVersSource(c.x + c.w / 2, c.y + c.h * 0.12), b = vueVersSource(c.x + c.w / 2, c.y + c.h * 0.55);
			return { ax: a.x, ay: a.y, bx: b.x, by: b.y };
		}
		const t = moteur.tailleSortie(r), centre = vueVersSource(c.x + c.w / 2, c.y + c.h / 2);
		const rayon = 0.3 * Math.min(t.w, t.h) / moteur.tailleSource.h;   // en hauteurs de la source
		return { cx: centre.x, cy: centre.y, rx: rayon, ry: rayon, angle: 0, plume: 50 };
	}

	const nouvelId = () => 'm' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);

	function ajouterMasque(type) {
		if (!active || !TYPES_MASQUE[type]) { return null; }
		const liste = active.reglages.masques || (active.reglages.masques = []);
		if (liste.length >= MASQUES_MAX) { toast('warning', 'Huit masques au plus par photo : supprimez-en un pour en créer un autre.'); return null; }
		const r = {};
		LOCAUX.forEach((k) => { r[k] = 0; });
		const memes = liste.filter((x) => x.type === type).length;
		const m = {
			id: nouvelId(), type,
			nom: TYPES_MASQUE[type].nom + (memes ? ' ' + (memes + 1) : ''), actif: true, inverse: false, quantite: 100, r,
		};
		if (TYPES_MASQUE[type].degrade) { m.geo = geometrieDefaut(type); }
		liste.push(m);
		cuireMasques(active);
		memoriser();
		basculerMasque(true, m.id);
		return m;
	}

	function supprimerMasque(id) {
		if (!active) { return; }
		const liste = active.reglages.masques || [];
		const i = liste.findIndex((m) => m.id === id);
		if (i < 0) { return; }
		if (etat.masqueId === id) { basculerMasque(false); }
		liste.splice(i, 1);
		if (active.calques[id]) { active.calques = Object.assign({}, active.calques); delete active.calques[id]; }
		cuireMasques(active);
		memoriser(); majMasques(); demanderRendu();
	}

	/* Le mode Masques : un masque choisi, sa zone en rouge, ses outils sur la
	   photo et ses reglages dans le panneau. En sortir le deselectionne. */
	let minuterieFlash = 0;
	function basculerMasque(oui, id) {
		if (!active) { return; }
		if (oui) {
			const m = (active.reglages.masques || []).find((x) => x.id === (id || etat.masqueId));
			if (!m) { return; }
			if (etat.mode === 'recadrer') { basculerRecadrage(false); }
			if (etat.mode === 'pinceau') { basculerPinceau(false); }
			if (etat.masqueId !== m.id) {
				pinceauMasque.mode = m.type === 'pinceau' ? 'ajouter' : 'non';
				// la zone se montre un instant, meme la souris dans le panneau
				etat.flashZone = true;
				clearTimeout(minuterieFlash);
				minuterieFlash = setTimeout(() => { etat.flashZone = false; demanderRendu(); }, 1600);
			}
			etat.mode = 'masque';
			etat.masqueId = m.id;
			if (TYPES_MASQUE[m.type].detourage) { assurerDetourage(active); }
		} else if (etat.mode === 'masque') {
			etat.mode = null;
			etat.masqueId = null;
		}
		el.masques.setAttribute('aria-pressed', String(etat.mode === 'masque'));
		planifierSauvegarde(null);   // le masque en cours se retrouve en revenant
		el.curseur.hidden = true;
		majMasques();
		majCurseurScene();
		demanderRendu();
	}

	/* Le volet Masques, ouvert et amene dans le panneau. Sur telephone, le
	   panneau est sous la photo : on n'y descend que pour choisir un type. */
	function montrerVoletMasques(memeSurTelephone) {
		el.voletMasques.open = true;
		if (memeSurTelephone || window.matchMedia('(min-width: 861px)').matches) {
			el.voletMasques.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
		}
	}
	el.masques.addEventListener('click', () => {
		if (!active) { return; }
		if (etat.mode === 'masque') { basculerMasque(false); return; }
		const liste = active.reglages.masques || [];
		montrerVoletMasques(!liste.length);
		if (liste.length) { basculerMasque(true, liste[liste.length - 1].id); }
		else { const b = document.querySelector('[data-type-masque]'); if (b) { b.focus({ preventScroll: true }); } }
	});
	document.querySelectorAll('[data-type-masque]').forEach((b) => b.addEventListener('click', () => {
		const m = ajouterMasque(b.dataset.typeMasque);
		if (!m) { return; }
		// sur telephone, un degrade ou un pinceau se trace sur la photo, au-dessus du panneau
		if (!window.matchMedia('(min-width: 861px)').matches && !TYPES_MASQUE[m.type].detourage) { scene.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
		else { montrerVoletMasques(); }
	}));

	function resumeMasque(m) {
		const parts = [];
		LOCAUX.forEach((k) => {
			const v = m.r[k] || 0;
			if (!v) { return; }
			parts.push(k === 'exposition'
				? (v > 0 ? '+' : '−') + Math.abs(v).toFixed(2).replace('.', ',') + ' IL'
				: LOCAUX_NOMS[k] + ' ' + (v > 0 ? '+' : '−') + Math.abs(Math.round(v)));
		});
		if (!parts.length) { return 'Aucun réglage pour l\'instant'; }
		return parts.slice(0, 2).join(' · ') + (parts.length > 2 ? ' · ' + (parts.length - 2) + ' de plus' : '');
	}

	function boutonIcone(icone, titre, classe) {
		const b = document.createElement('button');
		b.type = 'button';
		b.className = 'rp-btn rp-btn--icone rp-btn--petit ' + (classe || '');
		b.title = titre;
		b.setAttribute('aria-label', titre);
		b.innerHTML = '<i class="bi ' + icone + '" aria-hidden="true"></i>';
		return b;
	}

	function majListeMasques() {
		const liste = active ? (active.reglages.masques || []) : [];
		el.listeMasques.textContent = '';
		liste.forEach((m) => {
			const choisi = m.id === etat.masqueId, eteint = m.actif === false;
			const li = document.createElement('li');
			li.className = 'rp-masque-ligne' + (choisi ? ' est-choisi' : '') + (eteint ? ' est-eteint' : '');
			const choix = document.createElement('button');
			choix.type = 'button';
			choix.className = 'rp-masque-ligne__choix';
			choix.setAttribute('aria-pressed', String(choisi));
			choix.title = choisi ? 'Terminer ce masque' : 'Modifier ce masque';
			choix.innerHTML = '<i class="bi ' + TYPES_MASQUE[m.type].icone + '" aria-hidden="true"></i>';
			const textes = document.createElement('span');
			textes.className = 'rp-masque-ligne__textes';
			const nom = document.createElement('span');
			nom.className = 'rp-masque-ligne__nom';
			nom.textContent = m.nom + (m.inverse ? ' (inversé)' : '');
			const resume = document.createElement('span');
			resume.className = 'rp-masque-ligne__resume';
			resume.textContent = eteint ? 'Désactivé' : resumeMasque(m);
			textes.append(nom, resume);
			choix.appendChild(textes);
			choix.addEventListener('click', () => basculerMasque(!(etat.mode === 'masque' && choisi), m.id));
			const voir = boutonIcone(eteint ? 'bi-eye-slash' : 'bi-eye', eteint ? 'Réactiver ce masque' : 'Désactiver ce masque (voir la photo sans lui)');
			voir.setAttribute('aria-pressed', String(eteint));
			voir.addEventListener('click', () => {
				m.actif = eteint;
				if (m.actif && TYPES_MASQUE[m.type].detourage) { assurerDetourage(active); }
				memoriser(); majMasques(); demanderRendu();
			});
			const suppr = boutonIcone('bi-trash3', 'Supprimer ce masque', 'rp-btn--discret');
			suppr.addEventListener('click', () => supprimerMasque(m.id));
			li.append(choix, voir, suppr);
			el.listeMasques.appendChild(li);
		});
		el.masquesVide.hidden = liste.length > 0;
		document.querySelectorAll('[data-type-masque]').forEach((b) => { b.disabled = liste.length >= MASQUES_MAX; });
	}

	function majMasques() {
		majListeMasques();
		const m = masqueChoisi();
		el.masque.hidden = !m;
		if (m) {
			$('rpMasqueInverser').checked = !!m.inverse;
			$('rpMasqueVoir').checked = etat.voirZone;
			$('rpMasqueLimite').value = m.limite || '';
			el.masque.querySelectorAll('[data-pour-masque]').forEach((b) => { b.hidden = !b.dataset.pourMasque.split(' ').includes(m.type); });
			curseurs.forEach((b) => { if (b.dataset.reglage.indexOf('m_') === 0) { afficherValeur(b, lire(b.dataset.reglage)); } });
		}
		majOutilsMasque();
	}

	/* La barre posee sur la photo : nom du masque, pinceau, aide. */
	function majOutilsMasque() {
		const m = etat.mode === 'masque' ? masqueChoisi() : null;
		el.outilsMasque.hidden = !m;
		if (!m) { return; }
		const def = TYPES_MASQUE[m.type];
		$('rpOutilsMasqueNom').textContent = m.nom;
		$('rpOutilsMasqueIcone').className = 'bi ' + def.icone;
		// sans pinceau, la souris trace le degrade, ou deplace la vue agrandie
		$('rpMasqueSansPinceau').textContent = def.degrade ? 'Tracer' : 'Déplacer';
		document.querySelectorAll('input[name="rpMasquePinceau"]').forEach((r) => { r.checked = r.value === pinceauMasque.mode; });
		document.querySelectorAll('[data-si-pinceau]').forEach((x) => { x.hidden = pinceauMasque.mode === 'non'; });
		$('rpMasqueEffacer').hidden = !active.calques[m.id];
		let aide = def.degrade ? AIDES_MASQUE[m.type] : (m.type === 'pinceau' ? AIDES_MASQUE.pinceau : AIDES_MASQUE.detourage);
		if (def.detourage && !active.seg) { aide = AIDES_MASQUE.attente; }
		if (def.degrade && pinceauMasque.mode !== 'non') { aide = 'Le pinceau complète le dégradé (Alt inverse le pinceau). « Tracer » revient au tracé et aux poignées.'; }
		$('rpMasqueAide').textContent = aide;
	}

	document.querySelectorAll('input[name="rpMasquePinceau"]').forEach((r) => r.addEventListener('change', () => {
		if (!r.checked) { return; }
		pinceauMasque.mode = r.value;
		el.curseur.hidden = true;
		majOutilsMasque(); majCurseurScene(); demanderRendu();
	}));
	$('rpMasqueEffacer').addEventListener('click', () => {
		const m = masqueChoisi();
		if (!m || !active.calques[m.id]) { return; }
		active.calques = Object.assign({}, active.calques);
		delete active.calques[m.id];
		cuireMasques(active);
		memoriser(); majMasques(); demanderRendu();
	});
	$('rpMasqueTermine').addEventListener('click', () => basculerMasque(false));
	$('rpMasqueInverser').addEventListener('change', (e) => {
		const m = masqueChoisi();
		if (!m) { return; }
		m.inverse = e.target.checked;
		cuireMasques(active);
		memoriser(); majListeMasques(); demanderRendu();
	});
	$('rpMasqueVoir').addEventListener('change', (e) => { etat.voirZone = e.target.checked; planifierSauvegarde(null); demanderRendu(); });
	/* Limiter un pinceau ou un degrade a la personne, ou a l'arriere-plan : la
	   lumiere d'un degrade ne deborde plus sur le mur. */
	$('rpMasqueLimite').addEventListener('change', (e) => {
		const m = masqueChoisi();
		if (!m) { return; }
		m.limite = e.target.value || null;
		if (m.limite) { assurerDetourage(active); }
		memoriser(); majListeMasques(); demanderRendu();
	});
	$('rpMasqueZero').addEventListener('click', () => {
		const m = masqueChoisi();
		if (!m) { return; }
		LOCAUX.forEach((k) => { m.r[k] = 0; });
		m.quantite = 100;
		memoriser(); majMasques(); demanderRendu();
	});

	/* Tracer un degrade a la souris : du point d'appui au relachement. */
	function tracerDegrade(e, m) {
		e.preventDefault();
		scene.setPointerCapture(e.pointerId);
		const depart = versImage(e.clientX, e.clientY).q;
		const x0 = e.clientX, y0 = e.clientY;
		let bouge = false;
		const suivre = (ev) => {
			if (!bouge && Math.hypot(ev.clientX - x0, ev.clientY - y0) < 6) { return; }
			bouge = true;
			const q = versImage(ev.clientX, ev.clientY).q;
			if (m.type === 'lineaire') { m.geo = { ax: depart.x, ay: depart.y, bx: q.x, by: q.y }; }
			else {
				const r = Math.max(0.01, Math.hypot((q.x - depart.x) * ratioSource(), q.y - depart.y));
				m.geo = Object.assign({}, m.geo, { cx: depart.x, cy: depart.y, rx: r, ry: r, angle: 0 });
			}
			demanderRendu();
		};
		const fin = () => {
			scene.removeEventListener('pointermove', suivre);
			scene.removeEventListener('pointerup', fin);
			scene.removeEventListener('pointercancel', fin);
			if (bouge) { memoriser(); }
		};
		scene.addEventListener('pointermove', suivre);
		scene.addEventListener('pointerup', fin);
		scene.addEventListener('pointercancel', fin);
	}

	/* --- Fonds de studio --------------------------------------------------------- */
	document.querySelectorAll('[data-fond-studio]').forEach((b) => b.addEventListener('click', () => {
		if (!active) { return; }
		const f = active.reglages.fond;
		f.studioA = b.dataset.a; f.studioB = b.dataset.b; f.centreY = parseFloat(b.dataset.centre) || 0.5;
		if (f.mode !== 'studio') { changerFond('studio'); return; }
		majInterface(); memoriser(); demanderRendu();
	}));
	function majFondsStudio() {
		const f = active ? active.reglages.fond : null;
		// la vignette du mode Studio prend les couleurs du fond de studio en cours
		const vue = document.querySelector('.rp-fond__echantillon--studio');
		if (vue) { vue.style.background = f ? 'radial-gradient(ellipse 80% 105% at 50% ' + Math.round((f.centreY == null ? 0.5 : f.centreY) * 100) + '%, ' + f.studioA + ', ' + f.studioB + ')' : ''; }
		document.querySelectorAll('[data-fond-studio]').forEach((b) => {
			const choisi = !!f && f.mode === 'studio' && f.studioA.toLowerCase() === b.dataset.a && f.studioB.toLowerCase() === b.dataset.b;
			b.setAttribute('aria-pressed', String(choisi));
		});
	}

	/* --- Portrait : eclairage studio et teint ---------------------------------- */
	/* Le visage, d'apres le detourage : centre en coordonnees source
	   normalisees, largeur et hauteur en pixels de la source. */
	function boiteVisage(p) {
		const s = p.seg, b = s && (s.visageBoite || s.teteBoite);
		if (!b) { return null; }
		const W = p.image.width, H = p.image.height;
		return { cx: (b.x0 + b.x1) / 2 / W, cy: (b.y0 + b.y1) / 2 / H, l: b.x1 - b.x0, h: b.y1 - b.y0, W, H };
	}
	function masqueEclairage(type, nom, reglages, geo, limite) {
		const r = {};
		LOCAUX.forEach((k) => { r[k] = 0; });
		return { id: nouvelId(), type, nom, actif: true, inverse: false, quantite: 100, r: Object.assign(r, reglages), geo: geo || undefined, limite: limite || null, origine: 'eclairage' };
	}
	/* Un halo sur le visage : rayons en hauteurs de la source. */
	const haloVisage = (f, kx, ky, plume) => ({ cx: f.cx, cy: f.cy, rx: f.l * kx / 2 / f.H, ry: f.h * ky / 2 / f.H, angle: 0, plume });
	/* Les masques de chaque eclairage, poses sur le visage f. */
	const ECLAIRAGES = {
		doux: (f) => [
			masqueEclairage('radial', 'Éclairage doux · visage', { exposition: 0.3, ombres: 18, hautesLumieres: -6, clarte: -8 }, haloVisage(f, 1.35, 1.5, 85), 'sujet'),
		],
		studio: (f) => [
			masqueEclairage('sujet', 'Éclairage studio · personne', { contraste: 12, clarte: 8, nettete: 18, noirs: -8 }),
			masqueEclairage('radial', 'Éclairage studio · visage', { exposition: 0.18, ombres: 10 }, haloVisage(f, 1.25, 1.4, 80), 'sujet'),
		],
		lateral: (f) => {
			// la lumiere vient de la gauche de la photo telle qu'on la voit (rotation et miroir compris)
			const m = M.matriceOrientation(active.reglages.rotation, active.reglages.miroir);
			const dx = m[0], dy = m[1], D = dx !== 0 ? f.l : f.h;
			const pt = (k) => ({ x: f.cx + dx * k * D / f.W, y: f.cy + dy * k * D / f.H });
			const g = pt(-0.9), gm = pt(-0.3), d = pt(0.9), dm = pt(0.3);
			return [
				masqueEclairage('lineaire', 'Éclairage latéral · lumière', { exposition: 0.45, ombres: 14 }, { ax: g.x, ay: g.y, bx: dm.x, by: dm.y }, 'sujet'),
				masqueEclairage('lineaire', 'Éclairage latéral · ombre', { exposition: -0.5, ombres: -12 }, { ax: d.x, ay: d.y, bx: gm.x, by: gm.y }, 'sujet'),
			];
		},
		eclat: () => [
			masqueEclairage('peauVisage', 'Éclat · peau du visage', { exposition: 0.2, hautesLumieres: 12, clarte: 10, saturation: 6 }),
			masqueEclairage('sujet', 'Éclat · personne', { nettete: 20, contraste: 6 }),
		],
	};

	async function appliquerEclairage(cle) {
		const p = active;
		if (!p || (cle !== 'aucun' && !ECLAIRAGES[cle])) { return; }
		if (cle !== 'aucun') {
			const ok = await assurerDetourage(p);
			if (p !== active) { return; }
			if (!ok || !boiteVisage(p)) { toast('warning', "Aucun visage n'a été trouvé sur cette photo : l'éclairage studio se pose sur le visage détecté."); return; }
		}
		const garde = (p.reglages.masques || []).filter((m) => m.origine !== 'eclairage');
		const nouveaux = cle === 'aucun' ? [] : ECLAIRAGES[cle](boiteVisage(p));
		if (garde.length + nouveaux.length > MASQUES_MAX) { toast('warning', "Plus de place pour l'éclairage : huit masques au plus par photo. Supprimez-en un dans « Masques »."); return; }
		const choisi = masqueChoisi();
		if (etat.mode === 'masque' && choisi && choisi.origine === 'eclairage') { basculerMasque(false); }
		p.reglages.masques = garde.concat(nouveaux);
		p.reglages.eclairage = cle === 'aucun' ? null : cle;
		cuireMasques(p);
		majInterface(); memoriser(); demanderRendu();
	}
	document.querySelectorAll('[data-eclairage]').forEach((b) => b.addEventListener('click', () => appliquerEclairage(b.dataset.eclairage)));

	/* Teintes : sRGB et HSV, pour viser la teinte d'un teint sans toucher a la carnation. */
	const srgb = (c) => { c = Math.max(0, c); return c <= 0.0031308 ? c * 12.92 : 1.055 * Math.pow(c, 1 / 2.4) - 0.055; };
	function versHsv(c) {
		const mx = Math.max(c[0], c[1], c[2]), mn = Math.min(c[0], c[1], c[2]), d = mx - mn;
		let h = 0;
		if (d > 1e-6) { h = mx === c[0] ? ((c[1] - c[2]) / d + 6) % 6 : (mx === c[1] ? (c[2] - c[0]) / d + 2 : (c[0] - c[1]) / d + 4); }
		return [h * 60, mx ? d / mx : 0, mx];
	}
	function depuisHsv(h, s, v) {
		const k = (n) => (n + h / 60) % 6;
		const f = (n) => v - v * s * Math.max(0, Math.min(k(n), 4 - k(n), 1));
		return [f(5), f(3), f(1)];
	}
	/* La couleur lineaire c, avec la teinte h (degres) : sa saturation et sa valeur gardees, ou multipliees. */
	function teinteVers(c, h, sat, val) {
		const hsv = versHsv(c.map(srgb));
		return depuisHsv(h, Math.min(1, hsv[1] * (sat || 1)), Math.min(1, hsv[2] * (val || 1))).map(lin).map((x) => Math.max(1e-4, x));
	}

	/* Le teint : saturation et luminosite de la peau posees, puis temperature et
	   teinte de la peau cherchees en quelques passes sur le rendu reel, jusqu'a
	   la teinte visee (comme Studio UAZ). */
	async function appliquerTeint(cle) {
		const p = active, T = TEINTS[cle];
		if (!p || !T) { return; }
		const ok = await assurerDetourage(p);
		if (p !== active) { return; }
		if (!ok || !p.seg) { toast('warning', "Aucune peau n'a été trouvée sur cette photo : le teint se règle sur la peau du visage détectée."); return; }
		const r = p.reglages;
		r.peauSaturation = T.sat; r.peauLuminosite = T.lum; r.peauTemperature = 0; r.peauTeinte = 0;
		let trouve = false;
		for (let k = 0; k < 4; k++) {
			const rendu = peauRendue(p, r);
			if (!rendu) { break; }
			trouve = true;
			const d = balanceVers(rendu, teinteVers(rendu, T.h));
			r.peauTemperature = Math.round(borne(r.peauTemperature + d.temperature, -100, 100));
			r.peauTeinte = Math.round(borne(r.peauTeinte + d.teinte, -100, 100));
			if (Math.abs(d.temperature) <= 1 && Math.abs(d.teinte) <= 1) { break; }
		}
		if (!trouve) { toast('warning', "Le visage est trop petit sur cette photo pour mesurer la peau : le teint n'a pas été changé."); }
		r.teint = trouve ? cle : null;
		majInterface(); memoriser(); demanderRendu();
	}
	document.querySelectorAll('[data-teint]').forEach((b) => b.addEventListener('click', () => appliquerTeint(b.dataset.teint)));

	/* Les pastilles du teint montrent la peau de la photo (mesuree au
	   detourage) dans chaque teinte ; avant, une peau moyenne. */
	const PEAU_TYPE = [0.42, 0.19, 0.11];
	function majPortrait() {
		const r = active ? active.reglages : null;
		document.querySelectorAll('[data-eclairage]').forEach((b) => b.setAttribute('aria-pressed', String(!!r && (r.eclairage || 'aucun') === b.dataset.eclairage)));
		const peau = active && active.mesures && active.mesures.peau ? active.mesures.peau : PEAU_TYPE;
		document.querySelectorAll('[data-teint]').forEach((b) => {
			const T = TEINTS[b.dataset.teint];
			const c = teinteVers(peau, T.h, 1 + T.sat / 100, Math.pow(2, T.lum / 100 * 0.6)).map(srgb);
			b.querySelector('.rp-teint__pastille').style.background = 'rgb(' + c.map((x) => Math.round(borne(x, 0, 1) * 255)).join(' ') + ')';
			b.setAttribute('aria-pressed', String(!!r && r.teint === b.dataset.teint));
		});
	}

	/* --- Poignees des degrades (SVG pose sur la scene) ---------------------------- */
	const SVG = 'http://www.w3.org/2000/svg';
	function svgEl(nom, attrs, parent) {
		const n = document.createElementNS(SVG, nom);
		Object.keys(attrs || {}).forEach((k) => n.setAttribute(k, attrs[k]));
		if (parent) { parent.appendChild(n); }
		return n;
	}
	const poignees = { type: null, traits: el.poignees.querySelector('.rp-poignees__traits'), points: el.poignees.querySelector('.rp-poignees__points'), clip: el.poignees.querySelector('#rpPoigneesClip rect'), e: {} };
	function poignee(cle, titre) {
		const gp = svgEl('g', { class: 'rp-poignee rp-poignee--' + cle.replace(/[+-]/, ''), 'data-p': cle }, poignees.points);
		svgEl('circle', { r: 16, class: 'rp-poignee__zone' }, gp);
		svgEl('circle', { r: cle === 'c' ? 7 : 6, class: 'rp-poignee__point' }, gp);
		svgEl('title', {}, gp).textContent = titre;
		return gp;
	}
	function construirePoignees(type) {
		poignees.traits.textContent = ''; poignees.points.textContent = ''; poignees.e = {};
		const E = poignees.e;
		if (type === 'lineaire') {
			E.ligneA = svgEl('line', { class: 'rp-poignees__trait' }, poignees.traits);
			E.ligneB = svgEl('line', { class: 'rp-poignees__trait rp-poignees__trait--fin' }, poignees.traits);
			E.axe = svgEl('line', { class: 'rp-poignees__axe' }, poignees.traits);
			E.a = poignee('a', 'Plein effet : glisser pour déplacer');
			E.b = poignee('b', 'Aucun effet : glisser pour déplacer');
			E.c = poignee('c', 'Glisser pour déplacer tout le dégradé');
		} else {
			E.contour = svgEl('path', { class: 'rp-poignees__trait' }, poignees.traits);
			E.plume = svgEl('path', { class: 'rp-poignees__trait rp-poignees__trait--fin' }, poignees.traits);
			E.c = poignee('c', 'Glisser pour déplacer le cercle');
			['x+', 'x-'].forEach((k) => { E[k] = poignee(k, 'Glisser pour élargir et tourner'); });
			['y+', 'y-'].forEach((k) => { E[k] = poignee(k, 'Glisser pour allonger'); });
		}
		poignees.type = type;
	}
	const placer = (n, p) => n.setAttribute('transform', 'translate(' + p.x.toFixed(1) + ' ' + p.y.toFixed(1) + ')');
	const ligne = (n, a, b) => { n.setAttribute('x1', a.x); n.setAttribute('y1', a.y); n.setAttribute('x2', b.x); n.setAttribute('y2', b.y); };
	function pointEllipse(g, rx, ry, th) {
		const ca = Math.cos(g.angle || 0), sa = Math.sin(g.angle || 0);
		const lx = rx * Math.cos(th), ly = ry * Math.sin(th);
		return versEcran(g.cx + (lx * ca - ly * sa) / ratioSource(), g.cy + (lx * sa + ly * ca));
	}
	function cheminEllipse(g, k) {
		let d = '';
		for (let i = 0; i <= 64; i++) {
			const p = pointEllipse(g, g.rx * k, g.ry * k, i / 64 * Math.PI * 2);
			d += (i ? 'L' : 'M') + p.x.toFixed(1) + ' ' + p.y.toFixed(1);
		}
		return d + 'Z';
	}
	function majPoignees() {
		const m = etat.mode === 'masque' ? masqueChoisi() : null;
		const montrer = !!(m && TYPES_MASQUE[m.type].degrade && m.geo && etat.geo && pinceauMasque.mode === 'non');
		el.poignees.style.display = montrer ? '' : 'none';
		if (!montrer) { return; }
		const g = etat.geo, d = g.dpr;
		poignees.clip.setAttribute('x', g.x / d); poignees.clip.setAttribute('y', g.y / d);
		poignees.clip.setAttribute('width', g.l / d); poignees.clip.setAttribute('height', g.h / d);
		if (poignees.type !== m.type) { construirePoignees(m.type); }
		const E = poignees.e, geo = m.geo;
		if (m.type === 'lineaire') {
			const A = versEcran(geo.ax, geo.ay), B = versEcran(geo.bx, geo.by);
			const lg = Math.hypot(B.x - A.x, B.y - A.y) || 1, nx = -(B.y - A.y) / lg * 5000, ny = (B.x - A.x) / lg * 5000;
			ligne(E.ligneA, { x: A.x - nx, y: A.y - ny }, { x: A.x + nx, y: A.y + ny });
			ligne(E.ligneB, { x: B.x - nx, y: B.y - ny }, { x: B.x + nx, y: B.y + ny });
			ligne(E.axe, A, B);
			placer(E.a, A); placer(E.b, B); placer(E.c, { x: (A.x + B.x) / 2, y: (A.y + B.y) / 2 });
		} else {
			E.contour.setAttribute('d', cheminEllipse(geo, 1));
			E.plume.setAttribute('d', cheminEllipse(geo, Math.max(0.02, 1 - (geo.plume == null ? 50 : geo.plume) / 100)));
			placer(E.c, versEcran(geo.cx, geo.cy));
			placer(E['x+'], pointEllipse(geo, geo.rx, geo.ry, 0));
			placer(E['x-'], pointEllipse(geo, geo.rx, geo.ry, Math.PI));
			placer(E['y+'], pointEllipse(geo, geo.rx, geo.ry, Math.PI / 2));
			placer(E['y-'], pointEllipse(geo, geo.rx, geo.ry, Math.PI * 1.5));
		}
	}
	el.poignees.addEventListener('pointerdown', (e) => {
		const h = e.target.closest('.rp-poignee'), m = masqueChoisi();
		if (!h || !m || !m.geo) { return; }
		e.preventDefault(); e.stopPropagation();
		e.target.setPointerCapture(e.pointerId);
		const cle = h.dataset.p, depart = { q: versImage(e.clientX, e.clientY).q, geo: Object.assign({}, m.geo) };
		h.classList.add('est-tenue');
		const bouger = (ev) => {
			const q = versImage(ev.clientX, ev.clientY).q, g = m.geo, dx = q.x - depart.q.x, dy = q.y - depart.q.y;
			if (m.type === 'lineaire') {
				if (cle === 'a') { g.ax = q.x; g.ay = q.y; }
				else if (cle === 'b') { g.bx = q.x; g.by = q.y; }
				else { g.ax = depart.geo.ax + dx; g.ay = depart.geo.ay + dy; g.bx = depart.geo.bx + dx; g.by = depart.geo.by + dy; }
			} else if (cle === 'c') { g.cx = depart.geo.cx + dx; g.cy = depart.geo.cy + dy; }
			else {
				const vx = (q.x - g.cx) * ratioSource(), vy = q.y - g.cy;
				if (cle[0] === 'x') { g.rx = Math.max(0.01, Math.hypot(vx, vy)); g.angle = Math.atan2(vy, vx) + (cle === 'x-' ? Math.PI : 0); }
				else { const a = g.angle || 0; g.ry = Math.max(0.01, Math.abs(-Math.sin(a) * vx + Math.cos(a) * vy)); }
			}
			demanderRendu();
		};
		const fin = () => {
			h.classList.remove('est-tenue');
			e.target.removeEventListener('pointermove', bouger);
			e.target.removeEventListener('pointerup', fin);
			e.target.removeEventListener('pointercancel', fin);
			memoriser();
		};
		e.target.addEventListener('pointermove', bouger);
		e.target.addEventListener('pointerup', fin);
		e.target.addEventListener('pointercancel', fin);
	});

	/* --- Gestes sur la scene ------------------------------------------------------ */
	scene.addEventListener('pointerdown', (e) => {
		if (!active || e.button > 0 || e.target === el.cadre || el.cadre.contains(e.target)) { return; }
		if (e.target.closest('.rp-flottant, .rp-coupure, .rp-poignee')) { return; }
		const g = etat.geo;
		if (!g) { return; }
		if (etat.pipette) {
			const { q } = versImage(e.clientX, e.clientY);
			if (q.x < 0 || q.y < 0 || q.x > 1 || q.y > 1 || !active.t) { return; }
			const t = active.t, x = Math.round(q.x * t.w), y = Math.round(q.y * t.h);
			const c = moyenneImage(t, x - 2, y - 2, x + 3, y + 3);
			if (c) {
				Object.assign(active.reglages, balanceVers(c, null));
				majInterface(); memoriser(); demanderRendu();
			}
			basculerPipette(false);
			return;
		}
		if (etat.mode === 'pinceau') {
			if (!active.seg || !active.retouches) { toast('info', 'Le détourage se prépare : le pinceau sera prêt dans un instant.'); return; }
			e.preventDefault();
			scene.setPointerCapture(e.pointerId);
			active.retouches = active.retouches.slice(); // l'historique garde l'ancien masque
			tracerAuPinceau(e, (q, ajouter) => peindre(q.x, q.y, ajouter), () => pinceau.mode, () => memoriser());
			return;
		}
		if (etat.mode === 'masque' && masqueChoisi() && active.t) {
			const m = masqueChoisi(), i = indexMasqueChoisi();
			if (pinceauMasque.mode !== 'non') {
				e.preventDefault();
				scene.setPointerCapture(e.pointerId);
				// l'historique garde l'ancien pinceau du masque
				active.calques = Object.assign({}, active.calques);
				const cal = active.calques[m.id] = active.calques[m.id] ? active.calques[m.id].slice() : new Uint8Array(active.t.w * active.t.h).fill(128);
				tracerAuPinceau(e, (q, ajouter) => {
					const lignes = tamponner(cal, q.x, q.y, ajouter);
					if (lignes) { cuireLignes(active, i, lignes[0], lignes[1]); }
				}, () => pinceauMasque.mode, () => { memoriser(); majListeMasques(); });
				return;
			}
			if (TYPES_MASQUE[m.type].degrade) { tracerDegrade(e, m); return; }
		}
		if (etat.zoom != null && etat.mode !== 'recadrer') {
			e.preventDefault();
			scene.setPointerCapture(e.pointerId);
			scene.classList.add('rp-scene--glisse');
			const depart = { x: e.clientX, y: e.clientY, c: Object.assign({}, etat.centre) };
			const glisser = (ev) => {
				etat.centre = { x: depart.c.x - (ev.clientX - depart.x) * g.dpr / g.l, y: depart.c.y - (ev.clientY - depart.y) * g.dpr / g.h };
				demanderRendu();
			};
			const fin = () => {
				scene.classList.remove('rp-scene--glisse');
				scene.removeEventListener('pointermove', glisser);
				scene.removeEventListener('pointerup', fin);
			};
			scene.addEventListener('pointermove', glisser);
			scene.addEventListener('pointerup', fin);
		}
	});
	scene.addEventListener('pointermove', (e) => {
		if (e.buttons !== 0) { return; } // en plein trait, tracerAuPinceau place le cercle
		const pinceauActif = etat.mode === 'pinceau' || (etat.mode === 'masque' && pinceauMasque.mode !== 'non');
		if (pinceauActif && !e.target.closest('.rp-flottant, .rp-poignee')) { placerCurseurPinceau(e, e.altKey); }
		else { el.curseur.hidden = true; }
	});
	/* La zone du masque en rouge : quand la souris est sur la photo. Dans le
	   panneau, on voit l'effet des reglages, sans le rouge. */
	scene.addEventListener('pointerenter', () => { etat.survol = true; if (etat.mode === 'masque') { demanderRendu(); } });
	scene.addEventListener('pointerleave', () => {
		el.curseur.hidden = true;
		etat.survol = false;
		if (etat.mode === 'masque') { demanderRendu(); }
	});
	scene.addEventListener('dblclick', (e) => {
		if (!active || etat.mode || etat.pipette || e.target.closest('.rp-flottant, .rp-coupure')) { return; }
		if (etat.zoom == null) { zoomer(1 / etat.geo.z, e.clientX, e.clientY); } else { etat.zoom = null; majCurseurScene(); demanderRendu(); }
	});

	// La coupure avant / apres se deplace a la souris ou au doigt
	el.coupure.addEventListener('pointerdown', (e) => {
		e.preventDefault(); e.stopPropagation();
		el.coupure.setPointerCapture(e.pointerId);
		const g = etat.geo;
		const bouger = (ev) => {
			const b = canvas.getBoundingClientRect();
			etat.avant = borne(((ev.clientX - b.left) * g.dpr - g.x) / g.l, 0, 1);
			demanderRendu();
		};
		const fin = () => { el.coupure.removeEventListener('pointermove', bouger); el.coupure.removeEventListener('pointerup', fin); };
		el.coupure.addEventListener('pointermove', bouger);
		el.coupure.addEventListener('pointerup', fin);
	});

	/* ======================================================================
	   Ajout de photos : bouton, glisser-deposer
	   ====================================================================== */
	$('rpAjouter').addEventListener('click', () => el.fichiers.click());
	$('rpChoisir').addEventListener('click', () => el.fichiers.click());
	el.fichiers.addEventListener('change', () => { const f = Array.from(el.fichiers.files || []); el.fichiers.value = ''; ajouterFichiers(f); });
	let profondeurDepot = 0;
	app.addEventListener('dragenter', (e) => { if (Array.from(e.dataTransfer.types || []).includes('Files')) { e.preventDefault(); profondeurDepot++; el.depot.hidden = false; } });
	app.addEventListener('dragover', (e) => { if (Array.from(e.dataTransfer.types || []).includes('Files')) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; } });
	app.addEventListener('dragleave', () => { profondeurDepot = Math.max(0, profondeurDepot - 1); if (!profondeurDepot) { el.depot.hidden = true; } });
	app.addEventListener('drop', (e) => {
		e.preventDefault(); profondeurDepot = 0; el.depot.hidden = true;
		ajouterFichiers(e.dataTransfer.files);
	});

	/* ======================================================================
	   Plusieurs photos : copier les reglages, Studio UAZ sur toutes
	   ====================================================================== */
	$('rpSynchro').addEventListener('click', () => {
		if (!active || photos.length < 2) { return; }
		const modele = sansGeometrie(active.reglages);
		// un masque peint ne vaut que pour sa photo ; les autres (personne, degrades) se copient
		modele.masques = (modele.masques || []).filter((m) => m.type !== 'pinceau' && m.origine !== 'eclairage');
		// l'eclairage studio est pose sur le visage de chaque photo : il ne se copie pas
		delete modele.eclairage;
		photos.forEach((p) => {
			if (p === active) { return; }
			const g = { cadre: p.reglages.cadre, rotation: p.reglages.rotation, miroir: p.reglages.miroir, ratio: p.reglages.ratio, perso: persoDe(p.reglages) };
			// chacune garde son eclairage studio, pose sur son visage
			const eclairage = (p.reglages.masques || []).filter((m) => m.origine === 'eclairage');
			Object.assign(g, { eclairage: eclairage.length ? p.reglages.eclairage : null });
			p.reglages = Object.assign(fusionner(reglagesDefaut(), modele), g);
			p.reglages.masques = (p.reglages.masques || []).concat(eclairage).slice(-MASQUES_MAX);
			memoriser(p);
		});
		toast('success', 'Réglages copiés sur ' + (photos.length - 1) + ' photo' + (photos.length > 2 ? 's' : '') + ' (le recadrage et l\'éclairage studio de chacune sont gardés).');
	});

	$('rpStudioTout').addEventListener('click', async () => {
		if (photos.length < 2 || etat.occupe) { return; }
		etat.occupe = true;
		const depart = active;
		const opts = { fond: $('rpStudioFond').checked, cadre: $('rpStudioCadre').checked };
		let sansPersonne = 0;
		try {
			for (let i = 0; i < photos.length; i++) {
				const p = photos[i];
				afficherEtat('Studio UAZ : photo ' + (i + 1) + ' sur ' + photos.length + '…');
				await activer(p);
				const res = await appliquerStudio(p, opts);
				if (!res.personne) { sansPersonne++; }
				chargerImageFond(p.reglages);
				majInterface(); rendre();
				planifierVignette(p);
				await new Promise((r) => setTimeout(r, 480));
			}
		} finally {
			cacherEtat();
			etat.occupe = false;
			if (depart) { await activer(depart); }
		}
		toast(sansPersonne ? 'warning' : 'success', 'Studio UAZ appliqué à ' + photos.length + ' photos' + (sansPersonne ? ' ; ' + sansPersonne + ' sans personne détectée gardent leur fond.' : '.'));
	});

	/* ======================================================================
	   Filigrane de l'export
	   Comme dans Lightroom : un texte ou un logo, pose sur une grille 3 x 3
	   (ou repete en mosaique), large d'une part de la photo. Le meme pour
	   toutes les photos : il est enregistre avec l'atelier, et le logo choisi
	   avec les images de fond (id « filigrane »), sur cet appareil et sur le
	   serveur. Il se voit sur la photo pendant la retouche et n'entre que
	   dans le fichier exporte.
	   ====================================================================== */
	const FILIGRANE_DEFAUT = {
		actif: false, type: 'texte', texte: '© Université Adventiste Zurcher', police: 'sans', gras: true, italique: false,
		couleur: '#ffffff', ombre: true, logo: 'universite', teinte: 'origine',
		position: 'bd', taille: 30, tailleLogo: 16, opacite: 70, marge: 3, espacement: 40, angle: 0,
	};
	const POLICES = {
		sans: '"Inter", system-ui, "Segoe UI", Roboto, sans-serif',
		serif: 'Georgia, "Times New Roman", serif',
		mono: '"JetBrains Mono", ui-monospace, Consolas, monospace',
	};
	const ANCRES = ['hg', 'hc', 'hd', 'cg', 'cc', 'cd', 'bg', 'bc', 'bd', 'mosaique'];
	const filigrane = Object.assign({}, FILIGRANE_DEFAUT);
	const logosFiligrane = { universite: null, perso: null };   // { image, nom, fichier, url }
	const fil = {
		actif: $('rpFiligraneActif'), bloc: $('rpFiligrane'), vide: $('rpFiligraneVide'), texte: $('rpFiligraneTexte'),
		police: $('rpFiligranePolice'), gras: $('rpFiligraneGras'), italique: $('rpFiligraneItalique'), couleur: $('rpFiligraneCouleur'),
		ombre: $('rpFiligraneOmbre'), fichier: $('rpFiligraneFichier'), logoVue: $('rpFiligraneLogoVue'), logoNom: $('rpFiligraneLogoNom'),
		apercu: $('rpFiligraneApercu'),
	};
	const AIDE_LOGO = fil.logoNom.textContent;

	const policeFiligrane = (f, px) => (f.italique ? 'italic ' : '') + (f.gras ? '700 ' : '400 ') + px.toFixed(2) + 'px ' + (POLICES[f.police] || POLICES.sans);
	const cleTaille = () => (filigrane.type === 'logo' ? 'tailleLogo' : 'taille');
	const imageLogo = () => { const l = logosFiligrane[filigrane.logo]; return l ? l.image : null; };

	let logoUniversite = null;
	function chargerLogoUniversite() {
		if (!logoUniversite) {
			logoUniversite = fetch(app.dataset.logo, { credentials: 'same-origin' })
				.then((r) => { if (!r.ok) { throw new Error('Logo ' + r.status); } return r.blob(); })
				.then((b) => createImageBitmap(b))
				.then((image) => { logosFiligrane.universite = { image, nom: "Logo de l'université" }; majApercuFiligrane(true); return image; })
				.catch((e) => { logoUniversite = null; throw e; });
		}
		return logoUniversite;
	}
	/* Ce qu'il faut avant de dessiner : le logo choisi, la police du texte. */
	async function pretFiligrane() {
		if (filigrane.type === 'logo') {
			if (filigrane.logo === 'universite' && !logosFiligrane.universite) { await chargerLogoUniversite(); }
			if (!imageLogo()) { throw new Error('logo absent'); }
		} else if (document.fonts && document.fonts.load) {
			await document.fonts.load(policeFiligrane(filigrane, 40), filigrane.texte || 'A').catch(() => {});
		}
	}

	/* Logo en blanc ou en noir, comme un tampon : ce qui tranche sur le fond du
	   logo devient l'encre, le reste s'efface. Le sceau de l'universite garde
	   ainsi ses lettres (en creux) au lieu de devenir un disque plein ; un logo
	   d'une seule couleur garde sa forme entiere. Garde en cache. */
	let logoTeinte = { source: null, teinte: null, cv: null };
	function logoPourTeinte(image, teinte) {
		if (teinte !== 'blanc' && teinte !== 'noir') { return image; }
		if (logoTeinte.source === image && logoTeinte.teinte === teinte) { return logoTeinte.cv; }
		const cv = document.createElement('canvas');
		cv.width = image.width; cv.height = image.height;
		const g = cv.getContext('2d', { willReadFrequently: true });
		g.drawImage(image, 0, 0);
		const d = g.getImageData(0, 0, cv.width, cv.height), px = d.data, W = cv.width, H = cv.height;
		const lum = (i) => 0.2126 * px[i] + 0.7152 * px[i + 1] + 0.0722 * px[i + 2];
		// clartes de la partie visible du logo (5e et 95e centiles, moyenne)
		const hist = new Uint32Array(256);
		let n = 0, somme = 0;
		for (let i = 0; i < px.length; i += 4) {
			if (px[i + 3] < 128) { continue; }
			const l = Math.round(lum(i)); hist[l]++; n++; somme += l;
		}
		const centile = (q) => { let k = 0; for (let l = 0; l < 256; l++) { k += hist[l]; if (k >= q * n) { return l; } } return 255; };
		const bas = centile(0.05), haut = centile(0.95), ecart = haut - bas;
		// le fond du logo : ses bords s'ils sont pleins (JPEG), sinon sa teinte d'ensemble
		let nb = 0, sb = 0;
		const bord = (x, y) => { const i = (y * W + x) * 4; if (px[i + 3] >= 128) { nb++; sb += lum(i); } };
		for (let x = 0; x < W; x++) { bord(x, 0); bord(x, H - 1); }
		for (let y = 0; y < H; y++) { bord(0, y); bord(W - 1, y); }
		const encreClaire = nb > (W + H) * 0.2 ? sb / nb < 128 : n > 0 && somme / n > 0.6 * 255;
		const [r, v, b] = teinte === 'blanc' ? [255, 255, 255] : [20, 20, 22];
		for (let i = 0; i < px.length; i += 4) {
			let encre = 1;
			if (ecart > 38) {
				const l = lum(i);
				encre = Math.min(1, Math.max(0, encreClaire ? (l - bas) / ecart : (haut - l) / ecart));
			}
			px[i] = r; px[i + 1] = v; px[i + 2] = b; px[i + 3] = Math.round(px[i + 3] * encre);
		}
		g.putImageData(d, 0, 0);
		logoTeinte = { source: image, teinte, cv };
		return cv;
	}
	function luminance(hex) {
		const n = parseInt(String(hex).slice(1), 16);
		return Number.isFinite(n) ? (0.2126 * (n >> 16 & 255) + 0.7152 * (n >> 8 & 255) + 0.0722 * (n & 255)) / 255 : 1;
	}

	/* Dessine le filigrane f dans le rectangle (x0, y0, L, H) de la photo : le
	   fichier exporte, ou la photo telle qu'affichee. Taille, marge et
	   espacement se mesurent en proportion de la photo : l'apercu est fidele
	   a l'export, quelle que soit sa taille. */
	function dessinerFiligrane(g, x0, y0, L, H, f, logo) {
		const vise = L * (f.type === 'logo' ? f.tailleLogo : f.taille) / 100;
		let lw, lh, echelle, motif;
		if (f.type === 'logo') {
			if (!logo) { return; }
			const img = logoPourTeinte(logo, f.teinte);
			const s = Math.min(vise / img.width, H * 0.95 / img.height);
			lw = img.width * s; lh = img.height * s; echelle = Math.min(lw, lh) * 0.22;
			motif = () => g.drawImage(img, -lw / 2, -lh / 2, lw, lh);
		} else {
			const lignes = String(f.texte || '').split('\n').map((t) => t.trim()).filter(Boolean);
			if (!lignes.length) { return; }
			const interligne = 1.18;
			g.font = policeFiligrane(f, 100);
			const large = Math.max(...lignes.map((t) => g.measureText(t).width)) || 1;
			const px = Math.max(0.5, Math.min(100 * vise / large, H * 0.95 / (interligne * lignes.length)));
			g.font = policeFiligrane(f, px);
			lw = Math.max(...lignes.map((t) => g.measureText(t).width)); lh = px * interligne * lignes.length; echelle = px;
			// les lignes s'alignent sur le bord le plus proche : a gauche dans les coins de gauche...
			const cote = f.position === 'mosaique' ? 'c' : f.position[1];
			const align = cote === 'g' ? 'left' : (cote === 'd' ? 'right' : 'center');
			const x = align === 'left' ? -lw / 2 : (align === 'right' ? lw / 2 : 0);
			motif = () => {
				g.fillStyle = f.couleur; g.textAlign = align; g.textBaseline = 'middle';
				lignes.forEach((t, i) => g.fillText(t, x, (i - (lignes.length - 1) / 2) * px * interligne));
			};
		}
		const a = (f.angle || 0) * Math.PI / 180, cos = Math.abs(Math.cos(a)), sin = Math.abs(Math.sin(a));
		g.save();
		g.beginPath(); g.rect(x0, y0, L, H); g.clip();
		g.globalAlpha = Math.max(0, Math.min(1, f.opacite / 100));
		if (f.ombre) {
			// ombre sombre sous un filigrane clair, halo clair sous un filigrane sombre
			const sombre = f.type === 'logo' ? f.teinte === 'noir' : luminance(f.couleur) < 0.45;
			g.shadowColor = sombre ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)';
			g.shadowBlur = Math.max(1, echelle * 0.16);
			g.shadowOffsetY = echelle * 0.04;
		}
		if (f.position === 'mosaique') {
			// en quinconce, sur toute la photo, dans le sens de l'angle
			const ecart = Math.max(lw, lh) * (f.espacement / 100) * 0.6 + Math.min(lw, lh) * 0.35;
			let px = lw + ecart, py = lh + ecart;
			const R = Math.hypot(L, H) / 2 + Math.max(lw, lh);
			while ((2 * R / px + 2) * (2 * R / py + 2) > 2500) { px *= 1.25; py *= 1.25; }
			const ni = Math.ceil(R / px) + 1, nj = Math.ceil(R / py) + 1;
			g.translate(x0 + L / 2, y0 + H / 2);
			g.rotate(-a);
			for (let j = -nj; j <= nj; j++) {
				for (let i = -ni; i <= ni; i++) {
					g.save(); g.translate(i * px + (j & 1 ? px / 2 : 0), j * py); motif(); g.restore();
				}
			}
		} else {
			const bw = lw * cos + lh * sin, bh = lw * sin + lh * cos;   // encombrement une fois tourne
			const m = Math.min(L, H) * f.marge / 100;
			const lig = f.position[0], col = f.position[1];
			const cx = col === 'g' ? m + bw / 2 : (col === 'd' ? L - m - bw / 2 : L / 2);
			const cy = lig === 'h' ? m + bh / 2 : (lig === 'b' ? H - m - bh / 2 : H / 2);
			g.translate(x0 + cx, y0 + cy);
			g.rotate(-a);
			motif();
		}
		g.restore();
	}

	/* Apercu par-dessus la photo affichee (hors recadrage, pinceau et masques,
	   ou il generait le travail) ; redessine seulement quand il change. */
	let signatureApercu = '';
	function majApercuFiligrane(forcer) {
		const g = etat.geo, cv = fil.apercu;
		const montrer = !!(filigrane.actif && active && g && !etat.mode);
		cv.hidden = !montrer;
		if (!montrer) { signatureApercu = ''; return; }
		const logo = filigrane.type === 'logo' ? imageLogo() : null;
		if (filigrane.type === 'logo' && !logo && filigrane.logo === 'universite') { chargerLogoUniversite().catch(() => {}); }
		const sig = JSON.stringify(filigrane) + '|' + [g.cw, g.ch, g.x, g.y, g.l, g.h].map(Math.round).join(',') + '|' + !!logo;
		if (!forcer && sig === signatureApercu) { return; }
		signatureApercu = sig;
		if (cv.width !== g.cw || cv.height !== g.ch) { cv.width = g.cw; cv.height = g.ch; }
		const c = cv.getContext('2d');
		c.clearRect(0, 0, cv.width, cv.height);
		dessinerFiligrane(c, g.x, g.y, g.l, g.h, filigrane, logo);
		if (filigrane.type === 'texte' && document.fonts && !document.fonts.check(policeFiligrane(filigrane, 20))) {
			document.fonts.load(policeFiligrane(filigrane, 20)).then(() => majApercuFiligrane(true)).catch(() => {});
		}
	}

	/* --- Le volet ---------------------------------------------------------- */
	const curseursFiligrane = Array.from(document.querySelectorAll('.rp-curseur[data-filigrane]'));
	const cleCurseur = (bloc) => (bloc.dataset.filigrane === 'taille' ? cleTaille() : bloc.dataset.filigrane);

	function majFiligraneUI() {
		const f = filigrane;
		fil.actif.checked = f.actif;
		fil.bloc.hidden = !f.actif;
		fil.vide.hidden = f.actif;
		document.querySelectorAll('input[name="rpFiligraneType"]').forEach((i) => { i.checked = i.value === f.type; });
		document.querySelectorAll('[data-pour-filigrane]').forEach((s) => { s.hidden = s.dataset.pourFiligrane !== f.type; });
		if (document.activeElement !== fil.texte) { fil.texte.value = f.texte; }
		fil.police.value = f.police;
		fil.gras.setAttribute('aria-pressed', String(f.gras));
		fil.italique.setAttribute('aria-pressed', String(f.italique));
		document.querySelectorAll('[data-couleur-filigrane]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.couleurFiligrane === f.couleur)));
		fil.couleur.value = f.couleur;
		fil.ombre.checked = f.ombre;
		document.querySelectorAll('.rp-logo-choix').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.logo === f.logo)));
		document.querySelectorAll('input[name="rpFiligraneTeinte"]').forEach((i) => { i.checked = i.value === f.teinte; });
		document.querySelectorAll('#rpFiligrane [data-position]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.position === f.position)));
		curseursFiligrane.forEach((bloc) => {
			const cle = cleCurseur(bloc), v = f[cle];
			const piste = bloc.querySelector('input[type="range"]'), champ = bloc.querySelector('.rp-curseur__valeur');
			piste.value = v;
			if (document.activeElement !== champ) { champ.value = parseFloat(piste.step) < 1 ? Number(v).toFixed(1).replace(/\.0$/, '') : Math.round(v); }
			bloc.classList.toggle('rp-curseur--modifie', v !== FILIGRANE_DEFAUT[cle]);
			if (bloc.dataset.filigrane === 'marge') { bloc.hidden = f.position === 'mosaique' || f.position === 'cc'; }
			if (bloc.dataset.filigrane === 'espacement') { bloc.hidden = f.position !== 'mosaique'; }
		});
		const perso = logosFiligrane.perso;
		if (perso) { fil.logoVue.style.setProperty('--rp-logo', 'url("' + perso.url + '")'); } else { fil.logoVue.style.removeProperty('--rp-logo'); }
		fil.logoVue.classList.toggle('rp-logo-choix__vue--vide', !perso);
		fil.logoVue.parentNode.title = perso ? 'Votre image ; cliquez de nouveau pour en choisir une autre' : 'Choisir une image sur cet appareil';
		fil.logoNom.textContent = f.logo === 'perso' && perso ? perso.nom : AIDE_LOGO;
	}

	function changerFiligrane(modif) {
		Object.assign(filigrane, modif);
		majFiligraneUI();
		majApercuFiligrane();
		majExport();
		planifierSauvegarde(null);
		if (filigrane.actif && filigrane.type === 'logo' && filigrane.logo === 'universite' && !logosFiligrane.universite) {
			chargerLogoUniversite().catch(() => toast('warning', "Le logo de l'université ne se charge pas : vérifiez la connexion, ou choisissez votre propre image.", 9000));
		}
	}

	/* Repris de la sauvegarde : seules les cles connues, du bon type. */
	function reprendreFiligrane(f) {
		if (!f || typeof f !== 'object') { return; }
		Object.keys(FILIGRANE_DEFAUT).forEach((k) => { if (typeof f[k] === typeof FILIGRANE_DEFAUT[k]) { filigrane[k] = f[k]; } });
		if (!ANCRES.includes(filigrane.position)) { filigrane.position = FILIGRANE_DEFAUT.position; }
		if (filigrane.logo === 'perso' && !logosFiligrane.perso) { filigrane.logo = 'universite'; }
		majFiligraneUI();
		majApercuFiligrane(true);
	}

	function definirLogoPerso(fichier, nom, image) {
		const ancien = logosFiligrane.perso;
		if (ancien) { try { ancien.image.close(); } catch (e) { /* deja libere */ } URL.revokeObjectURL(ancien.url); }
		logosFiligrane.perso = { image, nom, fichier, url: URL.createObjectURL(fichier) };
	}
	function sauverLogoFiligrane() {
		const l = logosFiligrane.perso;
		if (!base || !l) { return; }
		ecritures = ecritures.then(() => base.ecrire({ fonds: [{ id: 'filigrane', nom: l.nom, fichier: l.fichier }] })).catch(echecSauvegarde);
	}
	async function ouvrirLogo(f) {
		let image = await createImageBitmap(f);
		const M = 2048, grand = Math.max(image.width, image.height);
		if (grand > M) {
			const s = M / grand;
			const petite = await createImageBitmap(f, { resizeWidth: Math.round(image.width * s), resizeHeight: Math.round(image.height * s), resizeQuality: 'high' });
			image.close(); image = petite;
		}
		return image;
	}

	fil.actif.addEventListener('change', () => changerFiligrane({ actif: fil.actif.checked }));
	document.querySelectorAll('input[name="rpFiligraneType"]').forEach((i) => i.addEventListener('change', () => { if (i.checked) { changerFiligrane({ type: i.value }); } }));
	fil.texte.addEventListener('input', () => changerFiligrane({ texte: fil.texte.value.slice(0, 160) }));
	fil.police.addEventListener('change', () => changerFiligrane({ police: fil.police.value }));
	fil.gras.addEventListener('click', () => changerFiligrane({ gras: !filigrane.gras }));
	fil.italique.addEventListener('click', () => changerFiligrane({ italique: !filigrane.italique }));
	document.querySelectorAll('[data-couleur-filigrane]').forEach((b) => b.addEventListener('click', () => changerFiligrane({ couleur: b.dataset.couleurFiligrane })));
	fil.couleur.addEventListener('input', () => changerFiligrane({ couleur: fil.couleur.value }));
	fil.ombre.addEventListener('change', () => changerFiligrane({ ombre: fil.ombre.checked }));
	document.querySelectorAll('input[name="rpFiligraneTeinte"]').forEach((i) => i.addEventListener('change', () => { if (i.checked) { changerFiligrane({ teinte: i.value }); } }));
	document.querySelectorAll('#rpFiligrane [data-position]').forEach((b) => b.addEventListener('click', () => changerFiligrane({ position: b.dataset.position })));
	document.querySelectorAll('.rp-logo-choix').forEach((b) => b.addEventListener('click', () => {
		// « Mon logo » : choisir l'image la premiere fois, ou une autre quand il est deja pris
		if (b.dataset.logo === 'perso' && (!logosFiligrane.perso || filigrane.logo === 'perso')) { fil.fichier.click(); return; }
		changerFiligrane({ logo: b.dataset.logo });
	}));
	fil.fichier.addEventListener('change', async () => {
		const f = fil.fichier.files && fil.fichier.files[0];
		fil.fichier.value = '';
		if (!f) { return; }
		if (!['image/png', 'image/webp', 'image/jpeg'].includes(f.type)) { toast('warning', 'Le logo doit être une image PNG, WebP ou JPEG.'); return; }
		if (f.size > 10 * 1024 * 1024) { toast('warning', 'Cette image dépasse 10 Mo : pour un logo, une image plus légère suffit.'); return; }
		try {
			definirLogoPerso(f, f.name, await ouvrirLogo(f));
			sauverLogoFiligrane();
			changerFiligrane({ logo: 'perso' });
			majApercuFiligrane(true);
		} catch (e) {
			console.error(e);
			toast('error', '« ' + f.name + " » n'a pas pu être ouverte : le fichier est peut-être abîmé.");
		}
	});
	curseursFiligrane.forEach((bloc) => {
		const piste = bloc.querySelector('input[type="range"]'), champ = bloc.querySelector('.rp-curseur__valeur');
		const borne = (v) => Math.min(parseFloat(piste.max), Math.max(parseFloat(piste.min), v));
		const changer = (v) => changerFiligrane({ [cleCurseur(bloc)]: v });
		const depart = () => changer(FILIGRANE_DEFAUT[cleCurseur(bloc)]);
		piste.addEventListener('input', () => changer(parseFloat(piste.value)));
		champ.addEventListener('change', () => {
			const v = parseFloat(String(champ.value).replace(',', '.'));
			if (Number.isFinite(v)) { changer(borne(v)); } else { depart(); }
		});
		champ.addEventListener('keydown', (e) => { if (e.key === 'Enter') { champ.blur(); } });
		bloc.querySelector('.rp-curseur__nom').addEventListener('dblclick', depart);
		piste.addEventListener('dblclick', depart);
	});
	majFiligraneUI();

	/* ======================================================================
	   Export
	   ====================================================================== */
	const EXTENSIONS = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };
	const MSG_FILIGRANE = "L'export est arrêté : le logo du filigrane ne se charge pas (connexion ?). Réessayez, choisissez votre propre image, ou retirez le filigrane.";
	function nomFichier(base, ext) {
		const propre = String(base || 'photo').normalize('NFC').replace(/[\\/:*?"<>|\u0000-\u001f]+/g, '-').replace(/\s+/g, ' ').trim().slice(0, 120) || 'photo';
		return propre + '.' + ext;
	}

	/* Netteté de sortie : à la fin du rendu, comme dans Lightroom (« netteté pour l'écran »). Un masque flou sur la luminance seule (pas de
	   franges de couleur), au rayon d'un pixel : une photo réduite perd toujours un peu de finesse, plus elle est réduite plus il en faut.
	   Le bruit n'est pas amplifié (seuil) et les contours francs ne font pas de halo (écrêtage). */
	const NETTETE_SORTIE = { aucune: 0, legere: 0.55, standard: 0.85, forte: 1.25 };
	function netteteSortie(g, w, h, force, reduction) {
		if (!force || w < 8 || h < 8) { return; }
		const quantite = force * (0.4 + 0.28 * Math.min(3, Math.max(0, reduction - 1)));
		const img = g.getImageData(0, 0, w, h);
		const d = img.data;
		const n = w * h;
		const Y = new Float32Array(n);
		for (let p = 0, i = 0; p < n; p++, i += 4) { Y[p] = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2]; }
		const B = new Float32Array(n);   // Y flouté à l'horizontale (1-2-1)
		for (let y = 0; y < h; y++) {
			const r = y * w;
			for (let x = 0; x < w; x++) {
				const a = Y[r + (x > 0 ? x - 1 : 0)], c = Y[r + (x < w - 1 ? x + 1 : w - 1)];
				B[r + x] = (a + 2 * Y[r + x] + c) * 0.25;
			}
		}
		const LIMITE = 26, SEUIL = 2.2;
		for (let y = 0; y < h; y++) {
			const r = y * w, ra = (y > 0 ? y - 1 : 0) * w, rb = (y < h - 1 ? y + 1 : h - 1) * w;
			for (let x = 0; x < w; x++) {
				const flou = (B[ra + x] + 2 * B[r + x] + B[rb + x]) * 0.25;
				const det = Y[r + x] - flou;
				const ad = det < 0 ? -det : det;
				let delta = det * quantite * (ad < SEUIL ? ad / SEUIL : 1);
				if (delta > LIMITE) { delta = LIMITE; } else if (delta < -LIMITE) { delta = -LIMITE; }
				const i = (r + x) * 4;
				d[i] += delta; d[i + 1] += delta; d[i + 2] += delta;
			}
		}
		g.putImageData(img, 0, 0);
	}

	/* La photo finale. Options de la photo de profil : o.profil = grand cote
	   au plus, en JPEG, sans filigrane, un fond transparent pose sur du blanc ;
	   o.qualite remplace celle du volet. */
	async function produire(photo, o) {
		o = o || {};
		if (active !== photo) { await activer(photo); }
		if (besoinDetourage(photo.reglages) && !photo.seg) { await assurerDetourage(photo); }
		const r = photo.reglages;
		const { w, h } = dimensionsExport(r, null, o.profil || 0);
		let type = o.profil ? 'image/jpeg' : el.format.value;
		if (!o.profil && r.fond.mode === 'transparent' && type === 'image/jpeg') { type = 'image/png'; }
		let cv = document.createElement('canvas');
		cv.width = w; cv.height = h;
		const g = cv.getContext('2d');
		moteur.rendre(r, w, h, { export: true }, (px, tx, ty, tw, th) => {
			g.putImageData(new ImageData(new Uint8ClampedArray(px.buffer), tw, th), tx, ty);
		});
		demanderRendu();
		{
			const t = moteur.tailleSortie(r);
			const force = o.profil ? NETTETE_SORTIE.standard : (NETTETE_SORTIE[el.netteteSortie ? el.netteteSortie.value : 'standard'] || 0);
			netteteSortie(g, w, h, force, Math.max(1, t.w / w));
		}
		if (filigrane.actif && !o.profil) {
			try { await pretFiligrane(); } catch (e) { throw Object.assign(new Error('filigrane'), { filigrane: true }); }
			dessinerFiligrane(g, 0, 0, w, h, filigrane, filigrane.type === 'logo' ? imageLogo() : null);
		}
		if (o.profil && r.fond.mode === 'transparent') {
			// putImageData remplace les pixels sans les fondre : le blanc se pose dessous, sur une autre toile
			const blanc = document.createElement('canvas');
			blanc.width = w; blanc.height = h;
			const gb = blanc.getContext('2d');
			gb.fillStyle = '#ffffff'; gb.fillRect(0, 0, w, h); gb.drawImage(cv, 0, 0);
			cv = blanc;
		}
		const qualite = o.qualite != null ? o.qualite : parseInt(el.qualite.value, 10) / 100;
		const blob = await new Promise((ok) => cv.toBlob(ok, type, qualite));
		if (!blob) { throw new Error('toBlob'); }
		return { blob, ext: EXTENSIONS[type], w, h };
	}

	function telecharger(blob, nom) {
		const a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = nom;
		document.body.appendChild(a);
		a.click();
		a.remove();
		setTimeout(() => URL.revokeObjectURL(a.href), 4000);
	}

	el.exporter.addEventListener('click', async () => {
		if (!active || etat.occupe) { return; }
		etat.occupe = true;
		el.exporter.disabled = true;
		afficherEtat('Export en cours…');
		try {
			const p = active;
			const res = await produire(p);
			telecharger(res.blob, nomFichier(el.nom.value || p.nom + '-retouche', res.ext));
			p.exportee = true; planifierSauvegarde(p);
			toast('success', 'Photo exportée : ' + res.w + ' × ' + res.h + ' px.');
		} catch (e) {
			console.error(e);
			toast('error', e.filigrane ? MSG_FILIGRANE : "L'export a échoué : la photo est peut-être trop grande pour la mémoire de cet appareil. Essayez une taille plus petite.");
		} finally {
			cacherEtat(); el.exporter.disabled = false; etat.occupe = false;
		}
	});

	function chargerJsZip() {
		if (window.JSZip) { return Promise.resolve(window.JSZip); }
		return new Promise((ok, ko) => {
			const s = document.createElement('script');
			s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
			s.onload = () => (window.JSZip ? ok(window.JSZip) : ko(new Error('JSZip')));
			s.onerror = () => ko(new Error('JSZip'));
			document.head.appendChild(s);
		});
	}

	el.exporterTout.addEventListener('click', async () => {
		if (photos.length < 2 || etat.occupe) { return; }
		etat.occupe = true;
		el.exporterTout.disabled = true;
		const depart = active;
		try {
			const JSZip = await chargerJsZip();
			const zip = new JSZip();
			const pris = new Set();
			for (let i = 0; i < photos.length; i++) {
				const p = photos[i];
				afficherEtat('Export : photo ' + (i + 1) + ' sur ' + photos.length + '…');
				const res = await produire(p);
				let nom = nomFichier(p.nomExport || p.nom + '-retouche', res.ext), k = 2;
				while (pris.has(nom.toLowerCase())) { nom = nomFichier((p.nomExport || p.nom + '-retouche') + '-' + k++, res.ext); }
				pris.add(nom.toLowerCase());
				zip.file(nom, res.blob);
				p.exportee = true; planifierSauvegarde(p);
			}
			afficherEtat('Préparation du fichier ZIP…');
			const archive = await zip.generateAsync({ type: 'blob', compression: 'STORE' });
			telecharger(archive, 'photos-retouchees.zip');
			toast('success', photos.length + ' photos exportées dans « photos-retouchees.zip ».');
		} catch (e) {
			console.error(e);
			toast('error', e && e.filigrane ? MSG_FILIGRANE : "L'export groupé a échoué (" + (e && e.message === 'JSZip' ? 'le module ZIP ne se charge pas : connexion à Internet ?' : 'mémoire insuffisante ? essayez une taille plus petite') + ').');
		} finally {
			cacherEtat(); el.exporterTout.disabled = false; etat.occupe = false;
			if (depart && photos.includes(depart)) { await activer(depart); }
		}
	});

	/* ======================================================================
	   Photo de profil (demande du 19/10/2026) : la photo retouchee remplace
	   celle d'un etudiant ou d'un enseignant, comme « Changer la photo » sur
	   sa fiche (app/retouche-photo.profil.php). La personne choisie reste notee
	   sur la photo (photo.profil, dans la sauvegarde) : apres d'autres
	   retouches, la renvoyer ne demande pas de la chercher a nouveau.
	   ====================================================================== */
	const PROFIL_COTE = 1200;                      // le serveur ramene de toute facon a 1200 px (data/photo.php)
	const PROFIL_POIDS_MAX = 1.8 * 1024 * 1024;    // PHP en production : 2 Mo par fichier
	const pf = {
		dialogue: $('rpProfil'), ouvrir: $('rpProfilOuvrir'), fermer: $('rpProfilFermer'), annuler: $('rpProfilAnnuler'), valider: $('rpProfilValider'),
		apercu: $('rpProfilApercu'), prepa: $('rpProfilPrepa'), dims: $('rpProfilDims'),
		q: $('rpProfilQ'), qLibelle: $('rpProfilQLibelle'), liste: $('rpProfilListe'), info: $('rpProfilInfo'),
		choisie: $('rpProfilChoisie'), avant: $('rpProfilAvant'), apres: $('rpProfilApres'), nom: $('rpProfilNom'), detail: $('rpProfilDetail'),
	};
	const profil = { photo: null, blob: null, url: null, choix: null, resultats: [], requete: 0, minuterie: 0, envoi: false };

	const typeProfil = () => (document.querySelector('input[name="rpProfilType"]:checked') || {}).value || 'equipe';
	function choisirTypeProfil(type) {
		document.querySelectorAll('input[name="rpProfilType"]').forEach((i) => { i.checked = i.value === type; });
		pf.qLibelle.textContent = type === 'equipe' ? 'Nom ou fonction' : "Nom de l'auteur";
	}

	async function appelProfil(params, corps) {
		const rep = await fetch(app.dataset.profil + '?' + new URLSearchParams(params), {
			method: corps ? 'POST' : 'GET', body: corps || undefined, credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-TOKEN': app.dataset.csrf || '' },
		});
		let d = null;
		try { d = await rep.json(); } catch (e) { d = null; }
		if (!rep.ok || !d || !d.ok) {
			const message = (d && d.message) || (rep.status === 413 ? 'La photo est trop lourde pour le serveur.' : 'Le serveur ne répond pas comme prévu (erreur ' + rep.status + ').');
			throw Object.assign(new Error(message), { statut: rep.status });
		}
		return d;
	}

	/* La photo actuelle d'une personne, ou ses initiales. */
	function visagePersonne(p) {
		const s = document.createElement('span');
		s.className = 'rp-profil__visage';
		if (p && p.photo) {
			const img = document.createElement('img');
			img.src = p.photo; img.alt = ''; img.loading = 'lazy'; img.decoding = 'async';
			s.appendChild(img);
		} else {
			s.classList.add('rp-profil__visage--vide');
			s.textContent = String((p && p.nom) || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((m) => m[0]).join('').toUpperCase();
			s.title = 'Pas encore de photo';
		}
		return s;
	}

	function infoProfil(texte) { pf.info.textContent = texte || ''; }

	function afficherPersonnes() {
		pf.liste.replaceChildren(...profil.resultats.map((p) => {
			const li = document.createElement('li');
			const b = document.createElement('button');
			b.type = 'button'; b.className = 'rp-profil__personne';
			b.setAttribute('aria-pressed', String(!!profil.choix && profil.choix.type === p.type && profil.choix.id === p.id));
			const texte = document.createElement('span'); texte.className = 'rp-profil__texte';
			const nom = document.createElement('strong'); nom.textContent = p.nom;
			const detail = document.createElement('span'); detail.textContent = p.detail;
			texte.append(nom, detail);
			b.append(visagePersonne(p), texte);
			b.addEventListener('click', () => choisirPersonne(p));
			b.addEventListener('keydown', (e) => {
				const voisin = e.key === 'ArrowDown' ? li.nextElementSibling : e.key === 'ArrowUp' ? li.previousElementSibling : null;
				if (voisin) { e.preventDefault(); voisin.querySelector('button').focus(); }
				else if (e.key === 'ArrowUp') { e.preventDefault(); pf.q.focus(); }
			});
			li.appendChild(b);
			return li;
		}));
	}

	async function chercherPersonnes() {
		clearTimeout(profil.minuterie); profil.minuterie = 0;
		const q = pf.q.value.trim(), type = typeProfil(), n = ++profil.requete;
		const quoi = type === 'equipe' ? 'membre de l’équipe' : 'témoignage';
		if (q.length < 2) {
			profil.resultats = []; afficherPersonnes();
			infoProfil('Tapez au moins deux lettres du nom.');
			return;
		}
		infoProfil('Recherche…');
		try {
			const d = await appelProfil({ action: 'chercher', type, q });
			if (n !== profil.requete) { return; }   // une recherche plus recente est partie
			profil.resultats = d.resultats;
			afficherPersonnes();
			infoProfil(!d.resultats.length ? 'Aucun ' + quoi + ' ne correspond à « ' + q + ' ».'
				: d.plus ? 'Les ' + d.resultats.length + ' premiers : précisez la recherche si la personne n\'y est pas.' : '');
		} catch (e) {
			if (n !== profil.requete) { return; }
			profil.resultats = []; afficherPersonnes();
			infoProfil(e.message);
		}
	}

	function choisirPersonne(p) {
		profil.choix = p;
		pf.choisie.hidden = !p;
		if (p) {
			pf.avant.replaceChildren(visagePersonne(p));
			pf.nom.textContent = p.nom;
			pf.detail.textContent = p.detail;
		}
		majValiderProfil();
		afficherPersonnes();
	}

	function majValiderProfil() {
		pf.valider.disabled = !profil.choix || !profil.blob || profil.envoi;
	}

	/* La photo telle qu'elle partira : JPEG, 1200 px au plus, sous la limite du serveur. */
	async function produireProfil(photo) {
		let res = null;
		for (const q of [0.92, 0.85, 0.78, 0.7, 0.6]) {
			res = await produire(photo, { profil: PROFIL_COTE, qualite: q });
			if (res.blob.size <= PROFIL_POIDS_MAX) { return res; }
		}
		throw new Error('poids');
	}

	function ko(octets) { return octets < 1024 * 1024 ? Math.max(1, Math.round(octets / 1024)) + ' Ko' : (octets / 1024 / 1024).toFixed(1).replace('.', ',') + ' Mo'; }

	async function ouvrirProfil() {
		if (!active || etat.occupe || pf.dialogue.open) { return; }
		const photo = active;
		Object.assign(profil, { photo, blob: null, choix: null, resultats: [], envoi: false });
		if (profil.url) { URL.revokeObjectURL(profil.url); profil.url = null; }
		pf.apercu.hidden = true; pf.prepa.hidden = false; pf.dims.textContent = '';
		pf.liste.replaceChildren(); pf.choisie.hidden = true;
		// la personne deja choisie pour cette photo, sinon un matricule dans le nom du fichier
		const deja = photo.profil;
		const matricule = '';
		choisirTypeProfil(deja ? deja.type : 'equipe');
		pf.q.value = deja ? '' : matricule;
		majValiderProfil();
		pf.dialogue.showModal();
		if (deja) {
			choisirPersonne(deja);
			const le = new Date(deja.le);
			infoProfil('Déjà envoyée à ' + deja.nom + ' le ' + le.toLocaleDateString('fr-FR') + ' à ' + le.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
				+ ' : « Remplacer la photo » met sa photo à jour avec vos dernières retouches. Pour une autre personne, cherchez-la.');
		} else {
			chercherPersonnes();
		}
		pf.q.focus();
		etat.occupe = true;
		try {
			const res = await produireProfil(photo);
			if (!pf.dialogue.open || profil.photo !== photo) { return; }
			profil.blob = res.blob;
			profil.url = URL.createObjectURL(res.blob);
			pf.apercu.src = profil.url; pf.apercu.hidden = false; pf.prepa.hidden = true;
			pf.apres.src = profil.url;
			pf.dims.textContent = res.w + ' × ' + res.h + ' px · JPEG · ' + ko(res.blob.size)
				+ (photo.reglages.fond.mode === 'transparent' ? ' · fond transparent posé sur du blanc' : '');
		} catch (e) {
			console.error(e);
			pf.prepa.hidden = true;
			pf.dims.textContent = e.message === 'poids'
				? 'La photo reste trop lourde pour le serveur, même compressée.'
				: "La photo n'a pas pu être préparée : la mémoire de cet appareil est peut-être insuffisante.";
		} finally {
			etat.occupe = false;
			majValiderProfil();
		}
	}

	async function envoyerProfil() {
		const p = profil.choix, photo = profil.photo;
		if (!p || !profil.blob || profil.envoi) { return; }
		profil.envoi = true; majValiderProfil();
		pf.valider.setAttribute('aria-busy', 'true');
		try {
			const corps = new FormData();
			corps.append('type', p.type);
			corps.append('id', String(p.id));
			corps.append('photo', profil.blob, 'profil.jpg');
			const d = await appelProfil({ action: 'remplacer' }, corps);
			photo.profil = { type: p.type, id: p.id, nom: d.personne.nom, detail: d.personne.detail, photo: d.personne.photo, le: Date.now() };
			photo.exportee = true;
			planifierSauvegarde(photo);
			pf.dialogue.close();
			toast('success', (p.type === 'equipe' ? 'La photo du membre de l’équipe ' : 'La photo du témoignage de ') + d.personne.nom + ' est remplacée.');
		} catch (e) {
			toast('error', e.statut === 401 ? e.message : "La photo de profil n'a pas été remplacée : " + e.message, 9000);
		} finally {
			profil.envoi = false; pf.valider.removeAttribute('aria-busy');
			majValiderProfil();
		}
	}

	pf.ouvrir.addEventListener('click', ouvrirProfil);
	pf.valider.addEventListener('click', envoyerProfil);
	[pf.fermer, pf.annuler].forEach((b) => b.addEventListener('click', () => { if (!profil.envoi) { pf.dialogue.close(); } }));
	pf.dialogue.addEventListener('cancel', (e) => { if (profil.envoi) { e.preventDefault(); } });   // Echap pendant l'envoi
	pf.dialogue.addEventListener('click', (e) => { if (e.target === pf.dialogue && !profil.envoi) { pf.dialogue.close(); } }); // clic hors de la fenetre
	pf.dialogue.addEventListener('close', () => {
		profil.requete++; clearTimeout(profil.minuterie);
		profil.photo = null; profil.blob = null; profil.choix = null;
		pf.apercu.removeAttribute('src'); pf.apres.removeAttribute('src');
		if (profil.url) { URL.revokeObjectURL(profil.url); profil.url = null; }
		pf.ouvrir.focus();
	});
	document.querySelectorAll('input[name="rpProfilType"]').forEach((i) => i.addEventListener('change', () => {
		if (!i.checked) { return; }
		choisirTypeProfil(i.value);
		if (profil.choix && profil.choix.type !== i.value) { choisirPersonne(null); }
		chercherPersonnes();
		pf.q.focus();
	}));
	pf.q.addEventListener('input', () => { clearTimeout(profil.minuterie); profil.minuterie = setTimeout(chercherPersonnes, 250); });
	pf.q.addEventListener('keydown', (e) => {
		if (e.key === 'ArrowDown') {
			const b = pf.liste.querySelector('button');
			if (b) { e.preventDefault(); b.focus(); }
		} else if (e.key === 'Enter') {
			e.preventDefault();
			if (profil.minuterie) { chercherPersonnes(); }
			else if (profil.resultats.length === 1) { choisirPersonne(profil.resultats[0]); }
		}
	});

	/* ======================================================================
	   Sauvegarde automatique (src/js/retouche-photo.sauvegarde.js)

	   Chaque modification gardee dans l'historique est enregistree une demi-
	   seconde plus tard dans le navigateur ; en revenant sur la page, l'atelier
	   reprend tel qu'il etait. La photo d'origine s'ecrit une fois, a
	   l'ouverture ; le detourage une fois calcule (repris ensuite au lieu
	   d'etre refait) ; le pinceau du detourage et des masques quand il change ;
	   l'historique des reglages suit (pas celui du pinceau : apres une reprise,
	   Ctrl+Z revient sur les reglages, les coups de pinceau restent). Les
	   outils aussi : taille et douceur du pinceau, zone en rouge, volets
	   ouverts, masque ou pinceau du detourage en cours (demande du 13/10/2026 :
	   « toujours sauvegarder ... quoi que ce soit »).
	   ====================================================================== */
	let base = null;              // la base du compte, ou null (navigateur sans IndexedDB)
	let enPanne = false;          // une ecriture a echoue (stockage plein...)
	let repriseFaite = false;     // rien ne s'ecrit avant la fin de la reprise
	const aSauver = new Set();
	let atelierASauver = false, minuterieSauvegarde = 0, ecritures = Promise.resolve();
	const temoin = $('rpSauve'), temoinTexte = $('rpSauveTexte');

	/* Le temoin : l'enregistrement dans ce navigateur (etatLocal), puis la
	   copie du serveur (etatServeur, src/js/retouche-photo.sauvegarde.js). */
	let etatLocal = 'aucune';
	let etatServeur = { etat: 'ok', attente: 0, message: '' };
	const avertis = new Set();   // un seul avis par sorte (session, serveur plein)
	function majTemoin(etatT) {
		if (etatT) { etatLocal = etatT; }
		const s = etatServeur, avecServeur = !!(base && base.serveur);
		let affiche = etatLocal, texte = { ok: 'Enregistré', attente: 'Enregistrement…', erreur: 'Non enregistré' }[etatLocal] || '', icone = etatLocal === 'erreur' ? 'bi-exclamation-triangle' : 'bi-check2-circle';
		if (etatLocal === 'ok' && avecServeur) {
			if (s.etat === 'envoi') { affiche = 'envoi'; texte = 'Envoi au serveur…' + (s.attente > 1 ? ' (' + s.attente + ')' : ''); icone = 'bi-cloud-arrow-up'; }
			else if (s.etat === 'horsligne') { affiche = 'horsligne'; texte = 'Sur cet appareil · envoi en attente'; icone = 'bi-cloud-slash'; }
			else if (s.etat === 'session') { affiche = 'horsligne'; texte = 'Reconnectez-vous pour l\'envoi'; icone = 'bi-cloud-slash'; }
			else if (s.etat === 'plein') { affiche = 'erreur'; texte = 'Serveur plein'; icone = 'bi-exclamation-triangle'; }
			else { texte = 'Enregistré sur le serveur'; icone = 'bi-cloud-check'; }
		}
		temoin.hidden = !photos.length || etatLocal === 'aucune';
		temoin.dataset.etat = affiche;
		temoinTexte.textContent = texte;
		temoin.querySelector('i').className = 'bi ' + icone;
	}
	function majServeur(s) {
		etatServeur = s;
		if (s.etat === 'session' && !avertis.has('session')) {
			avertis.add('session');
			toast('warning', 'Votre session a expiré : reconnectez-vous pour que vos dernières retouches partent au serveur. Elles restent gardées sur cet appareil.', 12000);
		}
		if (s.etat === 'plein' && !avertis.has('plein')) { avertis.add('plein'); toast('error', s.message, 12000); }
		if (s.etat === 'ok') { avertis.clear(); }
		majTemoin();
	}

	function planifierSauvegarde(photo) {
		if (photo) { aSauver.add(photo); }
		atelierASauver = true;
		if (!base) { return; }
		clearTimeout(minuterieSauvegarde);
		minuterieSauvegarde = setTimeout(sauvegarder, 500);
		majTemoin('attente');
	}

	function echecSauvegarde(e) {
		console.error('Sauvegarde', e);
		if (!enPanne) {
			toast('warning', "La sauvegarde automatique n'a pas pu s'écrire (espace du navigateur plein ?). Exportez vos photos avant de quitter la page.", 10000);
		}
		enPanne = true;
		majTemoin('erreur');
	}

	function sauverFichier(photo) {
		if (!base || !photo.fichier) { return; }
		ecritures = ecritures.then(() => base.ecrire({ fichiers: [{ id: photo.id, fichier: photo.fichier }] })).catch(echecSauvegarde);
	}
	function sauverFond(id, fichier) {
		if (!base) { return; }
		ecritures = ecritures.then(() => base.ecrire({ fonds: [{ id, nom: fichier.name, fichier }] })).catch(echecSauvegarde);
	}
	/* Synchronisation manquee au chargement (serveur injoignable) : retentee
	   plus tard. Si elle apporte des photos d'un autre appareil, l'atelier les
	   charge s'il est vide ; sinon il le dit (le travail en cours reste). */
	function retenterSynchro(delai) {
		setTimeout(async () => {
			if (!base || !base.serveur) { return; }
			try {
				const r = await base.synchroniser();
				if (!r || !(r.recus || r.retires)) { return; }
				if (!photos.length) { await sauvegarder(); location.reload(); }
				else { toast('info', "Des photos ou retouches d'un autre appareil sont arrivées : rechargez la page pour les voir.", 12000); }
			} catch (e) {
				if (!e.session) { retenterSynchro(Math.min(delai * 2, 300000)); }
			}
		}, delai);
	}

	/* Les outils tels qu'on les a laisses : pinceau, zone en rouge, volets
	   ouverts, et le masque ou le pinceau du detourage en cours. */
	const volets = () => Array.from(document.querySelectorAll('#rpReglages details.rp-volet'));
	const nomVolet = (d) => (d.querySelector('summary') || d).textContent.trim();
	function etatOutils() {
		const ouverts = {};
		volets().forEach((d) => { ouverts[nomVolet(d)] = d.open; });
		return {
			taille: pinceau.taille, douceur: pinceau.douceur, voirZone: etat.voirZone, volets: ouverts, grille: Object.assign({}, grille),
			mode: etat.mode === 'masque' || etat.mode === 'pinceau' ? etat.mode : null, masqueId: etat.masqueId,
		};
	}
	function reprendreOutils(o) {
		if (!o) { return; }
		if (o.taille) { regler('taille', o.taille); }
		if (o.douceur != null) { regler('douceur', o.douceur); }
		if (typeof o.voirZone === 'boolean') { etat.voirZone = o.voirZone; }
		volets().forEach((d) => { const v = o.volets && o.volets[nomVolet(d)]; if (typeof v === 'boolean') { d.open = v; } });
		if (o.grille && ORDRE_GRILLES.includes(o.grille.type)) {
			Object.assign(grille, {
				type: o.grille.type, orientation: (parseInt(o.grille.orientation, 10) || 0) & 3,
				densite: Math.min(24, Math.max(4, parseInt(o.grille.densite, 10) || 8)),
			});
			majMenuGrille(); demanderRendu();
		}
		if (!active) { return; }
		if (o.mode === 'masque' && (active.reglages.masques || []).some((m) => m.id === o.masqueId)) { basculerMasque(true, o.masqueId); }
		else if (o.mode === 'pinceau') { basculerPinceau(true); }
	}
	volets().forEach((d) => d.addEventListener('toggle', () => planifierSauvegarde(null)));

	/* Le detourage, compresse (environ 250 Ko), ecrit une fois apres son calcul. */
	function sauverDetourage(photo, seg) {
		if (!base || !window.RetoucheSauvegarde || !photo.t) { return; }
		const { w, h } = photo.t;
		RetoucheSauvegarde.emballerDetourage(photo.id, seg, w, h).then((rec) => {
			if (!photos.includes(photo)) { return; }   // fermee entre-temps
			photo.detourageSauve = rec;
			ecritures = ecritures.then(() => base.ecrire({ detourages: [rec] })).catch(echecSauvegarde);
		}).catch((e) => console.warn('Detourage non enregistre', e));
	}

	function oublierPhoto(p) {
		aSauver.delete(p);
		planifierSauvegarde(null);
		if (base) { ecritures = ecritures.then(() => base.supprimerPhoto(p.id)).catch(echecSauvegarde); }
	}

	function sauvegarder() {
		clearTimeout(minuterieSauvegarde); minuterieSauvegarde = 0;
		if (!base || !repriseFaite) { return ecritures; }
		const lot = Array.from(aSauver).filter((p) => photos.includes(p));
		aSauver.clear();
		const ajouts = { photos: [], masques: [] }, masquesEcrits = [];
		lot.forEach((p) => {
			ajouts.photos.push({
				id: p.id, nom: p.nom, reglages: JSON.stringify(p.reglages), pile: p.pile.map((e) => e.r), index: p.index,
				nomExport: p.nomExport, modifie: p.modifie, exportee: p.exportee, profil: p.profil || null, studio: p.studio || null, vignette: p.vignetteBlob || null, majLe: Date.now(),
			});
			// corrections du detourage et pinceau des masques : ensemble, quand l'un change
			if (p.retouches !== p.retouchesSauvees || p.calques !== p.calquesSauves) {
				ajouts.masques.push({ id: p.id, donnees: p.retouches, calques: p.calques });
				masquesEcrits.push([p, p.retouches, p.calques]);
			}
		});
		if (atelierASauver) {
			ajouts.atelier = [{
				cle: 'atelier', ordre: photos.map((p) => p.id), active: active ? active.id : null,
				format: el.format.value, taille: el.taille.value, qualite: el.qualite.value, majLe: Date.now(),
				outils: etatOutils(), filigrane: Object.assign({}, filigrane),
			}];
			atelierASauver = false;
		}
		ecritures = ecritures
			.then(() => base.ecrire(ajouts))
			.then(() => {
				masquesEcrits.forEach(([p, m, c]) => { p.retouchesSauvees = m; p.calquesSauves = c; });
				if (!minuterieSauvegarde) { enPanne = false; majTemoin('ok'); }
			})
			.catch(echecSauvegarde);
		return ecritures;
	}

	/* Reprise de la derniere seance : photos dans leur ordre, reglages,
	   historique, pinceau, fonds, photo affichee, options d'export. */
	async function reprendre() {
		try {
			base = window.RetoucheSauvegarde ? await window.RetoucheSauvegarde.ouvrir(app.dataset.compte || '0', {
				surBloquee: () => toast('warning', "La sauvegarde se met à jour : fermez les autres onglets de la retouche photo pour retrouver vos photos.", 12000),
				serveur: app.dataset.api || null,
				surEnvoi: majServeur,
			}) : null;
		} catch (e) { base = null; }
		if (!base) { repriseFaite = true; majTemoin('aucune'); return; }
		/* Le serveur d'abord : sur un autre appareil, les photos et retouches du
		   compte arrivent ici avant la reprise ; ce qui n'y est pas encore part. */
		if (base.serveur) {
			try {
				afficherEtat('Synchronisation avec le serveur…');
				await base.synchroniser(afficherEtat);
			} catch (e) {
				console.warn('Synchronisation', e);
				majServeur({ etat: e.session ? 'session' : 'horsligne', attente: 0, message: e.message });
				if (!e.session) {
					toast('warning', "Le serveur n'a pas pu être joint : vous travaillez sur la copie de cet appareil. Elle partira au serveur dès que la connexion reviendra.", 10000);
					retenterSynchro(20000);
				}
				base.pomper();
			}
		}
		let d;
		try { d = await base.toutLire(); } catch (e) { console.error(e); repriseFaite = true; cacherEtat(); majTemoin('ok'); return; }
		/* L'ordre de l'atelier, puis les photos qu'il ne connait pas (venues d'un
		   autre appareil), dans l'ordre ou elles ont ete ouvertes. */
		const connues = ((d.atelier && d.atelier.ordre) || []).filter((id) => d.photos.has(id));
		const ordre = connues.concat(Array.from(d.photos.keys()).filter((id) => !connues.includes(id)).sort((a, b) => a - b));
		for (const f of d.fonds) {
			// le logo du filigrane voyage avec les images de fond, sans en etre une
			if (f.id === 'filigrane') {
				try { definirLogoPerso(f.fichier, f.nom, await ouvrirLogo(f.fichier)); } catch (e) { /* logo illisible : ignore */ }
				continue;
			}
			try { imagesFond.set(f.id, { image: (await decoder(f.fichier)).image, nom: f.nom, url: URL.createObjectURL(f.fichier) }); } catch (e) { /* image de fond illisible : ignoree */ }
		}
		let n = 0;
		for (const id of ordre) {
			const rec = d.photos.get(id), fic = d.fichiers.get(id);
			if (!rec || !fic) { continue; }
			if (!n) { afficherEtat('Reprise de votre dernière séance…'); }
			try {
				const photo = nouvellePhoto(id, rec.nom, fic.fichier, await decoder(fic.fichier));
				photo.reglages = fusionner(reglagesDefaut(), JSON.parse(rec.reglages));
				const masque = d.masques.get(id);
				photo.retouches = masque && masque.donnees ? masque.donnees : null;
				photo.retouchesSauvees = photo.retouches;
				photo.calques = (masque && masque.calques) || {};
				photo.calquesSauves = photo.calques;
				photo.detourageSauve = d.detourages.get(id) || null;
				const pile = rec.pile && rec.pile.length ? rec.pile : [rec.reglages];
				photo.pile = pile.map((r) => ({ r, m: photo.retouches, c: photo.calques }));
				photo.index = Math.min(pile.length - 1, Math.max(0, rec.index == null ? pile.length - 1 : rec.index));
				Object.assign(photo, { modifie: !!rec.modifie, exportee: !!rec.exportee, nomExport: rec.nomExport || null, profil: rec.profil || null, studio: rec.studio || null, vignetteBlob: rec.vignette || null });
				photos.push(photo);
				creerVignette(photo);
				if (rec.vignette) {
					try {
						const v = await createImageBitmap(rec.vignette), g = photo.vignette.getContext('2d');
						g.clearRect(0, 0, photo.vignette.width, photo.vignette.height);
						g.drawImage(v, 0, 0, photo.vignette.width, photo.vignette.height);
						v.close();
					} catch (e) { /* la vignette d'origine reste */ }
				}
				compteur = Math.max(compteur, id);
				n++;
			} catch (e) { console.warn('Photo non reprise', id, e); }
		}
		if (d.atelier) {
			if (d.atelier.format) { el.format.value = d.atelier.format; }
			if (d.atelier.taille != null) { el.taille.value = d.atelier.taille; }
			if (d.atelier.qualite) { el.qualite.value = d.atelier.qualite; el.qualiteBloc.querySelector('.rp-curseur__valeur').value = d.atelier.qualite; }
		}
		reprendreFiligrane(d.atelier && d.atelier.filigrane);
		repriseFaite = true;
		cacherEtat();
		if (photos.length) {
			await activer(photos.find((p) => p.id === (d.atelier && d.atelier.active)) || photos[0]);
			majPellicule();
			toast('success', n === 1 ? 'Votre photo et ses retouches sont de retour.' : 'Vos ' + n + ' photos et leurs retouches sont de retour.');
		}
		reprendreOutils(d.atelier && d.atelier.outils);
		majTemoin('ok');
	}

	$('rpToutFermer').addEventListener('click', async () => {
		if (!photos.length || etat.occupe) { return; }
		const nonExportees = photos.filter((p) => p.modifie && !p.exportee).length;
		const ok = window.Toast && window.Toast.confirm
			? await window.Toast.confirm((photos.length === 1 ? 'Fermer la photo ?' : 'Fermer les ' + photos.length + ' photos ?')
				+ ' Elles quittent l\'atelier et sa sauvegarde, sur cet appareil et sur le serveur (tous vos appareils)'
				+ (nonExportees ? ' ; ' + nonExportees + ' retouchée' + (nonExportees > 1 ? 's' : '') + ' sans export seront perdues.' : '.'), { confirmText: 'Tout fermer', danger: true })
			: true;
		if (!ok) { return; }
		quitterModes();
		photos.splice(0).forEach((p) => { try { p.image.close(); } catch (e) { /* deja liberee */ } });
		recents = []; active = null; aSauver.clear();
		imagesFond.clear(); moteur.imageFond(null); imageFondChargee = null;
		clearTimeout(minuterieSauvegarde); minuterieSauvegarde = 0; atelierASauver = false;
		// le filigrane n'est pas une photo : il reste, et son logo avec (reecrit un
		// instant apres, pour dater d'apres le « Tout fermer » sur les autres appareils)
		if (base) { ecritures = ecritures.then(() => base.vider()).then(() => new Promise((ok) => setTimeout(ok, 20))).catch(echecSauvegarde); }
		sauverLogoFiligrane(); planifierSauvegarde(null);
		majInterface(); majPellicule(); majTemoin('ok');
		const g = moteur.gl; g.clearColor(0, 0, 0, 0); g.clear(g.COLOR_BUFFER_BIT);
	});

	// en quittant la page : ce qui attend part tout de suite
	document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden' && minuterieSauvegarde) { sauvegarder(); } });
	window.addEventListener('pagehide', () => { if (minuterieSauvegarde) { sauvegarder(); } });

	/* ======================================================================
	   Clavier, historique, fenetre
	   ====================================================================== */
	el.annuler.addEventListener('click', () => revenir(-1));
	el.retablir.addEventListener('click', () => revenir(1));

	document.addEventListener('keydown', (e) => {
		if (!active || pf.dialogue.open) { return; }   // la fenetre « Photo de profil » garde ses touches (Echap la ferme)
		const champ = e.target.closest && e.target.closest('input, select, textarea, [contenteditable="true"]');
		const touche = e.key;
		if ((e.ctrlKey || e.metaKey) && !e.altKey) {
			const k = touche.toLowerCase();
			if (champ && champ.type !== 'range') { return; }
			if (k === 'z' && !e.shiftKey) { e.preventDefault(); revenir(-1); return; }
			if (k === 'y' || (k === 'z' && e.shiftKey)) { e.preventDefault(); revenir(1); return; }
			return;
		}
		if (touche === 'Escape') {
			if (!gr.menu.hidden) { ouvrirMenuGrille(false); return; }
			if (etat.pipette) { basculerPipette(false); return; }
			if (etat.mode === 'recadrer') { basculerRecadrage(false); return; }
			if (etat.mode === 'pinceau') { basculerPinceau(false); return; }
			if (etat.mode === 'masque') { basculerMasque(false); return; }
			return;
		}
		if (champ || e.altKey) { return; }
		if (touche === '\\') { e.preventDefault(); basculerAvant(); }
		else if (touche === 'r' || touche === 'R') { e.preventDefault(); basculerRecadrage(etat.mode !== 'recadrer'); }
		else if (touche === 'b' || touche === 'B') { e.preventDefault(); basculerPinceau(etat.mode !== 'pinceau'); }
		else if (touche === 'm' || touche === 'M') { e.preventDefault(); el.masques.click(); }
		else if (touche === 'x' || touche === 'X') { e.preventDefault(); inverserFormat(); }
		else if (touche === 'o' || touche === 'O') { e.preventDefault(); if (e.shiftKey) { pivoterGrille(); } else { grilleSuivante(); } }
		else if (touche === 'Enter' && etat.mode === 'recadrer') { e.preventDefault(); basculerRecadrage(false); }
		else if ((touche === '[' || touche === ']') && (etat.mode === 'pinceau' || (etat.mode === 'masque' && pinceauMasque.mode !== 'non'))) {
			regler('taille', borne(pinceau.taille + (touche === ']' ? 10 : -10), 8, 400));
		}
	});

	window.addEventListener('beforeunload', (e) => {
		if (minuterieSauvegarde) { sauvegarder(); }
		// avec la sauvegarde, rien ne se perd : l'alerte ne reste que sans elle,
		// ou pendant un envoi au serveur (sur un autre appareil, il manquerait)
		if (((!base || enPanne) && photos.some((p) => p.modifie && !p.exportee)) || etatServeur.etat === 'envoi') { e.preventDefault(); e.returnValue = ''; }
	});
	if (window.ResizeObserver) { new ResizeObserver(() => demanderRendu()).observe(scene); }
	else { window.addEventListener('resize', demanderRendu); }

	/* ======================================================================
	   Pont avec le Studio UAZ (demande du 20/10/2026, etape 4 du Studio) : cette page,
	   ouverte par « Retoucher dans l'editeur » (src/js/studio.js), porte ?studio=<jeton>.
	   Elle dit « pret » a l'onglet du Studio, en recoit la photo de l'emplacement, et
	   « Renvoyer au Studio UAZ » la lui rend retouchee (JPEG, 2 400 px au plus, sans
	   filigrane) ; l'onglet se ferme quand le Studio l'a recue. Meme site seulement.
	   La photo garde son lien (photo.studio, dans la sauvegarde).
	   ====================================================================== */
	const studio = (() => {
		const jeton = new URLSearchParams(location.search).get('studio') || '';
		return /^st[a-z0-9]{2,40}$/.test(jeton) && window.opener ? { jeton, ouvreur: window.opener } : null;
	})();
	const STUDIO_COTE = 2400;
	const boutonStudio = $('rpStudioRenvoyer');
	function majBoutonStudio() {
		if (!boutonStudio) { return; }
		boutonStudio.hidden = !(studio && active && active.studio && active.studio.jeton === studio.jeton && !studio.ouvreur.closed);
	}
	if (studio && boutonStudio) {
		window.addEventListener('message', async (e) => {
			const d = e.data;
			if (e.origin !== location.origin || e.source !== studio.ouvreur || !d || typeof d !== 'object' || d.jeton !== studio.jeton) { return; }
			if (d.type === 'studio-uaz:photo' && d.blob instanceof Blob) {
				const avant = photos.length;
				const ext = { 'image/png': 'png', 'image/webp': 'webp' }[d.blob.type] || 'jpg';
				const nom = String(d.nom || 'photo-studio').replace(/\.[^.]+$/, '') + '.' + ext;
				await ajouterFichiers([new File([d.blob], nom, { type: d.blob.type })]);
				const photo = photos.length > avant ? photos[photos.length - 1] : null;
				if (!photo) { return; }
				photo.studio = { jeton: studio.jeton, emplacement: String(d.emplacement || ''), libelle: String(d.libelle || ''), recueLe: Date.now(), renvoyeeLe: null };
				planifierSauvegarde(photo);
				await activer(photo);
				majBoutonStudio();
				toast('info', 'Photo reçue du Studio UAZ (« ' + photo.studio.libelle + ' »). Retouchez-la, puis « Renvoyer au Studio UAZ ».', 9000);
			} else if (d.type === 'studio-uaz:recu') {
				toast('success', 'Le Studio UAZ a reçu la photo retouchée. Cet onglet se ferme.');
				setTimeout(() => window.close(), 1500);
			}
		});
		boutonStudio.addEventListener('click', async () => {
			const p = active;
			if (!p || !p.studio || etat.occupe) { return; }
			if (studio.ouvreur.closed) {
				majBoutonStudio();
				toast('error', "L'onglet du Studio UAZ est fermé : exportez la photo, puis chargez-la dans le Studio.", 9000);
				return;
			}
			etat.occupe = true;
			boutonStudio.disabled = true;
			afficherEtat('Envoi au Studio UAZ…');
			try {
				const res = await produire(p, { profil: STUDIO_COTE, qualite: 0.92 });
				studio.ouvreur.postMessage({ type: 'studio-uaz:retour', jeton: studio.jeton, emplacement: p.studio.emplacement, blob: res.blob, nom: (p.nom || 'photo') + '-retouche.jpg', largeur: res.w, hauteur: res.h }, location.origin);
				p.studio.renvoyeeLe = Date.now();
				p.exportee = true;
				planifierSauvegarde(p);
			} catch (e) {
				console.error(e);
				toast('error', "La photo n'a pas pu être renvoyée au Studio UAZ : elle est peut-être trop grande pour la mémoire de cet appareil.");
			} finally {
				cacherEtat();
				boutonStudio.disabled = false;
				etat.occupe = false;
			}
		});
		studio.ouvreur.postMessage({ type: 'studio-uaz:pret', jeton: studio.jeton }, location.origin);
	}

	majPerso();
	majInterface();
	majPellicule();
	const repriseTerminee = reprendre().catch((e) => { console.error(e); repriseFaite = true; cacherEtat(); });

	// Pour les essais automatises : etat interne en lecture
	window.RetouchePhoto = { get active() { return active; }, photos, ajouterFichiers, appliquerStudio, produire, etat, moteur, sauvegarder, repriseTerminee, ajouterMasque, basculerMasque, supprimerMasque, revenir, filigrane, changerFiligrane, grille, choisirGrille };
})();
