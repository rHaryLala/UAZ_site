/* =============================================================================
   Retouche photo — detourage et textures de travail — src/retouche-photo.php

   Deux modeles, charges a la premiere demande depuis les CDN puis gardes par
   le navigateur (environ 45 Mo la premiere fois) :

   - MODNet (detourage de portrait, licence Apache 2.0, ONNX Runtime Web) donne
     l'opacite de la personne, meches de cheveux comprises : une fois sur
     l'image entiere (512 px de petit cote), une fois sur un carre autour de
     la tete pour la finesse des cheveux ;
   - MediaPipe (segmenteur « selfie multiclass », 256 x 256) reconnait les
     cheveux, la peau du visage et du corps : la peau sert au preregle Studio
     UAZ et aux reglages « Portrait ». Si MODNet ne se charge pas, son masque
     sert aussi au detourage, cale sur les contours par un filtre guide.

   Tout reste dans le navigateur : aucune photo n'est envoyee au serveur.

   Classes de MediaPipe : 0 fond, 1 cheveux, 2 peau du corps, 3 peau du
   visage, 4 vetements, 5 accessoires (lunettes...).
   ========================================================================== */
(function () {
	'use strict';

	const VERSION = '0.10.21';
	const CDN = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@' + VERSION;
	const MODELE = 'https://storage.googleapis.com/mediapipe-models/image_segmenter/selfie_multiclass_256x256/float32/latest/selfie_multiclass_256x256.tflite';
	const ORT = 'https://cdn.jsdelivr.net/npm/onnxruntime-web@1.18.0/dist/';
	const MODNET = 'https://huggingface.co/Xenova/modnet/resolve/main/onnx/model.onnx';
	const N = 256;
	const MATTE = 512;
	const TRAVAIL_MAX = 1280;

	let segmenteur = null;
	let chargement = null;
	let matteur = null;
	let chargementMatte = null;

	/** Charge MediaPipe et son modele, une seule fois. */
	function preparer() {
		if (segmenteur) { return Promise.resolve(segmenteur); }
		if (!chargement) {
			chargement = (async function () {
				const vision = await import(CDN + '/vision_bundle.mjs');
				const fichiers = await vision.FilesetResolver.forVisionTasks(CDN + '/wasm');
				segmenteur = await vision.ImageSegmenter.createFromOptions(fichiers, {
					baseOptions: { modelAssetPath: MODELE, delegate: 'CPU' },
					runningMode: 'IMAGE', outputCategoryMask: false, outputConfidenceMasks: true,
				});
				return segmenteur;
			})().catch(function (e) { chargement = null; throw e; });
		}
		return chargement;
	}

	function chargerScript(src) {
		return new Promise(function (ok, ko) {
			const s = document.createElement('script');
			s.src = src; s.async = true;
			s.onload = ok; s.onerror = function () { ko(new Error('script ' + src)); };
			document.head.appendChild(s);
		});
	}

	/** Charge ONNX Runtime et MODNet, une seule fois. */
	function preparerMatte() {
		if (matteur) { return Promise.resolve(matteur); }
		if (!chargementMatte) {
			chargementMatte = (async function () {
				if (!window.ort) { await chargerScript(ORT + 'ort.min.js'); }
				window.ort.env.wasm.wasmPaths = ORT;
				window.ort.env.wasm.numThreads = 1; // sans isolation cross-origin, pas de fils
				matteur = await window.ort.InferenceSession.create(MODNET, { executionProviders: ['wasm'], graphOptimizationLevel: 'all' });
				return matteur;
			})().catch(function (e) { chargementMatte = null; throw e; });
		}
		return chargementMatte;
	}

	/* --- Outils de calcul ------------------------------------------------- */

	const lin = (v) => (v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4));
	const srgb = (v) => (v <= 0.0031308 ? v * 12.92 : 1.055 * Math.pow(v, 1 / 2.4) - 0.055);

	/** Moyenne sur une fenetre carree de rayon r (bords : fenetre tronquee). */
	function boite(src, w, h, r, dst) {
		const out = dst || new Float32Array(w * h);
		if (r < 1) { out.set(src); return out; }
		const tmp = new Float32Array(w * h);
		const cum = new Float64Array(w + 1);
		for (let y = 0; y < h; y++) {
			const ligne = y * w;
			for (let x = 0; x < w; x++) { cum[x + 1] = cum[x] + src[ligne + x]; }
			for (let x = 0; x < w; x++) {
				const x0 = x - r < 0 ? 0 : x - r, x1 = x + r >= w ? w - 1 : x + r;
				tmp[ligne + x] = (cum[x1 + 1] - cum[x0]) / (x1 - x0 + 1);
			}
		}
		const somme = new Float64Array(w);
		let n = 0;
		for (let y = 0; y <= r && y < h; y++) { const l = y * w; for (let x = 0; x < w; x++) { somme[x] += tmp[l + x]; } n++; }
		for (let y = 0; y < h; y++) {
			const l = y * w;
			for (let x = 0; x < w; x++) { out[l + x] = somme[x] / n; }
			const ajout = y + r + 1, retrait = y - r;
			if (ajout < h) { const la = ajout * w; for (let x = 0; x < w; x++) { somme[x] += tmp[la + x]; } n++; }
			if (retrait >= 0) { const lr = retrait * w; for (let x = 0; x < w; x++) { somme[x] -= tmp[lr + x]; } n--; }
		}
		return out;
	}

	function produit(a, b) { const o = new Float32Array(a.length); for (let i = 0; i < a.length; i++) { o[i] = a[i] * b[i]; } return o; }

	/** Filtre guide en couleur : cale p sur les contours du guide I = {r, g, b}. */
	function filtreGuide(I, p, w, h, r, eps) {
		const n = w * h, B = (x) => boite(x, w, h, r);
		const mr = B(I.r), mg = B(I.g), mb = B(I.b), mp = B(p);
		const crp = B(produit(I.r, p)), cgp = B(produit(I.g, p)), cbp = B(produit(I.b, p));
		const vrr = B(produit(I.r, I.r)), vrg = B(produit(I.r, I.g)), vrb = B(produit(I.r, I.b));
		const vgg = B(produit(I.g, I.g)), vgb = B(produit(I.g, I.b)), vbb = B(produit(I.b, I.b));
		const ar = new Float32Array(n), ag = new Float32Array(n), ab = new Float32Array(n), bb = new Float32Array(n);
		for (let i = 0; i < n; i++) {
			const r_ = mr[i], g_ = mg[i], b_ = mb[i], p_ = mp[i];
			const cr = crp[i] - r_ * p_, cg = cgp[i] - g_ * p_, cb = cbp[i] - b_ * p_;
			const srr = vrr[i] - r_ * r_ + eps, srg = vrg[i] - r_ * g_, srb = vrb[i] - r_ * b_;
			const sgg = vgg[i] - g_ * g_ + eps, sgb = vgb[i] - g_ * b_, sbb = vbb[i] - b_ * b_ + eps;
			const i11 = sgg * sbb - sgb * sgb, i12 = srb * sgb - srg * sbb, i13 = srg * sgb - srb * sgg;
			const i22 = srr * sbb - srb * srb, i23 = srb * srg - srr * sgb, i33 = srr * sgg - srg * srg;
			const det = srr * i11 + srg * i12 + srb * i13;
			const a1 = (i11 * cr + i12 * cg + i13 * cb) / det;
			const a2 = (i12 * cr + i22 * cg + i23 * cb) / det;
			const a3 = (i13 * cr + i23 * cg + i33 * cb) / det;
			ar[i] = a1; ag[i] = a2; ab[i] = a3; bb[i] = p_ - a1 * r_ - a2 * g_ - a3 * b_;
		}
		const mar = B(ar), mag = B(ag), mab = B(ab), mbb = B(bb);
		const q = new Float32Array(n);
		for (let i = 0; i < n; i++) { q[i] = mar[i] * I.r[i] + mag[i] * I.g[i] + mab[i] * I.b[i] + mbb[i]; }
		return q;
	}

	/** Filtre guide par lui-meme, canal par canal : lissage qui garde les bords. */
	function lissageBords(c, w, h, r, eps) {
		const B = (x) => boite(x, w, h, r);
		const m = B(c), v = B(produit(c, c));
		const a = new Float32Array(c.length), b = new Float32Array(c.length);
		for (let i = 0; i < c.length; i++) {
			const va = v[i] - m[i] * m[i];
			a[i] = va / (va + eps); b[i] = m[i] - a[i] * m[i];
		}
		const ma = B(a), mb = B(b);
		const q = new Float32Array(c.length);
		for (let i = 0; i < c.length; i++) { q[i] = ma[i] * c[i] + mb[i]; }
		return q;
	}

	function versRgba(r, g, b, a) {
		const n = r.length, o = new Uint8Array(n * 4);
		for (let i = 0; i < n; i++) {
			o[i * 4] = r[i] * 255 + 0.5; o[i * 4 + 1] = g[i] * 255 + 0.5; o[i * 4 + 2] = b[i] * 255 + 0.5;
			o[i * 4 + 3] = a ? a[i] * 255 + 0.5 : 255;
		}
		return o;
	}
	// Uint8Array tronque les flottants : les valeurs sont deja dans 0..255,5 ; on borne
	function borner(x) { for (let i = 0; i < x.length; i++) { x[i] = x[i] < 0 ? 0 : (x[i] > 1 ? 1 : x[i]); } return x; }

	/* --- Image de travail ----------------------------------------------- */

	/** Reduit la photo a la resolution de travail : { w, h, ech, r, g, b } (0..1). */
	function travail(image) {
		const ech = Math.min(1, TRAVAIL_MAX / Math.max(image.width, image.height));
		const w = Math.max(1, Math.round(image.width * ech)), h = Math.max(1, Math.round(image.height * ech));
		const cv = new OffscreenCanvas(w, h);
		const g = cv.getContext('2d', { willReadFrequently: true });
		g.imageSmoothingQuality = 'high';
		g.drawImage(image, 0, 0, w, h);
		const d = g.getImageData(0, 0, w, h).data;
		const n = w * h, r = new Float32Array(n), v = new Float32Array(n), b = new Float32Array(n);
		for (let i = 0; i < n; i++) { r[i] = d[i * 4] / 255; v[i] = d[i * 4 + 1] / 255; b[i] = d[i * 4 + 2] / 255; }
		return { w, h, ech, r, g: v, b };
	}

	/** Flou de la clarte (contraste local) : trois boites, comme une gaussienne. */
	function textureFlou(t) {
		const rad = Math.max(2, Math.round(Math.max(t.w, t.h) * 0.012));
		const passe = (c) => boite(boite(boite(c, t.w, t.h, rad), t.w, t.h, rad), t.w, t.h, rad);
		return versRgba(passe(t.r), passe(t.g), passe(t.b));
	}

	/* --- Segmentation ------------------------------------------------------- */

	/* Dessine la region (x, y, w, h) de l'image sur nw x nh ; ce qui deborde de
	   la photo prolonge ses bords, plutot qu'un aplat que les modeles liraient mal. */
	function dessinerRegion(g, image, x, y, w, h, nw, nh) {
		const W = image.width, H = image.height, sx = nw / w, sy = nh / h;
		const ix0 = Math.max(0, x), iy0 = Math.max(0, y), ix1 = Math.min(W, x + w), iy1 = Math.min(H, y + h);
		const dx0 = (ix0 - x) * sx, dy0 = (iy0 - y) * sy, dx1 = (ix1 - x) * sx, dy1 = (iy1 - y) * sy;
		g.clearRect(0, 0, nw, nh);
		g.drawImage(image, ix0, iy0, ix1 - ix0, iy1 - iy0, dx0, dy0, dx1 - dx0, dy1 - dy0);
		if (dx0 > 0.5) { g.drawImage(image, ix0, iy0, 1, iy1 - iy0, 0, dy0, dx0 + 0.5, dy1 - dy0); }
		if (dx1 < nw - 0.5) { g.drawImage(image, ix1 - 1, iy0, 1, iy1 - iy0, dx1 - 0.5, dy0, nw - dx1 + 0.5, dy1 - dy0); }
		if (dy0 > 0.5) { g.drawImage(g.canvas, 0, Math.ceil(dy0) + 1, nw, 1, 0, 0, nw, dy0 + 0.5); }
		if (dy1 < nh - 0.5) { g.drawImage(g.canvas, 0, Math.floor(dy1) - 2, nw, 1, 0, dy1 - 0.5, nw, nh - dy1 + 0.5); }
	}

	/* Une passe de MediaPipe sur le carre (x, y, c) : les cinq premieres classes
	   (fond, cheveux, peau du corps, peau du visage, vetements). */
	function passe(seg, image, x, y, c) {
		const cv = new OffscreenCanvas(N, N);
		dessinerRegion(cv.getContext('2d'), image, x, y, c, c, N, N);
		const r = seg.segment(cv);
		const classes = r.confidenceMasks.slice(0, 5).map((m) => m.getAsFloat32Array().slice());
		const nw = r.confidenceMasks[0].width, nh = r.confidenceMasks[0].height;
		r.close();
		return { x, y, w: c, h: c, classes, nw, nh };
	}

	/* Une passe de MODNet sur la region (x, y, w, h), rendue en nw x nh
	   (multiples de 32) ; pixels ramenes a [-1, 1]. */
	async function passeMatte(session, image, x, y, w, h, nw, nh) {
		const cv = new OffscreenCanvas(nw, nh);
		const g = cv.getContext('2d', { willReadFrequently: true });
		g.imageSmoothingQuality = 'high';
		dessinerRegion(g, image, x, y, w, h, nw, nh);
		const d = g.getImageData(0, 0, nw, nh).data;
		const n = nw * nh, entree = new Float32Array(3 * n);
		for (let i = 0; i < n; i++) {
			entree[i] = d[i * 4] / 127.5 - 1; entree[n + i] = d[i * 4 + 1] / 127.5 - 1; entree[2 * n + i] = d[i * 4 + 2] / 127.5 - 1;
		}
		const ort = window.ort;
		const r = await session.run({ [session.inputNames[0]]: new ort.Tensor('float32', entree, [1, 3, nh, nw]) });
		return { x, y, w, h, classes: [Float32Array.from(r[session.outputNames[0]].data)], nw, nh };
	}

	/* Fond le resultat d'une passe dans les cartes de travail, avec un fondu
	   pres des bords de la region (sauf la premiere, qui couvre tout). */
	function fondre(cartes, p, t, premiere) {
		const { w, h, ech } = t, nw = p.nw, nh = p.nh;
		for (let j = 0; j < h; j++) {
			const Y = (j + 0.5) / ech;
			const v = (Y - p.y) / p.h * nh - 0.5;
			if (v < -0.5 || v > nh - 0.5) { continue; }
			for (let i = 0; i < w; i++) {
				const X = (i + 0.5) / ech;
				const u = (X - p.x) / p.w * nw - 0.5;
				if (u < -0.5 || u > nw - 0.5) { continue; }
				const d = Math.min((u + 0.5) / nw, (nw - 0.5 - u) / nw, (v + 0.5) / nh, (nh - 0.5 - v) / nh);
				const poids = premiere ? 1 : Math.min(1, d / 0.06);
				if (poids <= 0) { continue; }
				const u0 = Math.max(0, Math.min(nw - 1, Math.floor(u))), v0 = Math.max(0, Math.min(nh - 1, Math.floor(v)));
				const u1 = Math.min(nw - 1, u0 + 1), v1 = Math.min(nh - 1, v0 + 1);
				const fu = Math.max(0, Math.min(1, u - u0)), fv = Math.max(0, Math.min(1, v - v0));
				const k = j * w + i;
				for (let cl = 0; cl < p.classes.length; cl++) {
					const m = p.classes[cl];
					const val = (m[v0 * nw + u0] * (1 - fu) + m[v0 * nw + u1] * fu) * (1 - fv) + (m[v1 * nw + u0] * (1 - fu) + m[v1 * nw + u1] * fu) * fv;
					cartes[cl][k] = cartes[cl][k] * (1 - poids) + val * poids;
				}
			}
		}
	}

	function boiteEnglobante(carte, t, seuil, inverse) {
		let x0 = Infinity, y0 = Infinity, x1 = -1, y1 = -1;
		for (let j = 0; j < t.h; j++) {
			for (let i = 0; i < t.w; i++) {
				const v = carte[j * t.w + i];
				if (inverse ? 1 - v > seuil : v > seuil) {
					if (i < x0) { x0 = i; } if (i > x1) { x1 = i; } if (j < y0) { y0 = j; } if (j > y1) { y1 = j; }
				}
			}
		}
		if (x1 < 0) { return null; }
		return { x0: x0 / t.ech, y0: y0 / t.ech, x1: (x1 + 1) / t.ech, y1: (y1 + 1) / t.ech };
	}

	const multiple32 = (v) => Math.max(32, Math.round(v / 32) * 32);

	/* Opacite des cheveux d'apres les couleurs : les deux defauts de MODNet sur
	   un fond clair. Il garde opaques les jours entre les meches (taches
	   blanches sur un nouveau fond sombre) et laisse autour de la chevelure un
	   voile de faible opacite (halo clair).
	   - zone : les cheveux reconnus par MediaPipe, elargis d'environ 3 % de
	     l'image (sa carte, en 256 px, manque les meches du bord) ; ni la peau,
	     ni le visage, ni les vetements, qui ont parfois la couleur du fond ;
	   - un pixel de la couleur du fond (ecart sRGB sous 0,04, rien au-dela de
	     0,09) perd son opacite : c est un jour ou du voile, pas une meche ;
	   - pres du fond franc (a moins de 3 % de l image), un pixel clair est un
	     melange de meches et de fond : il prend l opacite de la projection
	     ci-dessous ; au coeur de la chevelure, un reflet brillant reste opaque ;
	   - une meche fine que MODNet a perdue retrouve son opacite par la
	     projection a = (I - B).(F - B) / |F - B|^2 (melange I = aF + (1 - a)B
	     de la couleur des meches F et du fond B, estimees autour du pixel, en
	     lumiere lineaire) ;
	   - si les cheveux et le fond ont des couleurs trop proches, rien ne change. */
	function affinerCheveux(t, alpha, cheveux, peauCorps, visage, vetements) {
		const { w, h } = t, n = w * h, grand = Math.max(w, h);
		const R = Math.max(4, Math.round(grand * 0.01));
		const ch = new Float32Array(n);
		for (let i = 0; i < n; i++) { ch[i] = cheveux[i] > 0.3 ? 1 : 0; }
		const zone = boite(ch, w, h, Math.max(6, Math.round(grand * 0.03)));
		// le fond : les pixels surement du fond, en moyenne locale (sRGB)
		const surFond = new Float32Array(n);
		for (let i = 0; i < n; i++) { surFond[i] = alpha[i] < 0.03 ? 1 : 0; }
		const B = fondEstime(t, surFond, R * 2);
		// pres du fond franc : la ou les meches se melent au fond (jours, bord de la chevelure)
		const presFond = boite(surFond, w, h, Math.max(6, Math.round(grand * 0.03)));
		// les meches : surement de la personne, reconnues comme cheveux, loin du fond
		const ecart = (i) => Math.hypot(t.r[i] - B[0][i], t.g[i] - B[1][i], t.b[i] - B[2][i]);
		const surMeche = new Float32Array(n);
		for (let i = 0; i < n; i++) { surMeche[i] = alpha[i] > 0.97 && cheveux[i] > 0.5 && ecart(i) > 0.2 ? 1 : 0; }
		const F = fondEstime(t, surMeche, R * 2);
		const lisse = (x, a, b) => { const u = Math.min(1, Math.max(0, (x - a) / (b - a))); return u * u * (3 - 2 * u); };
		for (let i = 0; i < n; i++) {
			if (zone[i] <= 0.005 || visage[i] > 0.2 || peauCorps[i] > 0.4 || vetements[i] > 0.3) { continue; }
			const b0 = lin(B[0][i]), b1 = lin(B[1][i]), b2 = lin(B[2][i]);
			const fb0 = lin(F[0][i]) - b0, fb1 = lin(F[1][i]) - b1, fb2 = lin(F[2][i]) - b2;
			const nfb = fb0 * fb0 + fb1 * fb1 + fb2 * fb2;
			const confiance = lisse(Math.sqrt(nfb), 0.08, 0.25);
			if (confiance <= 0) { continue; }
			const ib0 = lin(t.r[i]) - b0, ib1 = lin(t.g[i]) - b1, ib2 = lin(t.b[i]) - b2;
			const ac = Math.min(1, Math.max(0, ((ib0 * fb0 + ib1 * fb1 + ib2 * fb2) / nfb - 0.03) / 0.94));
			if (ac < alpha[i]) {
				// couleur du fond, ou pixel mele de fond pres du fond franc ; au coeur de la
				// chevelure, un reflet clair reste opaque
				const poids = Math.max(1 - lisse(ecart(i), 0.04, 0.09), 0.9 * lisse(presFond[i], 0, 0.02));
				alpha[i] += (ac - alpha[i]) * confiance * poids;
			} else {
				// une meche perdue tranche sur le fond ; un mur a deux teintes, non
				alpha[i] += (ac - alpha[i]) * confiance * 0.85 * lisse(ecart(i), 0.1, 0.2);
			}
		}
	}

	/* Trous dans la personne : MODNet prend parfois les reflets clairs des
	   cheveux (une boucle qui renvoie la couleur du mur) pour du fond. Une zone
	   de fond enfermee dans la personne (sans passage vers le bord de l'image)
	   lui est rendue si elle est petite, ou si MediaPipe y voit aussi la
	   personne ; un vrai jour, entre le bras et le corps, reste du fond. Quatre
	   pixels autour du trou suivent, pour ne pas laisser d anneau. */
	function boucherTrous(alpha, mp, t) {
		const { w, h } = t, n = w * h;
		const bas = (i) => alpha[i] < 0.5;
		const vu = new Uint8Array(n); // 1 : fond relie au bord, 2 : trou deja visite
		const pile = new Int32Array(n);
		let sp = 0;
		const pousser = (i, v) => { if (bas(i) && !vu[i]) { vu[i] = v; pile[sp++] = i; } };
		for (let x = 0; x < w; x++) { pousser(x, 1); pousser((h - 1) * w + x, 1); }
		for (let y = 0; y < h; y++) { pousser(y * w, 1); pousser(y * w + w - 1, 1); }
		const voisins = (i, f) => {
			const x = i % w;
			if (x > 0) { f(i - 1); } if (x < w - 1) { f(i + 1); }
			if (i >= w) { f(i - w); } if (i < n - w) { f(i + w); }
		};
		while (sp) { voisins(pile[--sp], (j) => pousser(j, 1)); }
		const remplir = new Float32Array(n); // valeur de remplissage, 0 = rien
		const petit = n * 0.0006;
		let combien = 0;
		for (let i0 = 0; i0 < n; i0++) {
			if (!bas(i0) || vu[i0]) { continue; }
			const membres = [];
			let sommeMp = 0;
			pousser(i0, 2);
			while (sp) {
				const i = pile[--sp];
				membres.push(i); sommeMp += mp[i];
				voisins(i, (j) => pousser(j, 2));
			}
			const moyMp = sommeMp / membres.length;
			if (membres.length < petit || moyMp > 0.6) {
				combien++;
				for (const i of membres) { remplir[i] = 1; }
			}
		}
		if (!combien) { return 0; }
		// l anneau : quatre pixels autour (le bord du trou est flou), a la meme valeur
		for (let tour = 0; tour < 4; tour++) {
			const avant = remplir.slice();
			for (let i = 0; i < n; i++) {
				if (avant[i] > 0) { continue; }
				let m = 0;
				voisins(i, (j) => { if (avant[j] > m) { m = avant[j]; } });
				if (m > 0) { remplir[i] = m; }
			}
		}
		for (let i = 0; i < n; i++) { if (remplir[i] > alpha[i]) { alpha[i] = remplir[i]; } }
		return combien;
	}

	/**
	 * Detoure la personne. Renvoie les cartes de travail :
	 * { alpha, peau, visage, tete, cheveux, peauCorps, vetements, personne,
	 *   teteBoite, visageBoite, fin }
	 * (boites en pixels source ; fin : l'opacite vient de MODNet).
	 */
	async function segmenter(image, t, signaler) {
		// les deux modeles se telechargent en meme temps ; MODNet peut manquer
		const attenteMatte = preparerMatte().catch(function (e) { console.warn('MODNet indisponible', e); return null; });
		const seg = await preparer();
		const W = image.width, H = image.height, n = t.w * t.h;
		const cartes = [new Float32Array(n).fill(1), new Float32Array(n), new Float32Array(n), new Float32Array(n), new Float32Array(n)];
		const attendre = () => new Promise((r) => setTimeout(r, 0));
		signaler && signaler('Détection de la personne…');
		await attendre();
		const c1 = Math.max(W, H);
		fondre(cartes, passe(seg, image, (W - c1) / 2, (H - c1) / 2, c1), t, true);
		const pers = boiteEnglobante(cartes[0], t, 0.5, true);
		if (pers) {
			const c2 = Math.max(pers.x1 - pers.x0, pers.y1 - pers.y0) * 1.08;
			if (c2 < c1 * 0.92) {
				await attendre();
				fondre(cartes, passe(seg, image, (pers.x0 + pers.x1 - c2) / 2, (pers.y0 + pers.y1 - c2) / 2, c2), t, false);
			}
		}
		const tete = new Float32Array(n), peau = new Float32Array(n);
		for (let i = 0; i < n; i++) {
			tete[i] = Math.min(1, cartes[1][i] + cartes[3][i]);
			peau[i] = Math.min(1, cartes[2][i] + cartes[3][i]);
		}
		const teteBoite = boiteEnglobante(tete, t, 0.5, false);

		let alpha = null;
		signaler && signaler('Détourage fin des cheveux…');
		const session = await attenteMatte;
		if (session) {
			try {
				const s = MATTE / Math.min(W, H);
				const carte = [new Float32Array(n)];
				fondre(carte, await passeMatte(session, image, 0, 0, W, H, multiple32(W * s), multiple32(H * s)), t, true);
				// la tete a part : les cheveux en au moins 1,4 fois plus de pixels
				if (teteBoite) {
					const c3 = Math.max(teteBoite.x1 - teteBoite.x0, teteBoite.y1 - teteBoite.y0) * 1.4;
					if (MATTE / c3 > 1.4 * s) {
						await attendre();
						fondre(carte, await passeMatte(session, image, (teteBoite.x0 + teteBoite.x1 - c3) / 2, (teteBoite.y0 + teteBoite.y1 - c3) / 2, c3, c3, MATTE, MATTE), t, false);
					}
				}
				alpha = carte[0];
				// le fond garde un ou deux pour cent d'opacite : ramene a zero
				for (let i = 0; i < n; i++) { alpha[i] = Math.min(1, Math.max(0, (alpha[i] - 0.02) / 0.96)); }
				const mp = new Float32Array(n);
				for (let i = 0; i < n; i++) { mp[i] = Math.min(1, Math.max(0, (1 - cartes[0][i] - 0.15) / 0.7)); }
				/* Le coeur de la personne selon MediaPipe, loin de son contour
				   (moins precis que celui de MODNet) : opaque. MODNet rend parfois
				   semi-transparente une boucle dont les reflets ont la couleur
				   du mur. Le contour, lui, reste celui de MODNet. */
				const coeur = new Float32Array(n);
				for (let i = 0; i < n; i++) { coeur[i] = mp[i] > 0.85 ? 1 : 0; }
				const loin = boite(coeur, t.w, t.h, Math.max(4, Math.round(Math.max(t.w, t.h) * 0.012)));
				for (let i = 0; i < n; i++) {
					const c = (loin[i] - 0.94) / 0.06;
					if (c > 0 && c > alpha[i]) { alpha[i] = Math.min(1, c); }
				}
				boucherTrous(alpha, mp, t);
				signaler && signaler('Nettoyage des cheveux…');
				await attendre();
				affinerCheveux(t, alpha, cartes[1], cartes[2], cartes[3], cartes[4]);
			} catch (e) {
				console.warn('MODNet a echoue, detourage par MediaPipe', e);
				alpha = null;
			}
		}
		const fin = !!alpha;
		if (!alpha) {
			signaler && signaler('Calage du contour…');
			await attendre();
			// le modele laisse au fond une confiance de 0,08 a 0,1 de personne : niveaux
			// ramenes a 0..1, sinon 8 % de l'ancien fond transparaitrait partout
			const brut = new Float32Array(n);
			for (let i = 0; i < n; i++) { brut[i] = Math.min(1, Math.max(0, (1 - cartes[0][i] - 0.15) / 0.7)); }
			const rayon = Math.max(3, Math.round(Math.max(t.w, t.h) * 0.006));
			alpha = borner(filtreGuide(t, brut, t.w, t.h, rayon, 2e-4));
		}
		return {
			alpha, peau: borner(boite(peau, t.w, t.h, 2)), visage: cartes[3], tete,
			// pour les masques de l'atelier (Cheveux, Peau du corps, Vetements)
			cheveux: cartes[1], peauCorps: cartes[2], vetements: cartes[4],
			personne: boiteEnglobante(alpha, t, 0.5, false), teteBoite, fin,
			visageBoite: boiteEnglobante(cartes[3], t, 0.5, false),
		};
	}

	/* --- Textures qui dependent du detourage -------------------------------- */

	/* Couleur du fond d'origine, estimee sous la personne a partir des pixels
	   de fond voisins (moyenne ponderee), a deux echelles avec repli. */
	function fondEstime(t, poids, rayon) {
		const { w, h } = t, n = w * h;
		const den = boite(poids, w, h, rayon);
		const num = [t.r, t.g, t.b].map((c) => boite(produit(c, poids), w, h, rayon));
		const den2 = boite(poids, w, h, rayon * 4);
		const num2 = [t.r, t.g, t.b].map((c) => boite(produit(c, poids), w, h, rayon * 4));
		let sp = 0; const moy = [0, 0, 0];
		for (let i = 0; i < n; i++) { sp += poids[i]; moy[0] += t.r[i] * poids[i]; moy[1] += t.g[i] * poids[i]; moy[2] += t.b[i] * poids[i]; }
		const glob = sp > 0 ? moy.map((x) => x / sp) : [0.9, 0.9, 0.9];
		const out = [new Float32Array(n), new Float32Array(n), new Float32Array(n)];
		for (let i = 0; i < n; i++) {
			for (let c = 0; c < 3; c++) {
				let v;
				if (den[i] > 0.04) { v = num[c][i] / den[i]; }
				else if (den2[i] > 0.01) {
					const m = den2[i] > 0.04 ? 1 : (den2[i] - 0.01) / 0.03;
					v = (num2[c][i] / den2[i]) * m + glob[c] * (1 - m);
					if (den[i] > 0.005) { const f = (den[i] - 0.005) / 0.035; v = (num[c][i] / den[i]) * f + v * (1 - f); }
				} else { v = glob[c]; }
				out[c][i] = v;
			}
		}
		return out;
	}

	/* Couleur du premier plan seul, sans l'ancien fond, dans les pixels mi-
	   personne mi-fond (meches de cheveux) : estimation multi-niveaux de
	   Germer, Uelwer, Conrad et Harmeling (2020), celle de PyMatting. A chaque
	   pixel, F et B verifient au mieux I = aF + (1 - a)B tout en restant lisses
	   la ou l'opacite varie peu ; de la plus petite image a la plus grande. En
	   lumiere lineaire, comme le compositage du moteur. */
	function premierPlan(t, alpha) {
		const w0 = t.w, h0 = t.h, n0 = w0 * h0;
		const src = [t.r, t.g, t.b].map((c) => { const o = new Float32Array(n0); for (let i = 0; i < n0; i++) { o[i] = lin(c[i]); } return o; });
		const EPS = 5e-3, POIDS_GRADIENT = 1;
		const niveaux = Math.max(1, Math.ceil(Math.log2(Math.max(w0, h0))));
		const moyenne = src.map((c) => { let s = 0; for (let i = 0; i < n0; i++) { s += c[i]; } return s / n0; });
		let F = moyenne.map((m) => Float32Array.of(m)), B = moyenne.map((m) => Float32Array.of(m));
		let pw = 1, ph = 1;
		const reduire = (c, cw, chh, w, h) => { // au plus proche, comme PyMatting
			const o = new Float32Array(w * h);
			for (let y = 0; y < h; y++) {
				const sy = Math.min(chh - 1, Math.floor(y * chh / h)) * cw;
				for (let x = 0; x < w; x++) { o[y * w + x] = c[sy + Math.min(cw - 1, Math.floor(x * cw / w))]; }
			}
			return o;
		};
		for (let niv = 0; niv <= niveaux; niv++) {
			const w = Math.max(1, Math.round(Math.pow(w0, niv / niveaux))), h = Math.max(1, Math.round(Math.pow(h0, niv / niveaux)));
			const I = niv === niveaux ? src : src.map((c) => reduire(c, w0, h0, w, h));
			const A = niv === niveaux ? alpha : reduire(alpha, w0, h0, w, h);
			F = F.map((c) => reduire(c, pw, ph, w, h));
			B = B.map((c) => reduire(c, pw, ph, w, h));
			pw = w; ph = h;
			const tours = w <= 32 && h <= 32 ? 10 : 2;
			const [Fr, Fg, Fb] = F, [Br, Bg, Bb] = B, [Ir, Ig, Ib] = I;
			for (let k = 0; k < tours; k++) {
				for (let y = 0; y < h; y++) {
					for (let x = 0; x < w; x++) {
						const i = y * w + x, a0 = A[i], a1 = 1 - a0;
						let a00 = a0 * a0, a11 = a1 * a1;
						const a01 = a0 * a1;
						let fr = 0, fg = 0, fb = 0, br = 0, bg = 0, bb = 0;
						for (let v = 0; v < 4; v++) {
							const j = v === 0 ? (x > 0 ? i - 1 : i) : v === 1 ? (x < w - 1 ? i + 1 : i) : v === 2 ? (y > 0 ? i - w : i) : (y < h - 1 ? i + w : i);
							const da = EPS + POIDS_GRADIENT * Math.abs(a0 - A[j]);
							a00 += da; a11 += da;
							fr += da * Fr[j]; fg += da * Fg[j]; fb += da * Fb[j];
							br += da * Br[j]; bg += da * Bg[j]; bb += da * Bb[j];
						}
						const inv = 1 / (a00 * a11 - a01 * a01);
						const b0r = a0 * Ir[i] + fr, b1r = a1 * Ir[i] + br;
						const b0g = a0 * Ig[i] + fg, b1g = a1 * Ig[i] + bg;
						const b0b = a0 * Ib[i] + fb, b1b = a1 * Ib[i] + bb;
						Fr[i] = Math.min(1, Math.max(0, inv * (a11 * b0r - a01 * b1r)));
						Fg[i] = Math.min(1, Math.max(0, inv * (a11 * b0g - a01 * b1g)));
						Fb[i] = Math.min(1, Math.max(0, inv * (a11 * b0b - a01 * b1b)));
						Br[i] = Math.min(1, Math.max(0, inv * (a00 * b1r - a01 * b0r)));
						Bg[i] = Math.min(1, Math.max(0, inv * (a00 * b1g - a01 * b0g)));
						Bb[i] = Math.min(1, Math.max(0, inv * (a00 * b1b - a01 * b0b)));
					}
				}
			}
		}
		return F.map((c) => { for (let i = 0; i < c.length; i++) { c[i] = srgb(c[i]); } return c; });
	}

	/**
	 * Textures a envoyer au moteur : masque (R alpha, G peau, B pinceau),
	 * lisse (peau lissee), premierPlan (couleur des meches sans l ancien fond),
	 * fondFlou (la personne effacee, floutee).
	 */
	function textures(t, s, retouches) {
		const { w, h } = t, n = w * h, grand = Math.max(w, h);
		const poids = new Float32Array(n);
		for (let i = 0; i < n; i++) { const a = s.alpha[i]; poids[i] = a < 0.06 ? 1 : Math.max(0, 1 - (a - 0.06) / 0.18); }
		const pp = premierPlan(t, s.alpha);
		// fond flou : la personne effacee (remplacee par l'estimation), puis floutee
		const large = fondEstime(t, poids, Math.max(8, Math.round(grand * 0.04)));
		const rf = Math.max(4, Math.round(grand * 0.018));
		const flou = [t.r, t.g, t.b].map((c, k) => {
			const rempli = new Float32Array(n);
			for (let i = 0; i < n; i++) { rempli[i] = c[i] * poids[i] + large[k][i] * (1 - poids[i]); }
			return boite(boite(boite(rempli, w, h, rf), w, h, rf), w, h, rf);
		});
		// peau lissee : rayon a l'echelle du visage
		const largeurVisage = s.teteBoite ? (s.teteBoite.x1 - s.teteBoite.x0) * t.ech : grand * 0.2;
		const rl = Math.max(2, Math.round(largeurVisage * 0.03));
		const lisse = [t.r, t.g, t.b].map((c) => borner(lissageBords(c, w, h, rl, 0.0025)));
		return {
			masque: masqueRgba(s, retouches, n),
			lisse: versRgba(lisse[0], lisse[1], lisse[2]),
			premierPlan: versRgba(pp[0], pp[1], pp[2]),
			fondFlou: versRgba(borner(flou[0]), borner(flou[1]), borner(flou[2])),
		};
	}

	function masqueRgba(s, retouches, n) {
		const o = new Uint8Array(n * 4);
		for (let i = 0; i < n; i++) {
			o[i * 4] = s.alpha[i] * 255 + 0.5;
			o[i * 4 + 1] = s.peau[i] * 255 + 0.5;
			o[i * 4 + 2] = retouches ? retouches[i] : 128;
			o[i * 4 + 3] = 255;
		}
		return o;
	}

	/* --- Mesures pour le preregle ----------------------------------------- */

	function moyenneLineaire(t, garder) {
		const somme = [0, 0, 0]; let k = 0;
		for (let i = 0; i < t.r.length; i++) {
			if (!garder(i)) { continue; }
			somme[0] += lin(t.r[i]); somme[1] += lin(t.g[i]); somme[2] += lin(t.b[i]); k++;
		}
		return k ? { rgb: somme.map((x) => x / k), n: k } : null;
	}

	/**
	 * Mesures : couleur moyenne de la peau du visage (lumiere lineaire), du
	 * fond, et luminance haute de la personne.
	 */
	function mesurer(t, s) {
		const n = t.r.length;
		const yp = (i) => 0.2126 * t.r[i] + 0.7152 * t.g[i] + 0.0722 * t.b[i];
		// le visage sans les yeux, les sourcils ni les reflets des lunettes : 15 % les plus sombres
		// et 3 % les plus clairs ecartes
		const valeurs = [];
		for (let i = 0; i < n; i++) { if (s.visage[i] > 0.6 && s.alpha[i] > 0.8) { valeurs.push(yp(i)); } }
		let peau = null;
		if (valeurs.length > 150) {
			const tri = Float32Array.from(valeurs).sort();
			const bas = tri[Math.floor(tri.length * 0.15)], haut = tri[Math.floor(tri.length * 0.97)];
			peau = moyenneLineaire(t, (i) => s.visage[i] > 0.6 && s.alpha[i] > 0.8 && yp(i) >= bas && yp(i) <= haut);
		}
		if (!peau) { peau = moyenneLineaire(t, (i) => s.peau[i] > 0.6 && s.alpha[i] > 0.8); }
		const fond = moyenneLineaire(t, (i) => s.alpha[i] < 0.05);
		const lum = [];
		for (let i = 0; i < n; i += 3) { if (s.alpha[i] > 0.9) { lum.push(yp(i)); } }
		const triLum = Float32Array.from(lum).sort();
		return {
			peau: peau && peau.n > 50 ? peau.rgb : null,
			fond: fond && fond.n > 200 ? fond.rgb : null,
			hautPersonne: triLum.length ? triLum[Math.floor(triLum.length * 0.99)] : 0.9,
		};
	}

	window.RetoucheMasque = {
		preparer, travail, textureFlou, segmenter, textures, masqueRgba, mesurer,
		get pret() { return !!segmenteur; },
		_boite: boite, _filtreGuide: filtreGuide, _fondEstime: fondEstime,
	};
})();
