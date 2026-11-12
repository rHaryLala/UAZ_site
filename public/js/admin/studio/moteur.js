/* =============================================================================
   Studio UAZ — le moteur de dessin (canvas 2D).

   Une seule source pour l'aperçu et l'export : le même code dessine le visuel, à l'échelle 1 pour l'aperçu, à l'échelle 1 ou 2 pour l'export (le texte
   et les filets sont alors redessinés en vectoriel, jamais agrandis). Chaque modèle (modeles.js) reçoit un « stylo » (Pen) et une géométrie par format.

   Ce que le moteur garantit :
   - le texte s'ajuste tout seul (la taille baisse de 2 px en 2 px jusqu'au minimum du modèle, puis une alerte « texte trop long » apparaît) ;
   - les photos sont découpées à la taille EXACTE de leur cadre, par réductions successives de moitié (pas de crénelage), puis affinées d'un soupçon :
     un grand écran d'ordinateur n'a pas le droit d'avoir une photo molle ;
   - aucune ressource externe : la police (Inter), le sceau et le logo du thème viennent du site ;
   - aucun HTML saisi n'est interprété : tout passe par fillText.
   ========================================================================== */
(() => {
    'use strict';

    const FORMATS = {
        '16:9': { w: 1920, h: 1080, nom: 'Écran 16:9', usage: 'Écran du direct, projection, YouTube' },
        '1:1': { w: 1080, h: 1080, nom: 'Carré 1:1', usage: 'Instagram, Facebook' },
        '4:5': { w: 1080, h: 1350, nom: 'Post 4:5', usage: 'Instagram, Facebook' },
        '9:16': { w: 1080, h: 1920, nom: 'Story 9:16', usage: 'Stories, Reels, TikTok' },
    };

    const COULEURS = {
        marine1: '#1a2b6a', marine2: '#13235b', marine3: '#0e1c4d', cyan: '#00c4d4', blanc: '#ffffff',
        doux: '#c9d1f2', pale: '#9aa6dc', filet: 'rgba(255,255,255,0.13)',
    };

    const POLICE = 'Inter, "Helvetica Neue", Arial, sans-serif';
    const SPACING = typeof CanvasRenderingContext2D !== 'undefined' && 'letterSpacing' in CanvasRenderingContext2D.prototype;

    /* --- Typographie française : espace insécable avant « : ; ! ? » et dans les guillemets --- */
    const typo = (s) => String(s ?? '')
        .replace(/[ \t]+/g, ' ')
        .replace(/ ([:;!?»])/g, ' $1')
        .replace(/(«) /g, '$1 ');

    /* --- Polices : Inter variable, auto-hébergée --- */
    let policesPretes = null;
    function chargerPolices(urls) {
        if (policesPretes) { return policesPretes; }
        const faces = [
            new FontFace('Inter', `url(${urls.latin}) format('woff2')`, { weight: '100 900', unicodeRange: 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD' }),
            new FontFace('Inter', `url(${urls.latinExt}) format('woff2')`, { weight: '100 900', unicodeRange: 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF' }),
        ];
        policesPretes = Promise.all(faces.map((f) => f.load().then((ok) => document.fonts.add(ok)))).then(() => document.fonts.load('900 40px Inter', 'ÉÈÀ')).then(() => true).catch(() => false);

        return policesPretes;
    }

    /* --- Photos : découpe à la taille exacte du cadre --- */
    const toile = (w, h) => { const c = document.createElement('canvas'); c.width = Math.max(1, w); c.height = Math.max(1, h); return c; };

    /** Masque flou léger sur la luminance seule, pour rendre à une photo réduite la finesse qu'elle a perdue. */
    function affiner(canvas, quantite) {
        const w = canvas.width, h = canvas.height;
        if (quantite <= 0 || w < 8 || h < 8) { return; }
        const g = canvas.getContext('2d');
        const img = g.getImageData(0, 0, w, h);
        const d = img.data, n = w * h;
        const Y = new Float32Array(n), B = new Float32Array(n);
        for (let p = 0, i = 0; p < n; p++, i += 4) { Y[p] = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2]; }
        for (let y = 0; y < h; y++) {
            const r = y * w;
            for (let x = 0; x < w; x++) { B[r + x] = (Y[r + (x > 0 ? x - 1 : 0)] + 2 * Y[r + x] + Y[r + (x < w - 1 ? x + 1 : w - 1)]) * 0.25; }
        }
        for (let y = 0; y < h; y++) {
            const r = y * w, ra = (y > 0 ? y - 1 : 0) * w, rb = (y < h - 1 ? y + 1 : h - 1) * w;
            for (let x = 0; x < w; x++) {
                const det = Y[r + x] - (B[ra + x] + 2 * B[r + x] + B[rb + x]) * 0.25;
                const ad = det < 0 ? -det : det;
                let delta = det * quantite * (ad < 2.2 ? ad / 2.2 : 1);
                if (delta > 24) { delta = 24; } else if (delta < -24) { delta = -24; }
                const i = (r + x) * 4;
                d[i] += delta; d[i + 1] += delta; d[i + 2] += delta;
            }
        }
        g.putImageData(img, 0, 0);
    }

    /** La partie de la photo qui remplit un cadre de ce rapport (point focal et zoom compris). */
    function cadrage(im, rapport) {
        const W = im.w, H = im.h;
        const zoom = Math.max(1, im.zoom || 1);
        let sw = W, sh = W / rapport;
        if (sh > H) { sh = H; sw = H * rapport; }
        sw /= zoom; sh /= zoom;
        const [fx, fy] = im.focus || [0.5, 0.4];
        const sx = Math.min(W - sw, Math.max(0, fx * W - sw / 2));
        const sy = Math.min(H - sh, Math.max(0, fy * H - sh / 2));

        return { sx, sy, sw, sh };
    }

    function redimensionner(img, sx, sy, sw, sh, tw, th) {
        let src = img, rx = sx, ry = sy, rw = sw, rh = sh;
        while (rw / 2 >= tw && rh / 2 >= th) {
            const nw = Math.max(tw, Math.round(rw / 2)), nh = Math.max(th, Math.round(rh / 2));
            const t = toile(nw, nh), g = t.getContext('2d');
            g.imageSmoothingEnabled = true; g.imageSmoothingQuality = 'high';
            g.drawImage(src, rx, ry, rw, rh, 0, 0, nw, nh);
            src = t; rx = 0; ry = 0; rw = nw; rh = nh;
        }
        const t = toile(tw, th), g = t.getContext('2d');
        g.imageSmoothingEnabled = true; g.imageSmoothingQuality = 'high';
        g.drawImage(src, rx, ry, rw, rh, 0, 0, tw, th);
        const reduction = sw / tw;
        if (reduction > 1.15) { affiner(t, Math.min(0.55, 0.22 + 0.12 * (reduction - 1))); }

        return t;
    }

    /* ======================================================================
       Le stylo
       ====================================================================== */
    class Pen {
        constructor(ctx, W, H, env) {
            this.c = ctx; this.W = W; this.H = H; this.env = env; this.alertes = [];
        }

        alerte(message, niveau = 'attention') { if (!this.alertes.some((a) => a.message === message)) { this.alertes.push({ message, niveau }); } }

        rect(x, y, w, h, couleur) { this.c.fillStyle = couleur; this.c.fillRect(x, y, w, h); }

        /** Dégradé linéaire sur un rectangle : sens = [x0, y0, x1, y1] en proportion du rectangle ; arrêts = [[0, couleur], …]. */
        degrade(x, y, w, h, sens, arrets) {
            const g = this.c.createLinearGradient(x + sens[0] * w, y + sens[1] * h, x + sens[2] * w, y + sens[3] * h);
            arrets.forEach(([p, couleur]) => g.addColorStop(p, couleur));
            this.c.fillStyle = g; this.c.fillRect(x, y, w, h);
        }

        ligne(x1, y1, x2, y2, couleur, epaisseur = 1) {
            const c = this.c;
            c.strokeStyle = couleur; c.lineWidth = epaisseur;
            c.beginPath(); c.moveTo(x1, y1); c.lineTo(x2, y2); c.stroke();
        }

        cercle(cx, cy, r, { remplissage = null, trait = null, epaisseur = 1 } = {}) {
            const c = this.c;
            c.beginPath(); c.arc(cx, cy, r, 0, Math.PI * 2);
            if (remplissage) { c.fillStyle = remplissage; c.fill(); }
            if (trait) { c.strokeStyle = trait; c.lineWidth = epaisseur; c.stroke(); }
        }

        regler(taille, poids, espacement) {
            const c = this.c;
            c.font = `${poids} ${taille}px ${POLICE}`;
            if (SPACING) { c.letterSpacing = `${(espacement * taille).toFixed(2)}px`; }
        }

        largeur(texte, taille, espacement) {
            const w = this.c.measureText(texte).width;
            return SPACING ? w : w + espacement * taille * texte.length;
        }

        /**
         * Texte de plusieurs couleurs, à la ligne automatique. parties = [{ t, c }] (un « \n » force un retour). Options : x, y (haut de la première ligne),
         * w (largeur), taille, min (taille minimale), poids, espacement (en em), interligne, lignesMax, alignement, majuscules.
         * Renvoie { h, bas, taille, lignes, depasse }.
         */
        texteCouleurs(parties, o) {
            const c = this.c;
            const poids = o.poids || 500, esp = o.espacement || 0, ig = o.interligne || 1.2;
            const jetons = [];
            parties.forEach(({ t, c: couleur }) => {
                String(o.majuscules ? String(t ?? '').toLocaleUpperCase('fr') : t ?? '').split('\n').forEach((ligne, i) => {
                    if (i > 0) { jetons.push({ saut: true }); }
                    typo(ligne).split(' ').filter(Boolean).forEach((mot) => jetons.push({ mot, couleur }));
                });
            });
            let taille = o.taille;
            const min = o.min || Math.max(12, Math.round(o.taille * 0.55));
            let lignes = [], tropLarge = false;

            for (;;) {
                this.regler(taille, poids, esp);
                lignes = [[]]; tropLarge = false;
                let courant = '';
                for (const j of jetons) {
                    if (j.saut) { lignes.push([]); courant = ''; continue; }
                    const essai = courant ? `${courant} ${j.mot}` : j.mot;
                    if (courant && this.largeur(essai, taille, esp) > o.w) { lignes.push([j]); courant = j.mot; } else { lignes[lignes.length - 1].push(j); courant = essai; }
                    if (this.largeur(j.mot, taille, esp) > o.w) { tropLarge = true; }
                }
                lignes = lignes.filter((l) => l.length);
                if (((!o.lignesMax || lignes.length <= o.lignesMax) && !tropLarge) || taille <= min) { break; }
                taille -= 2;
            }

            const depasse = (o.lignesMax && lignes.length > o.lignesMax) || tropLarge;
            const lh = taille * ig;
            const espaceW = this.largeur(' ', taille, esp);
            c.textBaseline = 'alphabetic';
            c.textAlign = 'left';
            lignes.forEach((ligne, i) => {
                const base = o.y + i * lh + lh / 2 + (o.capitales ? 0.36 : 0.34) * taille;
                const texte = ligne.map((j) => j.mot).join(' ');
                const largeurLigne = this.largeur(texte, taille, esp) - (SPACING ? esp * taille : 0);
                let x = o.x;
                if (o.alignement === 'centre') { x = o.x + (o.w - largeurLigne) / 2; } else if (o.alignement === 'droite') { x = o.x + o.w - largeurLigne; }
                ligne.forEach((j, k) => {
                    c.fillStyle = j.couleur || o.couleur || COULEURS.blanc;
                    c.fillText(j.mot, x, base);
                    x += this.largeur(j.mot, taille, esp) + (k < ligne.length - 1 ? espaceW : 0);
                });
            });
            if (depasse) { this.alerte(o.nom ? `« ${o.nom} » est trop long : raccourcissez-le.` : 'Un texte est trop long : raccourcissez-le.', 'erreur'); }

            return { h: lignes.length * lh, bas: o.y + lignes.length * lh, taille, lignes: lignes.length, depasse: !!depasse };
        }

        /** Texte d'une seule couleur (raccourci de texteCouleurs). */
        texte(chaine, o) { return this.texteCouleurs([{ t: chaine, c: o.couleur }], o); }

        /** Photo d'un emplacement, découpée à la taille du cadre r = { x, y, w, h }. Options : fondu = { cote, longueur }. */
        photo(slot, r, o = {}) {
            const im = this.env.photos[slot];
            const c = this.c;

            if (!im || !im.img) {
                this.espaceReserve(r, slot);

                return false;
            }

            const { sx, sy, sw, sh } = cadrage(im, r.w / r.h);
            const e = this.env.echelle;
            const tw = Math.round(r.w * e), th = Math.round(r.h * e);
            const cle = [slot, im.version, tw, th, Math.round(sx), Math.round(sy), Math.round(sw), Math.round(sh)].join('|');
            let bmp = this.env.cache.get(cle);
            if (!bmp) {
                bmp = redimensionner(im.img, sx, sy, sw, sh, tw, th);
                this.env.cache.set(cle, bmp);
                if (this.env.cache.size > 30) { this.env.cache.delete(this.env.cache.keys().next().value); }
            }
            if (sw < tw * 0.75) { this.alerte(`La photo « ${im.libelle || slot} » est petite pour ce cadre (${Math.round(sw)} px de large) : elle risque d'être floue.`); }

            c.save();
            c.beginPath(); c.rect(r.x, r.y, r.w, r.h); c.clip();
            if (o.fondu) {
                const t = toile(tw, th), g = t.getContext('2d');
                g.drawImage(bmp, 0, 0);
                g.globalCompositeOperation = 'destination-in';
                const L = o.fondu.longueur * e, cote = o.fondu.cote;
                const gr = cote === 'gauche' ? g.createLinearGradient(0, 0, L, 0)
                    : cote === 'droite' ? g.createLinearGradient(tw, 0, tw - L, 0)
                        : cote === 'haut' ? g.createLinearGradient(0, 0, 0, L) : g.createLinearGradient(0, th, 0, th - L);
                gr.addColorStop(0, 'rgba(0,0,0,0)'); gr.addColorStop(1, 'rgba(0,0,0,1)');
                g.fillStyle = gr; g.fillRect(0, 0, tw, th);
                c.drawImage(t, r.x, r.y, r.w, r.h);
            } else {
                c.drawImage(bmp, r.x, r.y, r.w, r.h);
            }
            c.restore();

            return true;
        }

        /** Cadre sans photo : un dégradé discret et, dans l'aperçu seulement, l'invitation à en ajouter une. */
        espaceReserve(r, slot) {
            const c = this.c;
            this.degrade(r.x, r.y, r.w, r.h, [0, 0, 1, 1], [[0, 'rgba(255,255,255,0.06)'], [1, 'rgba(0,196,212,0.10)']]);
            if (this.env.apercu) {
                c.save();
                c.strokeStyle = 'rgba(255,255,255,0.35)'; c.lineWidth = 2; c.setLineDash([14, 10]);
                c.strokeRect(r.x + 14, r.y + 14, r.w - 28, r.h - 28);
                c.setLineDash([]);
                c.fillStyle = 'rgba(255,255,255,0.75)'; c.textAlign = 'center'; c.textBaseline = 'middle';
                c.font = `600 ${Math.max(16, Math.min(34, r.w / 18))}px ${POLICE}`;
                if (SPACING) { c.letterSpacing = '0px'; }
                c.fillText('Ajoutez une photo', r.x + r.w / 2, r.y + r.h / 2);
                c.restore();
            }
        }

        /** Une image du site (sceau, logo du thème), à la taille donnée. */
        image(nom, x, y, w, h = 0) {
            const im = this.env.logos[nom];
            if (!im) { return; }
            const hh = h || w * (im.naturalHeight / im.naturalWidth);
            this.c.drawImage(im, x, y, w, hh);
        }
    }

    function chargerImage(url) {
        return new Promise((ok) => {
            const im = new Image();
            im.decoding = 'async';
            im.onload = () => ok(im);
            im.onerror = () => ok(null);
            im.src = url;
        });
    }

    /**
     * Dessine un visuel. env : { photos: { slot: { img, w, h, focus, zoom, version, libelle } }, logos, cache, apercu, echelle }.
     * Renvoie { alertes }.
     */
    function rendre(canvas, modele, donnees, format, env) {
        const f = FORMATS[format];
        const e = env.echelle || 1;
        canvas.width = Math.round(f.w * e);
        canvas.height = Math.round(f.h * e);
        const ctx = canvas.getContext('2d');
        ctx.setTransform(e, 0, 0, e, 0, 0);
        ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
        ctx.clearRect(0, 0, f.w, f.h);
        const pen = new Pen(ctx, f.w, f.h, Object.assign({ cache: new Map(), echelle: e, apercu: false }, env));
        modele.dessiner(pen, donnees, geometrie(format));

        return { alertes: pen.alertes };
    }

    /** Les repères de chaque format : où sont l'en-tête, la photo, le texte et le pied de page. */
    function geometrie(format) {
        const { w: W, h: H } = FORMATS[format];
        if (W > H) {
            return {
                format, W, H, paysage: true, marge: 96, k: 1,
                sceau: { x: 116, y: 62, d: 94 }, tx: 260, t1: { c: 86, taille: 24 }, t2: { c: 127, taille: 34 }, filetY: 208, filetX: [96, 1824],
                texte: { x: 96, w: 880, y0: 280, y1: 905 },
                photo: { x: 1040, y: 0, w: 880, h: 1080 },
                pied: { filetY: 943, y: 990 },
            };
        }
        const t = { '1:1': { dy: 0, eh: 150, ph: 380, y1: 940, fy: 985 }, '4:5': { dy: 0, eh: 160, ph: 520, y1: 1170, fy: 1245 }, '9:16': { dy: 170, eh: 330, ph: 760, y1: 1620, fy: 1700 } }[format];

        return {
            format, W, H, paysage: false, marge: 72, k: 0.78, dy: t.dy,
            sceau: { x: 72, y: 46 + t.dy, d: 72 }, tx: 168, t1: { c: 64 + t.dy, taille: 20 }, t2: { c: 98 + t.dy, taille: 28 }, filetY: t.eh, filetX: [72, W - 72],
            texte: { x: 72, w: W - 144, y0: t.eh + t.ph - 24, y1: t.y1 },
            photo: { x: 0, y: t.eh, w: W, h: t.ph },
            pied: { filetY: t.fy, y: t.fy + 50 },
        };
    }

    window.StudioMoteur = { FORMATS, COULEURS, POLICE, typo, chargerPolices, chargerImage, rendre, geometrie, Pen };
})();
