/* =============================================================================
   Retouche photo — moteur de rendu (WebGL 2) — src/retouche-photo.php

   Un seul programme de fragments developpe la photo pixel par pixel :
   balance des blancs et exposition en lumiere lineaire, courbe des tons,
   saturation et TSL en espace perceptif, puis le fond (remplace, flou,
   transparent) d'apres le masque de la personne, et enfin le vignetage.

   Les textures « de travail » (flou de clarte, lissage de la peau, fond
   estime, masque) sont calculees en JavaScript a une resolution reduite
   (src/js/retouche-photo.masque.js) et suivent la photo source par ses
   coordonnees normalisees : elles se superposent quelle que soit la taille
   de rendu.

   Le meme rendu sert a l'apercu (dans le canevas, a la taille de l'ecran),
   a l'histogramme et a l'export, en tuiles de 2048 px a la taille reelle.

   Masques (retouches locales, jusqu'a 8) : chaque masque a sa zone et ses
   reglages (exposition, contraste, hautes lumieres, ombres, blancs, noirs,
   temperature, teinte, saturation, clarte, nettete). Dans chaque pixel, les
   reglages des masques, ponderes par leur zone, s'ajoutent aux reglages
   globaux. La zone vient d'un canal des textures uCalquesA et uCalquesB
   (masques 1 a 4, 5 a 8), calculees en JavaScript : detourage et pinceau
   (type 1), ou pinceau seul en complement d'un degrade lineaire (type 2) ou
   radial (type 3), calcules ici. Coordonnees des degrades : celles de la
   photo source, normalisees ; ils suivent recadrage et rotation.
   ========================================================================== */
(function () {
	'use strict';

	const VERTEX = `#version 300 es
in vec2 aPos;
void main() { gl_Position = vec4(aPos, 0.0, 1.0); }`;

	const FRAGMENT = `#version 300 es
precision highp float;
uniform sampler2D uSrc, uFlou, uLisse, uPremierPlan, uFondFlou, uMasque, uImageFond, uLut;
uniform vec4 uVue;          // sortie = (fragment - xy) / zw
uniform vec4 uCadre;        // recadrage, en coordonnees orientees normalisees
uniform mat2 uOrient;       // oriente -> source
uniform vec2 uTexel;        // 1 / taille de la source
uniform float uRatio;       // largeur / hauteur de la sortie
uniform vec3 uGains;
uniform float uExpo, uContraste, uHL, uOmbres, uBlancs, uNoirs;
uniform float uVibrance, uSaturation, uClarte, uNettete, uLissage, uVignette;
uniform vec3 uTSL[8];
uniform vec3 uVirageO, uVirageH;   // virage partiel : couleur ajoutee aux ombres, aux hautes lumieres
uniform vec3 uPeauGains;        // correction propre a la peau (masque de peau du detourage)
uniform float uPeauSat;
uniform int uTSLActif;
uniform int uFondMode;      // 0 original, 1 couleur ou degrade, 2 flou, 3 image, 4 transparent
uniform vec3 uFondA, uFondB;
uniform vec2 uFondCentre;   // fond de studio : ou tombe la lumiere (sortie normalisee), derriere la tete
uniform float uFondFlouForce, uContour, uDurete;
uniform int uDecontam, uMasqueOk;
uniform vec2 uImageFondEchelle, uImageFondDecal;
uniform float uAvant;       // abscisse de la coupure avant | apres, en sortie normalisee (< 0 : aucune)
uniform int uFiltreActif;      // filtre (src/js/retouche-photo.filtres.js) : courbes en table uLut
uniform float uFiltreForce, uFiltreSat, uFiltreGrain, uGrainEchelle;
uniform vec2 uTailleSortie;   // en pixels, pour un grain a l echelle de l export
uniform float uBiais;         // biais de niveau de detail de la source : negatif = plus net (reduction)
uniform float uReflet;        // 0..1 : force de la correction des reflets bleus des lunettes
uniform vec4 uVisage;         // boite du visage dans la source (UV : x0, y0, x1, y1) ; x1 <= x0 : aucun visage trouve
uniform float uDeflou;        // 0..1 : force du defloutage
uniform float uDeflouRayon;   // rayon du flou a corriger, en pixels de la photo
uniform int uVoirMasque, uExport;
uniform vec3 uFondVue;
// masques : zone (canal de uCalquesA / uCalquesB, degrade) et reglages locaux
uniform sampler2D uCalquesA, uCalquesB;
uniform int uNbMasques;
uniform int uMType[8];        // 0 eteint, 1 carte, 2 degrade lineaire, 3 degrade radial
uniform int uMInverse[8];
uniform int uMLimite[8];      // 0 toute la photo, 1 la personne seule, 2 l arriere-plan seul
uniform float uMQuantite[8];
uniform vec4 uMGeoA[8];       // lineaire : A (plein effet), B (aucun) ; radial : centre, contour progressif
uniform vec4 uMGeoB[8];       // radial : rayons (en hauteurs de la source), cos et sin de l angle
uniform vec4 uML1[8];         // exposition, contraste, hautes lumieres, ombres
uniform vec4 uML2[8];         // blancs, noirs, temperature, teinte
uniform vec4 uML3[8];         // saturation, clarte, nettete
uniform int uMVoir;           // masque dont la zone se voit en rouge, -1 aucun
uniform float uRatioSrc;      // largeur / hauteur de la source
out vec4 sortie;

// reglages du pixel : les globaux, plus ceux des masques qui le couvrent
float gContraste, gHL, gOmbres, gBlancs, gNoirs, gSat;

float l1(float c) { return c <= 0.04045 ? c / 12.92 : pow((c + 0.055) / 1.055, 2.4); }
vec3 s2l(vec3 c) { return vec3(l1(c.r), l1(c.g), l1(c.b)); }
float s1(float c) { c = max(c, 0.0); return c <= 0.0031308 ? c * 12.92 : 1.055 * pow(c, 1.0 / 2.4) - 0.055; }
vec3 l2s(vec3 c) { return vec3(s1(c.r), s1(c.g), s1(c.b)); }
float luma(vec3 c) { return dot(c, vec3(0.2126, 0.7152, 0.0722)); }
float cloche(float x, float c, float l) { float d = (x - c) / l; return exp(-d * d); }

vec3 rgb2hsv(vec3 c) {
	vec4 K = vec4(0.0, -1.0 / 3.0, 2.0 / 3.0, -1.0);
	vec4 p = mix(vec4(c.bg, K.wz), vec4(c.gb, K.xy), step(c.b, c.g));
	vec4 q = mix(vec4(p.xyw, c.r), vec4(c.r, p.yzx), step(p.x, c.r));
	float d = q.x - min(q.w, q.y);
	return vec3(abs(q.z + (q.w - q.y) / (6.0 * d + 1e-10)), d / (q.x + 1e-10), q.x);
}
vec3 hsv2rgb(vec3 c) {
	vec3 p = abs(fract(c.xxx + vec3(1.0, 2.0 / 3.0, 1.0 / 3.0)) * 6.0 - 3.0);
	return c.z * mix(vec3(1.0), clamp(p - 1.0, 0.0, 1.0), c.y);
}

// Courbe des tons, sur la luminance perceptive
float courbe(float y) {
	float t = y;
	t += gNoirs * 0.12 * (1.0 - smoothstep(0.0, 0.32, t));
	t += gOmbres * 0.20 * cloche(t, 0.30, 0.17);
	t += gHL * 0.20 * cloche(t, 0.76, 0.20);
	t += gBlancs * 0.14 * smoothstep(0.45, 1.0, t);
	float u = clamp(t, 0.0, 1.0);
	if (gContraste >= 0.0) {
		t = mix(t, u * u * (3.0 - 2.0 * u) + (t - u), gContraste * 0.85);
	} else {
		t = mix(t, 0.46 + (t - 0.46) * 0.55, -gContraste);
	}
	return max(t, 0.0);
}

// Gains de la balance des blancs, comme gains() en JavaScript (t, n : -1..1)
vec3 gainsBB(float t, float n) {
	float kt = t * 1.5, kn = n * 1.5;
	vec3 l = exp2(vec3(0.5 * kt + 0.5 * kn, 0.5 * kt - kn, -kt + 0.5 * kn));
	return l / dot(l, vec3(0.2126, 0.7152, 0.0722));
}

// Part d un pixel dans la zone d un masque (0..1), avant son intensite
float zoneMasque(int i, vec2 q, vec4 ca, vec4 cb) {
	float v = i < 4 ? ca[i] : cb[i - 4];
	if (uMType[i] == 1) { return v; }
	vec2 d = (q - uMGeoA[i].xy) * vec2(uRatioSrc, 1.0);
	float g;
	if (uMType[i] == 2) {
		vec2 ab = (uMGeoA[i].zw - uMGeoA[i].xy) * vec2(uRatioSrc, 1.0);
		g = 1.0 - smoothstep(0.0, 1.0, dot(d, ab) / max(dot(ab, ab), 1e-8));
	} else {
		float c = uMGeoB[i].z, s = uMGeoB[i].w;
		vec2 e = vec2(c * d.x + s * d.y, -s * d.x + c * d.y) / max(uMGeoB[i].xy, vec2(1e-4));
		float pl = clamp(uMGeoA[i].z, 0.02, 1.0);
		g = 1.0 - smoothstep(1.0 - pl, 1.0, length(e));
	}
	if (uMInverse[i] == 1) { g = 1.0 - g; }
	// le pinceau du masque : 128 neutre, au-dessus il ajoute, au-dessous il retire
	return clamp(g + (v * 255.0 - 128.0) / 127.0, 0.0, 1.0);
}

float genou(float x) { // compression douce des hautes lumieres
	const float k = 0.9;
	return x <= k ? x : k + (1.0 - k) * (1.0 - exp(-(x - k) / (1.0 - k)));
}

// Developpe une couleur lineaire (deja equilibree et exposee) en sRGB
vec3 developper(vec3 lin) {
	vec3 c = pow(max(lin, 0.0), vec3(1.0 / 2.2));
	float y = luma(c);
	float yn = courbe(y);
	c = y > 1e-4 ? c * (yn / y) : c + (yn - y);
	// compression sur le canal le plus fort : la teinte est gardee
	float mx = max(c.r, max(c.g, c.b));
	if (mx > 0.9) { c *= genou(mx) / mx; }
	float g = luma(c);
	vec3 hsv = rgb2hsv(clamp(c, 0.0, 1.0));
	// la vibrance epargne les couleurs deja saturees et les tons chair (autour de 25 degres)
	float chair = cloche(hsv.x * 360.0, 25.0, 22.0) * smoothstep(0.08, 0.3, hsv.y);
	float vib = uVibrance * (1.0 - hsv.y) * (uVibrance > 0.0 ? (1.0 - 0.7 * chair) : 1.0);
	c = g + (c - g) * max(0.0, (1.0 + gSat) * (1.0 + vib));
	if (uTSLActif == 1) {
		vec3 h = rgb2hsv(clamp(c, 0.0, 1.0));
		float deg = h.x * 360.0;
		float centres[8] = float[8](0.0, 30.0, 60.0, 120.0, 180.0, 235.0, 275.0, 320.0);
		float largeurs[8] = float[8](28.0, 26.0, 32.0, 50.0, 45.0, 42.0, 38.0, 40.0);
		vec3 acc = vec3(0.0); float somme = 0.0;
		for (int i = 0; i < 8; i++) {
			float d = abs(deg - centres[i]); d = min(d, 360.0 - d);
			float w = max(0.0, 1.0 - d / largeurs[i]);
			w = w * w * (3.0 - 2.0 * w);
			acc += w * uTSL[i]; somme += w;
		}
		if (somme > 0.0) {
			acc /= max(somme, 1.0);
			float poids = smoothstep(0.02, 0.18, h.y);
			h.x = fract(h.x + acc.x * poids * 30.0 / 360.0 + 1.0);
			h.y = clamp(h.y * (1.0 + acc.y * poids), 0.0, 1.0);
			h.z = clamp(h.z * (1.0 + acc.z * 0.45 * poids), 0.0, 1.0);
			c = hsv2rgb(h);
		}
	}
	// virage partiel
	float yv = luma(clamp(c, 0.0, 1.0));
	c += uVirageO * (1.0 - smoothstep(0.0, 0.55, yv)) + uVirageH * smoothstep(0.45, 1.0, yv);
	return clamp(c, 0.0, 1.0);
}

float alphaMasque(vec4 m) {
	if (uMasqueOk == 0) return 1.0;
	float a = clamp((m.r - 0.5 + uContour) * uDurete + 0.5, 0.0, 1.0);
	return clamp(a + (m.b * 255.0 - 128.0) / 127.0, 0.0, 1.0);
}

float bruit(vec2 p) { return fract(sin(dot(p, vec2(12.9898, 78.233))) * 43758.5453); }

// Flou gaussien 3 x 3 de pas r (en UV de la source), en lumiere lineaire, et bornes (min, max) du voisinage.
vec3 flou9(vec2 q, vec2 r, out vec3 mn, out vec3 mx) {
	vec3 c = s2l(textureLod(uSrc, q, 0.0).rgb);
	vec3 a = s2l(textureLod(uSrc, q + vec2(r.x, 0.0), 0.0).rgb), b = s2l(textureLod(uSrc, q - vec2(r.x, 0.0), 0.0).rgb);
	vec3 d = s2l(textureLod(uSrc, q + vec2(0.0, r.y), 0.0).rgb), e = s2l(textureLod(uSrc, q - vec2(0.0, r.y), 0.0).rgb);
	vec3 f = s2l(textureLod(uSrc, q + r, 0.0).rgb), g = s2l(textureLod(uSrc, q - r, 0.0).rgb);
	vec3 h = s2l(textureLod(uSrc, q + vec2(r.x, -r.y), 0.0).rgb), k = s2l(textureLod(uSrc, q + vec2(-r.x, r.y), 0.0).rgb);
	mn = min(min(min(c, a), min(b, d)), min(min(e, f), min(g, min(h, k))));
	mx = max(max(max(c, a), max(b, d)), max(max(e, f), max(g, max(h, k))));
	return (c * 4.0 + (a + b + d + e) * 2.0 + f + g + h + k) / 16.0;
}


// Echantillon de la photo pour un pixel de SORTIE. Agrandissement (un pixel de sortie plus petit qu'un pixel de la source) : bicubique
// (Catmull-Rom, 9 lectures) au lieu du bilineaire, nettement plus net. Reduction : filtre trilineaire avec un leger biais de nettete.
vec3 echantillon(vec2 q, vec2 st) {
	vec2 rapport = st / uTexel;
	if (max(rapport.x, rapport.y) < 0.98) {
		vec2 pos = q / uTexel;
		vec2 t1 = floor(pos - 0.5) + 0.5;
		vec2 f = pos - t1;
		vec2 w0 = f * (-0.5 + f * (1.0 - 0.5 * f));
		vec2 w1 = 1.0 + f * f * (-2.5 + 1.5 * f);
		vec2 w2 = f * (0.5 + f * (2.0 - 1.5 * f));
		vec2 w3 = f * f * (-0.5 + 0.5 * f);
		vec2 w12 = w1 + w2;
		vec2 o12 = w2 / w12;
		vec2 a = (t1 - 1.0) * uTexel, b = (t1 + o12) * uTexel, c = (t1 + 2.0) * uTexel;
		vec3 r = texture(uSrc, vec2(a.x, a.y)).rgb * w0.x * w0.y
		       + texture(uSrc, vec2(b.x, a.y)).rgb * w12.x * w0.y
		       + texture(uSrc, vec2(c.x, a.y)).rgb * w3.x * w0.y
		       + texture(uSrc, vec2(a.x, b.y)).rgb * w0.x * w12.y
		       + texture(uSrc, vec2(b.x, b.y)).rgb * w12.x * w12.y
		       + texture(uSrc, vec2(c.x, b.y)).rgb * w3.x * w12.y
		       + texture(uSrc, vec2(a.x, c.y)).rgb * w0.x * w3.y
		       + texture(uSrc, vec2(b.x, c.y)).rgb * w12.x * w3.y
		       + texture(uSrc, vec2(c.x, c.y)).rgb * w3.x * w3.y;
		return clamp(r, 0.0, 1.0);
	}
	return texture(uSrc, q, uBiais).rgb;
}

void main() {
	vec2 o = (gl_FragCoord.xy - uVue.xy) / uVue.zw;
	if (o.x < 0.0 || o.y < 0.0 || o.x > 1.0 || o.y > 1.0) { sortie = vec4(uFondVue, 1.0); return; }
	vec2 p = uCadre.xy + o * uCadre.zw;
	vec2 q = uOrient * (p - 0.5) + 0.5;
	// Un pixel de sortie, en coordonnees de la source : le rayon des reglages de nettete suit la taille de l'image affichee ou exportee.
	vec2 st = vec2(length(vec2(dFdx(q).x, dFdy(q).x)), length(vec2(dFdx(q).y, dFdy(q).y)));
	vec3 brut = echantillon(q, st);
	if (uAvant >= 0.0 && o.x < uAvant) { sortie = vec4(brut, 1.0); return; }

	vec4 mk = texture(uMasque, q);

	// Masques : leurs reglages, ponderes par leur zone, s ajoutent aux globaux
	vec4 L1 = vec4(0.0), L2 = vec4(0.0), L3 = vec4(0.0);
	float zoneVue = 0.0;
	if (uNbMasques > 0) {
		vec4 ca = texture(uCalquesA, q), cb = texture(uCalquesB, q);
		// la personne (detourage et son pinceau), pour les masques limites a elle ou au fond
		float personne = clamp(mk.r + (mk.b * 255.0 - 128.0) / 127.0, 0.0, 1.0);
		for (int i = 0; i < 8; i++) {
			if (i >= uNbMasques) { break; }
			if (uMType[i] == 0) { continue; }
			float z = zoneMasque(i, q, ca, cb);
			if (uMLimite[i] != 0) { z *= uMasqueOk == 0 ? 0.0 : (uMLimite[i] == 1 ? personne : 1.0 - personne); }
			if (i == uMVoir) { zoneVue = z; }
			float w = z * uMQuantite[i];
			L1 += w * uML1[i]; L2 += w * uML2[i]; L3 += w * uML3[i];
		}
	}
	gContraste = clamp(uContraste + L1.y, -1.0, 1.0);
	gHL = uHL + L1.z; gOmbres = uOmbres + L1.w;
	gBlancs = uBlancs + L2.x; gNoirs = uNoirs + L2.y;
	gSat = max(-1.0, uSaturation + L3.x);

	vec3 lin = s2l(brut);
	float a = alphaMasque(mk);
	bool remplace = uFondMode > 0 && uMasqueOk == 1;

	// Pourtour : dans les pixels mi-personne mi-fond (meches), la couleur de la
	// personne seule, estimee sans l ancien fond (RetoucheMasque.premierPlan,
	// d apres l opacite du modele : pas la ou le pinceau a ajoute a la personne)
	if (remplace && uDecontam == 1 && mk.r > 0.01 && mk.r < 0.996) {
		vec3 F = s2l(texture(uPremierPlan, q).rgb);
		lin = mix(lin, F, smoothstep(0.996, 0.85, mk.r) * smoothstep(0.01, 0.04, mk.r));
	}
	if (uLissage > 0.0 && uMasqueOk == 1) {
		vec3 L = s2l(texture(uLisse, q).rgb);
		float ecart = length(lin - L);
		lin = mix(lin, L, uLissage * 0.72 * mk.g * (1.0 - smoothstep(0.06, 0.2, ecart)));
	}
	// Reflets bleus des lunettes : la lumiere d'un ecran se reflete dans les verres en bleu ou en violet. Dans la bande des yeux du visage detecte,
	// les pixels dont le bleu l'emporte sur le rouge (la peau, meme rosee, les cheveux et l'iris brun ne le font pas) voient leur bleu ramene a la
	// moyenne du rouge et du vert : le reflet devient neutre et chaud, la peau et le reste de l'image ne bougent pas.
	if (uReflet > 0.0 && uVisage.z > uVisage.x) {
		vec2 fc = (uVisage.xy + uVisage.zw) * 0.5;
		vec2 fs = max((uVisage.zw - uVisage.xy) * 0.5, vec2(0.0001));
		vec2 u = (q - fc) / fs;
		float bande = smoothstep(-0.85, -0.65, u.y) * (1.0 - smoothstep(0.05, 0.30, u.y)) * (1.0 - smoothstep(0.95, 1.15, abs(u.x)));
		if (bande > 0.0) {
			float mx = max(brut.r, max(brut.g, brut.b));
			float sur_rouge = smoothstep(0.82, 1.0, brut.b / max(brut.r, 0.001));
			float sur_vert = smoothstep(0.03, 0.12, (brut.b - brut.g) / max(mx, 0.001));
			float w = clamp(bande * sur_rouge * sur_vert * smoothstep(0.08, 0.25, mx) * uReflet * 1.15, 0.0, 1.0);
			lin.b = mix(lin.b, min(lin.b, 0.5 * (lin.r + lin.g)), w);
			lin.g = mix(lin.g, min(lin.g, lin.r), w); // le reflet tire aussi le vert : on garde un ton chaud, pas verdatre
		}
	}
	float nt = uNettete + L3.z;
	if (nt > 0.0) {
		// Flou gaussien 3 x 3 d'un pixel de sortie : le detail est ce que la photo a de plus fin que ce flou.
		vec3 fl = s2l(brut) * 4.0
		        + (s2l(texture(uSrc, q + vec2(st.x, 0.0), uBiais).rgb) + s2l(texture(uSrc, q - vec2(st.x, 0.0), uBiais).rgb)
		         + s2l(texture(uSrc, q + vec2(0.0, st.y), uBiais).rgb) + s2l(texture(uSrc, q - vec2(0.0, st.y), uBiais).rgb)) * 2.0
		        + s2l(texture(uSrc, q + st, uBiais).rgb) + s2l(texture(uSrc, q - st, uBiais).rgb)
		        + s2l(texture(uSrc, q + vec2(st.x, -st.y), uBiais).rgb) + s2l(texture(uSrc, q + vec2(-st.x, st.y), uBiais).rgb);
		vec3 d = s2l(brut) - fl / 16.0;
		// La peau est menagee (elle doit rester naturelle), les contours francs ne font pas de halo.
		float menage = 1.0 - 0.6 * (uMasqueOk == 1 ? mk.g : 0.0);
		lin = max(lin + nt * 2.2 * menage * d * (1.0 - smoothstep(0.12, 0.45, length(d))), 0.0);
	} else if (nt < 0.0) {
		// netteté negative (masques seulement) : adoucit, vers le flou de la clarte
		lin = mix(lin, s2l(texture(uFlou, q).rgb), min(1.0, -nt) * 0.6);
	}
	// Defloutage : la photo floue est la photo nette passee dans un flou ; on en retrouve une bonne part en rajoutant ce que ce flou a retire,
	// a deux echelles (le rayon choisi et sa moitie). Trois garde-fous : les petits ecarts (grain, bruit) ne sont pas renforces, les gros contours
	// non plus (pas de halo), et le resultat reste dans la fourchette des voisins du pixel (pas de liseré clair ou sombre).
	if (uDeflou > 0.0) {
		vec2 r1 = uDeflouRayon * uTexel;
		vec3 mn1, mx1, mn2, mx2;
		vec3 b1 = flou9(q, r1, mn1, mx1);
		vec3 b2 = flou9(q, r1 * 0.5, mn2, mx2);
		vec3 x0 = s2l(textureLod(uSrc, q, 0.0).rgb);
		vec3 dd = 0.9 * (x0 - b1) + 1.1 * (x0 - b2);
		float am = length(dd);
		float poids = smoothstep(0.004, 0.025, am) * (1.0 - smoothstep(0.30, 0.80, am));
		float menage = 1.0 - 0.45 * (uMasqueOk == 1 ? mk.g : 0.0);
		vec3 cible = x0 + uDeflou * 2.8 * menage * poids * dd;
		vec3 marge = 0.06 * (mx1 - mn1) + 0.002;
		cible = clamp(cible, mn1 - marge, mx1 + marge);
		lin = max(lin + (cible - x0), 0.0);
	}
	float cl = uClarte + L3.y;
	if (cl != 0.0) {
		vec3 bf = s2l(texture(uFlou, q).rgb);
		float y = luma(lin), yb = luma(bf);
		float d = clamp(log2((y + 0.004) / (yb + 0.004)), -2.0, 2.0);
		float yp = pow(y, 1.0 / 2.2);
		lin *= exp2(cl * 0.7 * d * 4.0 * yp * (1.0 - yp));
	}
	// Hautes lumieres ecretees a la prise de vue : leur couleur est perdue, la
	// balance des blancs les teinterait (un chemisier blanc virerait au lilas).
	// Elles tendent vers le neutre, comme dans un logiciel de developpement.
	vec3 lw = lin * uGains * uExpo;
	if (L1.x != 0.0) { lw *= exp2(L1.x); }
	if (L2.z != 0.0 || L2.w != 0.0) { lw *= gainsBB(L2.z, L2.w); }
	if (uMasqueOk == 1 && mk.g > 0.0) {
		lw *= mix(vec3(1.0), uPeauGains, mk.g);
		float yl = luma(lw);
		lw = max(vec3(0.0), yl + (lw - yl) * (1.0 + uPeauSat * mk.g));
	}
	float ecrete = smoothstep(0.955, 0.998, max(brut.r, max(brut.g, brut.b)));
	// sauf sur la peau : une joue surexposee deviendrait une tache grise
	if (uMasqueOk == 1) { ecrete *= 1.0 - smoothstep(0.1, 0.6, mk.g); }
	lw = mix(lw, vec3(luma(lw)), ecrete * 0.9);
	vec3 c = developper(lw);

	vec3 res = c;
	float alphaSortie = 1.0;
	if (remplace) {
		vec3 fond;
		if (uFondMode == 1) {
			vec2 r = (o - uFondCentre) * vec2(uRatio, 1.0) / max(uRatio, 1.0);
			fond = mix(uFondA, uFondB, smoothstep(0.08, 0.78, length(r) * 1.25));
		} else if (uFondMode == 2) {
			vec3 nette = s2l(brut);
			vec3 floue = s2l(texture(uFondFlou, q).rgb);
			fond = developper(mix(nette, floue, uFondFlouForce) * uGains * uExpo);
		} else if (uFondMode == 3) {
			fond = texture(uImageFond, o * uImageFondEchelle + uImageFondDecal).rgb;
		} else {
			if (uExport == 1) { alphaSortie = a; fond = c; }
			else {
				vec2 cell = floor(gl_FragCoord.xy / 9.0);
				fond = mod(cell.x + cell.y, 2.0) < 1.0 ? vec3(0.80) : vec3(0.92);
			}
		}
		res = l2s(mix(s2l(fond), s2l(c), a));
	}
	// Filtre : saturation, puis les courbes du filtre (une table par canal), puis
	// le grain ; l intensite melange le resultat a la photo sans filtre
	if (uFiltreActif == 1) {
		vec3 f = clamp(res, 0.0, 1.0);
		f = clamp(mix(vec3(luma(f)), f, 1.0 + uFiltreSat), 0.0, 1.0);
		const float LA = 255.0 / 256.0, LB = 0.5 / 256.0;
		f = vec3(texture(uLut, vec2(f.r * LA + LB, 0.5)).r, texture(uLut, vec2(f.g * LA + LB, 0.5)).g, texture(uLut, vec2(f.b * LA + LB, 0.5)).b);
		if (uFiltreGrain > 0.0) {
			float gr = bruit(floor(o * uTailleSortie) + 17.0) - 0.5;
			f += gr * uFiltreGrain * 0.16 * uGrainEchelle * (1.0 - abs(2.0 * luma(f) - 1.0) * 0.6);
		}
		res = mix(res, clamp(f, 0.0, 1.0), uFiltreForce);
	}
	if (uVignette != 0.0) {
		vec2 r = (o - 0.5) * vec2(uRatio, 1.0) / max(uRatio, 1.0) * 2.0;
		float v = smoothstep(0.35, 1.25, length(r));
		res = uVignette < 0.0 ? res * (1.0 + uVignette * 0.75 * v) : mix(res, vec3(1.0), uVignette * 0.75 * v);
	}
	if (uVoirMasque == 1 && uMasqueOk == 1) {
		res = mix(res, vec3(0.95, 0.18, 0.32), 0.5 * (1.0 - a));
	}
	if (uMVoir >= 0) {
		res = mix(res, vec3(0.95, 0.18, 0.32), 0.5 * zoneVue);
	}
	res += (bruit(gl_FragCoord.xy) - 0.5) / 255.0;
	sortie = vec4(clamp(res, 0.0, 1.0), alphaSortie);
}`;

	const TSL_ORDRE = ['rouge', 'orange', 'jaune', 'vert', 'aqua', 'bleu', 'violet', 'magenta'];

	function hexVersRgb(hex) {
		const h = String(hex || '#ffffff').replace('#', '');
		const n = parseInt(h.length === 3 ? h.split('').map((x) => x + x).join('') : h, 16) || 0;
		return [((n >> 16) & 255) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255];
	}

	/* Gains de la balance des blancs (lumiere lineaire), d'apres les curseurs.
	   Temperature : axe jaune / bleu ; teinte : axe vert / magenta. A ±100, le
	   rapport bleu / rouge change de 2^2,25. La luminance d'un gris est gardee. */
	function gains(temperature, teinte) {
		const kt = (temperature / 100) * 1.5, kn = (teinte / 100) * 1.5;
		const l = [0.5 * kt + 0.5 * kn, 0.5 * kt - kn, -kt + 0.5 * kn].map((x) => Math.pow(2, x));
		const y = 0.2126 * l[0] + 0.7152 * l[1] + 0.0722 * l[2];
		return l.map((x) => x / y);
	}

	/* Couleur du virage partiel : la teinte (degres) a pleine saturation, sans
	   sa luminance (elle colore sans eclaircir), a l echelle de la force. */
	function virage(teinte, force) {
		if (!force) { return [0, 0, 0]; }
		const h = ((((teinte || 0) % 360) + 360) % 360) / 60, x = 1 - Math.abs((h % 2) - 1);
		const c = h < 1 ? [1, x, 0] : h < 2 ? [x, 1, 0] : h < 3 ? [0, 1, x] : h < 4 ? [0, x, 1] : h < 5 ? [x, 0, 1] : [1, 0, x];
		const y = 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
		return c.map((v) => (v - y) * (force / 100) * 0.16);
	}

	/* Matrice oriente -> source, pour une rotation de k quarts de tour (sens
	   horaire) puis un miroir horizontal. */
	function matriceOrientation(quarts, miroir) {
		const k = ((quarts % 4) + 4) % 4;
		// q - 0.5 = R * F * (p - 0.5) ; colonnes pour GLSL (mat2 en colonnes)
		const R = [[1, 0, 0, 1], [0, 1, -1, 0], [-1, 0, 0, -1], [0, -1, 1, 0]][k]; // [a, b, c, d] : x' = a x + b y ; y' = c x + d y
		const f = miroir ? -1 : 1;
		const a = R[0] * f, b = R[1], c = R[2] * f, d = R[3];
		return new Float32Array([a, c, b, d]);
	}

	function creer(canvas) {
		const gl = canvas.getContext('webgl2', { premultipliedAlpha: false, preserveDrawingBuffer: false, antialias: false });
		if (!gl) { return null; }
		const nuanceur = (type, texte) => {
			const s = gl.createShader(type); gl.shaderSource(s, texte); gl.compileShader(s);
			if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) { throw new Error(gl.getShaderInfoLog(s)); }
			return s;
		};
		const prog = gl.createProgram();
		gl.attachShader(prog, nuanceur(gl.VERTEX_SHADER, VERTEX));
		gl.attachShader(prog, nuanceur(gl.FRAGMENT_SHADER, FRAGMENT));
		gl.linkProgram(prog);
		if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) { throw new Error(gl.getProgramInfoLog(prog)); }
		gl.useProgram(prog);
		const tampon = gl.createBuffer();
		gl.bindBuffer(gl.ARRAY_BUFFER, tampon);
		gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
		const pos = gl.getAttribLocation(prog, 'aPos');
		gl.enableVertexAttribArray(pos);
		gl.vertexAttribPointer(pos, 2, gl.FLOAT, false, 0, 0);
		gl.pixelStorei(gl.UNPACK_COLORSPACE_CONVERSION_WEBGL, gl.NONE);

		const U = {};
		const nb = gl.getProgramParameter(prog, gl.ACTIVE_UNIFORMS);
		for (let i = 0; i < nb; i++) {
			const info = gl.getActiveUniform(prog, i);
			const nom = info.name.replace(/\[0\]$/, '');
			U[nom] = gl.getUniformLocation(prog, info.name);
		}
		const UNITES = { uSrc: 0, uFlou: 1, uLisse: 2, uPremierPlan: 3, uFondFlou: 4, uMasque: 5, uImageFond: 6, uLut: 8, uCalquesA: 9, uCalquesB: 10 };
		Object.keys(UNITES).forEach((k) => gl.uniform1i(U[k], UNITES[k]));

		const textures = {};
		function texture(nom, mipmap) {
			if (!textures[nom]) { textures[nom] = gl.createTexture(); }
			gl.activeTexture(gl.TEXTURE0 + UNITES[nom]);
			gl.bindTexture(gl.TEXTURE_2D, textures[nom]);
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, mipmap ? gl.LINEAR_MIPMAP_LINEAR : gl.LINEAR);
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
			return textures[nom];
		}
		// textures neutres d'un pixel, en attendant les vraies
		function pixel(nom, rgba) {
			texture(nom, false);
			gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 1, 1, 0, gl.RGBA, gl.UNSIGNED_BYTE, new Uint8Array(rgba));
		}
		['uFlou', 'uLisse', 'uPremierPlan', 'uFondFlou', 'uImageFond'].forEach((n) => pixel(n, [128, 128, 128, 255]));
		pixel('uLut', [128, 128, 128, 255]);
		let lutChargee = null;       // filtre dont la table est dans uLut
		pixel('uMasque', [255, 0, 128, 255]);
		pixel('uSrc', [128, 128, 128, 255]);
		pixel('uCalquesA', [0, 0, 0, 0]);
		pixel('uCalquesB', [0, 0, 0, 0]);

		const maxTexture = gl.getParameter(gl.MAX_TEXTURE_SIZE);
		let source = { w: 1, h: 1 };
		let masqueOk = false;
		let visage = new Float32Array([0, 0, 0, 0]);   // boite du visage (UV de la source), pour la correction des reflets de lunettes
		let imageFond = null;

		const moteur = {
			gl, maxTexture,
			/** Photo source : un ImageBitmap ou un canevas, deja a la taille voulue. */
			source(image) {
				texture('uSrc', true);
				gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);
				gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, image);
				gl.generateMipmap(gl.TEXTURE_2D);
				source = { w: image.width, h: image.height };
				masqueOk = false;
				['uFlou', 'uLisse', 'uPremierPlan', 'uFondFlou'].forEach((n) => pixel(n, [128, 128, 128, 255]));
				pixel('uMasque', [255, 0, 128, 255]);
				pixel('uCalquesA', [0, 0, 0, 0]);
				pixel('uCalquesB', [0, 0, 0, 0]);
			},
			/** Zones des masques (RGBA 8 bits, un masque par canal) : a = masques 1 a 4, b = 5 a 8. */
			calques(a, b, w, h) {
				gl.pixelStorei(gl.UNPACK_ALIGNMENT, 1);
				texture('uCalquesA', false);
				gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, w, h, 0, gl.RGBA, gl.UNSIGNED_BYTE, a);
				texture('uCalquesB', false);
				gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, w, h, 0, gl.RGBA, gl.UNSIGNED_BYTE, b);
			},
			/** Mise a jour d'une bande (lignes y0 a y1 exclues) de l'une des deux textures ('a' ou 'b'). */
			calquesPartiel(lequel, donnees, w, y0, y1) {
				texture(lequel === 'b' ? 'uCalquesB' : 'uCalquesA', false);
				gl.pixelStorei(gl.UNPACK_ALIGNMENT, 1);
				gl.texSubImage2D(gl.TEXTURE_2D, 0, 0, y0, w, y1 - y0, gl.RGBA, gl.UNSIGNED_BYTE, donnees.subarray(y0 * w * 4, y1 * w * 4));
			},
			/** Texture de travail (RGBA 8 bits) : flou, lisse, premierPlan, fondFlou, masque. */
			travail(nom, donnees, w, h) {
				const unite = { flou: 'uFlou', lisse: 'uLisse', premierPlan: 'uPremierPlan', fondFlou: 'uFondFlou', masque: 'uMasque' }[nom];
				texture(unite, false);
				gl.pixelStorei(gl.UNPACK_ALIGNMENT, 1);
				gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, w, h, 0, gl.RGBA, gl.UNSIGNED_BYTE, donnees);
				if (nom === 'masque') { masqueOk = true; }
			},
			/** Mise a jour d'une bande du masque (pinceau) : lignes y0 a y1 exclues. */
			masquePartiel(donnees, w, y0, y1) {
				texture('uMasque', false);
				gl.pixelStorei(gl.UNPACK_ALIGNMENT, 1);
				gl.texSubImage2D(gl.TEXTURE_2D, 0, 0, y0, w, y1 - y0, gl.RGBA, gl.UNSIGNED_BYTE, donnees.subarray(y0 * w * 4, y1 * w * 4));
			},
			/** Boite du visage en pixels de la source ({ x0, y0, x1, y1 }) ; null : aucun visage trouve. */
			definirVisage(b, largeur, hauteur) {
				visage = b ? new Float32Array([b.x0 / largeur, b.y0 / hauteur, b.x1 / largeur, b.y1 / hauteur]) : new Float32Array([0, 0, 0, 0]);
			},
			oublierMasque() { masqueOk = false; pixel('uMasque', [255, 0, 128, 255]); },
			imageFond(image) {
				imageFond = image ? { w: image.width, h: image.height } : null;
				if (!image) { pixel('uImageFond', [128, 128, 128, 255]); return; }
				texture('uImageFond', true);
				gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, image);
				gl.generateMipmap(gl.TEXTURE_2D);
			},
			get masqueOk() { return masqueOk; },
			get tailleSource() { return source; },

			/* Taille de la sortie (en pixels source) pour un recadrage donne. */
			tailleSortie(r) {
				const tourne = (r.rotation % 2) !== 0;
				const W = tourne ? source.h : source.w, H = tourne ? source.w : source.h;
				return { w: Math.max(1, Math.round(r.cadre.w * W)), h: Math.max(1, Math.round(r.cadre.h * H)), W, H };
			},

			/* Envoie tous les reglages au programme. opts : { avant, voirMasque, damier, export, fondVue } */
			uniformes(r, opts) {
				const t = moteur.tailleSortie(r);
				gl.uniform4f(U.uCadre, r.cadre.x, r.cadre.y, r.cadre.w, r.cadre.h);
				gl.uniformMatrix2fv(U.uOrient, false, matriceOrientation(r.rotation, r.miroir));
				gl.uniform2f(U.uTexel, 1 / source.w, 1 / source.h);
				gl.uniform1f(U.uRatio, t.w / t.h);
				gl.uniform3fv(U.uGains, gains(r.temperature, r.teinte));
				gl.uniform1f(U.uExpo, Math.pow(2, r.exposition));
				gl.uniform1f(U.uContraste, r.contraste / 100);
				gl.uniform1f(U.uHL, r.hautesLumieres / 100);
				gl.uniform1f(U.uOmbres, r.ombres / 100);
				gl.uniform1f(U.uBlancs, r.blancs / 100);
				gl.uniform1f(U.uNoirs, r.noirs / 100);
				gl.uniform1f(U.uVibrance, r.vibrance / 100);
				gl.uniform1f(U.uSaturation, r.saturation / 100);
				gl.uniform1f(U.uClarte, r.clarte / 100);
				gl.uniform1f(U.uNettete, r.nettete / 100);
				gl.uniform1f(U.uLissage, r.lissage / 100);
				gl.uniform1f(U.uReflet, (r.refletLunettes || 0) / 100);
				gl.uniform1f(U.uDeflou, (r.defloutage || 0) / 100);
				gl.uniform1f(U.uDeflouRayon, r.defloutageRayon || 2);
				gl.uniform4fv(U.uVisage, visage);
				const tsl = new Float32Array(24);
				let actif = 0;
				TSL_ORDRE.forEach((nom, i) => {
					const v = r.tsl[nom] || [0, 0, 0];
					tsl[i * 3] = v[0] / 100; tsl[i * 3 + 1] = v[1] / 100; tsl[i * 3 + 2] = v[2] / 100;
					if (v[0] || v[1] || v[2]) { actif = 1; }
				});
				gl.uniform3fv(U.uTSL, tsl);
				gl.uniform1i(U.uTSLActif, actif);
				// filtre : sa table de courbes (envoyee au changement), sa saturation, son grain,
				// et son vignetage, ajoute a celui du reglage
				const F = window.RetoucheFiltres, choix = r.filtre || { nom: 'aucun', force: 100 };
				const def = F ? F.trouver(choix.nom) : null;
				const filtreActif = !!(def && def.nom !== 'aucun' && choix.force > 0);
				const force = filtreActif ? choix.force / 100 : 0;
				if (filtreActif && lutChargee !== def.nom) {
					texture('uLut', false);
					gl.pixelStorei(gl.UNPACK_ALIGNMENT, 1);
					gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 256, 1, 0, gl.RGBA, gl.UNSIGNED_BYTE, F.table(def));
					lutChargee = def.nom;
				}
				gl.uniform1i(U.uFiltreActif, filtreActif ? 1 : 0);
				gl.uniform1f(U.uFiltreForce, force);
				gl.uniform1f(U.uFiltreSat, filtreActif ? (def.sat || 0) : 0);
				gl.uniform1f(U.uFiltreGrain, filtreActif ? (def.grain || 0) : 0);
				gl.uniform1f(U.uGrainEchelle, opts.grainEchelle == null ? 1 : opts.grainEchelle);
				gl.uniform2f(U.uTailleSortie, t.w, t.h);
				gl.uniform1f(U.uVignette, Math.max(-1, Math.min(1, r.vignette / 100 + (filtreActif ? (def.vignette || 0) * force : 0))));
				gl.uniform3fv(U.uVirageO, virage(r.virageOT, r.virageOS));
				gl.uniform3fv(U.uVirageH, virage(r.virageHT, r.virageHS));
				const gp = gains(r.peauTemperature || 0, r.peauTeinte || 0), lp = Math.pow(2, (r.peauLuminosite || 0) / 100);
				gl.uniform3f(U.uPeauGains, gp[0] * lp, gp[1] * lp, gp[2] * lp);
				gl.uniform1f(U.uPeauSat, (r.peauSaturation || 0) / 100);
				const modes = { original: 0, couleur: 1, studio: 1, flou: 2, image: 3, transparent: 4 };
				let mode = modes[r.fond.mode] || 0;
				if (mode === 3 && !imageFond) { mode = 0; }
				gl.uniform1i(U.uFondMode, mode);
				const a = hexVersRgb(r.fond.mode === 'studio' ? r.fond.studioA : r.fond.couleur);
				const b = hexVersRgb(r.fond.mode === 'studio' ? r.fond.studioB : r.fond.couleur);
				gl.uniform3f(U.uFondA, a[0], a[1], a[2]);
				gl.uniform3f(U.uFondB, b[0], b[1], b[2]);
				gl.uniform2f(U.uFondCentre, 0.5, r.fond.centreY == null ? 0.5 : r.fond.centreY);
				gl.uniform1f(U.uFondFlouForce, r.fond.flou / 100);
				gl.uniform1f(U.uContour, r.fond.contour / 100 * 0.35);
				gl.uniform1f(U.uDurete, 1 + (r.fond.durete / 100) * 3);
				gl.uniform1i(U.uDecontam, r.fond.pourtour ? 1 : 0);
				gl.uniform1i(U.uMasqueOk, masqueOk ? 1 : 0);
				if (imageFond) { // l'image de fond couvre la sortie, centree
					const rs = t.w / t.h, ri = imageFond.w / imageFond.h;
					const sx = rs > ri ? 1 : rs / ri, sy = rs > ri ? ri / rs : 1;
					gl.uniform2f(U.uImageFondEchelle, sx, sy);
					gl.uniform2f(U.uImageFondDecal, (1 - sx) / 2, (1 - sy) / 2);
				}
				// masques
				const masques = (r.masques || []).slice(0, 8);
				const mType = new Int32Array(8), mInv = new Int32Array(8), mLim = new Int32Array(8), mQte = new Float32Array(8);
				const geoA = new Float32Array(32), geoB = new Float32Array(32), l1 = new Float32Array(32), l2 = new Float32Array(32), l3 = new Float32Array(32);
				masques.forEach((m, i) => {
					const v = m.r || {}, g = m.geo || {}, k = i * 4;
					mType[i] = m.actif === false ? 0 : (m.type === 'lineaire' ? 2 : (m.type === 'radial' ? 3 : 1));
					mInv[i] = m.inverse ? 1 : 0;
					mLim[i] = m.limite === 'sujet' ? 1 : (m.limite === 'fond' ? 2 : 0);
					mQte[i] = (m.quantite == null ? 100 : m.quantite) / 100;
					if (m.type === 'lineaire') { geoA.set([g.ax, g.ay, g.bx, g.by], k); }
					else if (m.type === 'radial') {
						const angle = g.angle || 0;
						geoA.set([g.cx, g.cy, (g.plume == null ? 50 : g.plume) / 100, 0], k);
						geoB.set([g.rx, g.ry, Math.cos(angle), Math.sin(angle)], k);
					}
					l1.set([v.exposition || 0, (v.contraste || 0) / 100, (v.hautesLumieres || 0) / 100, (v.ombres || 0) / 100], k);
					l2.set([(v.blancs || 0) / 100, (v.noirs || 0) / 100, (v.temperature || 0) / 100, (v.teinte || 0) / 100], k);
					l3.set([(v.saturation || 0) / 100, (v.clarte || 0) / 100, (v.nettete || 0) / 100, 0], k);
				});
				gl.uniform1i(U.uNbMasques, masques.length);
				gl.uniform1iv(U.uMType, mType);
				gl.uniform1iv(U.uMInverse, mInv);
				gl.uniform1iv(U.uMLimite, mLim);
				gl.uniform1fv(U.uMQuantite, mQte);
				gl.uniform4fv(U.uMGeoA, geoA);
				gl.uniform4fv(U.uMGeoB, geoB);
				gl.uniform4fv(U.uML1, l1);
				gl.uniform4fv(U.uML2, l2);
				gl.uniform4fv(U.uML3, l3);
				gl.uniform1i(U.uMVoir, opts.masqueVoir == null ? -1 : opts.masqueVoir);
				gl.uniform1f(U.uRatioSrc, source.w / source.h);
				gl.uniform1f(U.uAvant, opts.avant == null ? -1 : opts.avant);
				gl.uniform1i(U.uVoirMasque, opts.voirMasque ? 1 : 0);
				gl.uniform1i(U.uExport, opts.export ? 1 : 0);
				gl.uniform1f(U.uBiais, opts.export ? -0.45 : -0.2);
				const fv = opts.fondVue || [0.14, 0.14, 0.15];
				gl.uniform3f(U.uFondVue, fv[0], fv[1], fv[2]);
				return t;
			},

			/* Apercu dans le canevas. vue : { x, y, l, h } rectangle de la sortie,
			   en pixels du canevas (origine en haut a gauche). */
			dessiner(r, vue, opts) {
				gl.bindFramebuffer(gl.FRAMEBUFFER, null);
				gl.viewport(0, 0, canvas.width, canvas.height);
				moteur.uniformes(r, opts || {});
				gl.uniform4f(U.uVue, vue.x, canvas.height - vue.y, vue.l, -vue.h);
				gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
			},

			/* Rendu hors ecran a la taille w x h (lignes de haut en bas, RGBA).
			   Par tuiles : chaque tuile est rendue puis copiee via surTuile. */
			rendre(r, w, h, opts, surTuile) {
				const T = 2048;
				const fbo = gl.createFramebuffer();
				const tex = gl.createTexture();
				gl.activeTexture(gl.TEXTURE7);
				gl.bindTexture(gl.TEXTURE_2D, tex);
				gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, Math.min(T, w), Math.min(T, h), 0, gl.RGBA, gl.UNSIGNED_BYTE, null);
				gl.bindFramebuffer(gl.FRAMEBUFFER, fbo);
				gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, tex, 0);
				moteur.uniformes(r, Object.assign({}, opts || {}));
				try {
					for (let ty = 0; ty < h; ty += T) {
						for (let tx = 0; tx < w; tx += T) {
							const tw = Math.min(T, w - tx), th = Math.min(T, h - ty);
							gl.viewport(0, 0, tw, th);
							gl.uniform4f(U.uVue, -tx, -ty, w, h);
							gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
							const px = new Uint8Array(tw * th * 4);
							gl.readPixels(0, 0, tw, th, gl.RGBA, gl.UNSIGNED_BYTE, px);
							surTuile(px, tx, ty, tw, th);
						}
					}
				} finally {
					gl.bindFramebuffer(gl.FRAMEBUFFER, null);
					gl.deleteFramebuffer(fbo);
					gl.deleteTexture(tex);
				}
			},

			/* Petit rendu en memoire (histogramme, vignette) : renvoie { px, w, h }. */
			rendreReduit(r, cote, opts) {
				const t = moteur.tailleSortie(r);
				const s = Math.min(1, cote / Math.max(t.w, t.h));
				const w = Math.max(1, Math.round(t.w * s)), h = Math.max(1, Math.round(t.h * s));
				const px = new Uint8Array(w * h * 4);
				moteur.rendre(r, w, h, opts, (bloc, tx, ty, tw, th) => {
					for (let y = 0; y < th; y++) { px.set(bloc.subarray(y * tw * 4, (y + 1) * tw * 4), ((ty + y) * w + tx) * 4); }
				});
				return { px, w, h };
			},
		};
		return moteur;
	}

	window.RetoucheMoteur = { creer, gains, hexVersRgb, matriceOrientation, TSL_ORDRE };
})();
