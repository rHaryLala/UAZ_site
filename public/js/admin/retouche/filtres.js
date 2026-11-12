/* =============================================================================
   Retouche photo — filtres facon Instagram — src/retouche-photo.php

   Chaque filtre est une recette de developpement, appliquee par le moteur
   (src/js/retouche-photo.moteur.js) apres tous les reglages et le fond :
   - sat      : saturation ajoutee avant les courbes (-1 : noir et blanc) ;
   - courbes  : l (toutes les couches), r, g, b, en points [entree, sortie]
                sur 0..255 ; relever le point 0 « delave » les noirs, relever
                le bleu dans les ombres les rend froides, l'abaisser dans les
                hautes lumieres les rechauffe ;
   - grain    : 0..1 ; vignette : -1..1 (negatif : bords assombris).
   Le curseur « Intensite » melange le resultat a la photo sans filtre.

   Les courbes passent par une interpolation cubique monotone (Fritsch et
   Carlson) : pas de rebond, une courbe montante le reste. Puis la couche
   generale l, puis celle du canal, dans une table de 256 valeurs par canal.
   ========================================================================== */
(function () {
	'use strict';

	const LISTE = [
		{ nom: 'aucun', libelle: 'Original' },
		{ nom: 'eclat', libelle: 'Éclat', sat: 0.15,
			courbes: { l: [[0, 0], [64, 57], [128, 134], [192, 206], [255, 255]], g: [[0, 6], [128, 129], [255, 255]], b: [[0, 22], [128, 132], [255, 251]] } },
		{ nom: 'dore', libelle: 'Doré', sat: 0.06, vignette: -0.15,
			courbes: { l: [[0, 16], [64, 74], [128, 140], [192, 202], [255, 250]], r: [[0, 10], [128, 140], [255, 255]], g: [[0, 4], [128, 130], [255, 246]], b: [[0, 0], [128, 112], [255, 220]] } },
		{ nom: 'pastel', libelle: 'Pastel', sat: -0.25,
			courbes: { l: [[0, 40], [64, 88], [128, 146], [192, 202], [255, 242]], r: [[0, 6], [128, 132], [255, 255]], b: [[0, 8], [128, 130], [255, 250]] } },
		{ nom: 'lagon', libelle: 'Lagon', sat: 0.08,
			courbes: { l: [[0, 0], [64, 56], [128, 128], [192, 200], [255, 255]], r: [[0, 0], [64, 52], [128, 130], [192, 206], [255, 255]], g: [[0, 10], [128, 128], [255, 252]], b: [[0, 40], [64, 82], [128, 124], [192, 178], [255, 240]] } },
		{ nom: 'rosee', libelle: 'Rosée', sat: -0.05,
			courbes: { l: [[0, 14], [128, 142], [255, 255]], r: [[0, 10], [128, 140], [255, 255]], g: [[0, 0], [128, 124], [255, 246]], b: [[0, 14], [128, 134], [255, 250]] } },
		{ nom: 'brume', libelle: 'Brume', sat: -0.35, grain: 0.25,
			courbes: { l: [[0, 52], [64, 92], [128, 140], [192, 190], [255, 228]], r: [[0, 0], [255, 250]], g: [[0, 4], [255, 252]], b: [[0, 12], [255, 255]] } },
		{ nom: 'vintage', libelle: 'Vintage', sat: -0.15, grain: 0.3, vignette: -0.3,
			courbes: { l: [[0, 28], [128, 138], [255, 236]], r: [[0, 28], [128, 150], [255, 250]], g: [[0, 18], [128, 135], [255, 236]], b: [[0, 10], [128, 110], [255, 202]] } },
		{ nom: 'pellicule', libelle: 'Pellicule', sat: -0.1, grain: 0.35,
			courbes: { l: [[0, 12], [64, 66], [128, 132], [192, 196], [255, 248]], r: [[0, 4], [128, 134], [255, 252]], g: [[0, 6], [128, 128], [255, 246]], b: [[0, 14], [128, 120], [255, 232]] } },
		{ nom: 'ambre', libelle: 'Ambre', sat: 0.1,
			courbes: { l: [[0, 10], [128, 136], [255, 250]], r: [[0, 20], [128, 148], [255, 255]], g: [[0, 10], [128, 128], [255, 246]], b: [[0, 0], [128, 100], [255, 212]] } },
		{ nom: 'givre', libelle: 'Givre', sat: -0.1,
			courbes: { l: [[0, 8], [128, 140], [255, 255]], r: [[0, 0], [128, 124], [255, 245]], b: [[0, 12], [128, 136], [255, 255]] } },
		{ nom: 'minuit', libelle: 'Minuit', sat: -0.3, vignette: -0.35,
			courbes: { l: [[0, 0], [64, 48], [128, 118], [192, 190], [255, 245]], r: [[0, 0], [128, 118], [255, 240]], g: [[0, 4], [128, 126], [255, 248]], b: [[0, 18], [128, 140], [255, 255]] } },
		{ nom: 'tropique', libelle: 'Tropique', sat: 0.35, vignette: -0.12,
			courbes: { l: [[0, 0], [64, 52], [128, 132], [192, 210], [255, 255]], r: [[0, 0], [128, 132], [255, 255]] } },
		{ nom: 'noir', libelle: 'Noir et blanc', sat: -1, grain: 0.15,
			courbes: { l: [[0, 0], [64, 48], [128, 128], [192, 210], [255, 255]] } },
		{ nom: 'argentique', libelle: 'Argentique', sat: -1, grain: 0.45, vignette: -0.2,
			courbes: { l: [[0, 30], [128, 136], [255, 240]] } },
		{ nom: 'sepia', libelle: 'Sépia', sat: -1,
			courbes: { l: [[0, 10], [255, 250]], r: [[0, 30], [128, 160], [255, 255]], g: [[0, 20], [128, 135], [255, 240]], b: [[0, 10], [128, 105], [255, 205]] } },
	];

	/* Interpolation cubique monotone d'une suite de points : 256 valeurs. */
	function courbe(points) {
		const p = (points && points.length >= 2 ? points : [[0, 0], [255, 255]]).slice().sort((a, b) => a[0] - b[0]);
		const n = p.length, x = p.map((q) => q[0]), y = p.map((q) => q[1]);
		const d = [], m = new Array(n).fill(0);
		for (let i = 0; i < n - 1; i++) { d.push((y[i + 1] - y[i]) / Math.max(1e-6, x[i + 1] - x[i])); }
		m[0] = d[0]; m[n - 1] = d[n - 2];
		for (let i = 1; i < n - 1; i++) { m[i] = d[i - 1] * d[i] <= 0 ? 0 : (d[i - 1] + d[i]) / 2; }
		for (let i = 0; i < n - 1; i++) {
			if (d[i] === 0) { m[i] = m[i + 1] = 0; continue; }
			const a = m[i] / d[i], b = m[i + 1] / d[i], s = a * a + b * b;
			if (s > 9) { const t = 3 / Math.sqrt(s); m[i] = t * a * d[i]; m[i + 1] = t * b * d[i]; }
		}
		const t = new Float32Array(256);
		for (let v = 0; v < 256; v++) {
			if (v <= x[0]) { t[v] = y[0]; continue; }
			if (v >= x[n - 1]) { t[v] = y[n - 1]; continue; }
			let k = 0;
			while (v > x[k + 1]) { k++; }
			const h = x[k + 1] - x[k], u = (v - x[k]) / h, u2 = u * u, u3 = u2 * u;
			t[v] = (2 * u3 - 3 * u2 + 1) * y[k] + (u3 - 2 * u2 + u) * h * m[k] + (-2 * u3 + 3 * u2) * y[k + 1] + (u3 - u2) * h * m[k + 1];
		}
		return t;
	}

	/* Table RGBA de 256 x 1 pour le moteur : couche generale, puis le canal. */
	function table(filtre) {
		const c = (filtre && filtre.courbes) || {};
		const l = courbe(c.l), canaux = [courbe(c.r), courbe(c.g), courbe(c.b)];
		const o = new Uint8Array(256 * 4);
		for (let v = 0; v < 256; v++) {
			const base = Math.min(255, Math.max(0, l[v]));
			for (let k = 0; k < 3; k++) {
				const i = Math.min(255, Math.max(0, base)), f = i - Math.floor(i), j = Math.floor(i);
				const s = canaux[k][j] * (1 - f) + canaux[k][Math.min(255, j + 1)] * f;
				o[v * 4 + k] = Math.round(Math.min(255, Math.max(0, s)));
			}
			o[v * 4 + 3] = 255;
		}
		return o;
	}

	const trouver = (nom) => LISTE.find((f) => f.nom === nom) || LISTE[0];

	window.RetoucheFiltres = { liste: LISTE, trouver, table };
})();
