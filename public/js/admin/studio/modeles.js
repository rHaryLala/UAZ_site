/* =============================================================================
   Studio UAZ — les modèles.

   Un modèle est une définition de données (champs, emplacements photo, variantes rapides) et une fonction de dessin qui reçoit le stylo du moteur
   (moteur.js), les valeurs saisies et la géométrie du format. Ajouter un modèle = ajouter une entrée à MODELES, sans toucher à l'éditeur.

   Chaque élément dessiné porte un identifiant, un nom et les champs qu'il affiche : sur le visuel, on peut le sélectionner, le modifier, le déplacer,
   le grossir ou le supprimer (masquer), comme un bloc de newsletter (voir editeur.js).

   Le style vient des écrans et des affiches de l'université : fond marine en dégradé, cyan du thème « Toutes choses nouvelles », titres très gras (Inter
   300 à 900, serrés), filets fins, photo qui se fond dans le marine, lettres « UAZ » en filigrane, en-tête et pied de page identiques partout.
   ========================================================================== */
(() => {
    'use strict';

    const { COULEURS: C } = window.StudioMoteur;
    const kk = (g) => (g.paysage ? 1 : g.k);
    const mx = (a, b) => Math.max(a, b);

    /* --- Éléments communs --- */
    function fond(p) {
        p.degrade(0, 0, p.W, p.H, [0, 0, 1, 1], [[0, C.marine1], [0.45, C.marine2], [1, C.marine3]]);
    }

    function barreHaut(p, g) {
        p.rect(g.marge, 0, 128 * kk(g), 6, C.cyan);
    }

    function entete(p, g) {
        p.groupe('entete', 'En-tête (sceau et nom)', { x: g.sceau.x, y: g.sceau.y, w: 560, h: g.sceau.d }, () => {
            p.image('sceau', g.sceau.x, g.sceau.y, g.sceau.d, g.sceau.d);
            p.texte('UNIVERSITÉ ADVENTISTE', { x: g.tx, y: g.t1.c - g.t1.taille / 2, w: 640, taille: g.t1.taille, poids: 500, espacement: 0.055, couleur: C.blanc, interligne: 1, capitales: true, lignesMax: 1 });
            p.texte('Zurcher', { x: g.tx, y: g.t2.c - g.t2.taille / 2, w: 640, taille: g.t2.taille, poids: 700, espacement: -0.01, couleur: C.cyan, interligne: 1, lignesMax: 1 });
            if (g.paysage) { p.ligne(g.filetX[0], g.filetY, g.filetX[1], g.filetY, C.filet, 1.5); }
        });
    }

    function pied(p, g, gauche) {
        const k = kk(g), xr = g.W - g.marge;
        p.groupe('filet', 'Filet du pied de page', { x: g.filetX[0], y: g.pied.filetY - 6, w: g.filetX[1] - g.filetX[0], h: 12 }, () => p.ligne(g.filetX[0], g.pied.filetY, g.filetX[1], g.pied.filetY, C.filet, 1.5));
        p.texte(gauche, { id: 'pied', nom: 'Mot du pied de page', champs: ['pied'], x: g.marge, y: g.pied.y - 17, w: g.W * 0.62, taille: 22 * Math.max(k, 0.9), poids: 500, couleur: C.pale, interligne: 1.35, lignesMax: 1 });
        p.groupe('marque', 'Signature « UAZ »', { x: xr - 126, y: g.pied.y - 14, w: 126, h: 28 }, () => {
            p.ligne(xr - 126, g.pied.y, xr - 78, g.pied.y, C.cyan, 2);
            p.texte('UAZ', { x: xr - 90, y: g.pied.y - 12, w: 90, taille: 20, poids: 800, espacement: 0.3, couleur: C.cyan, interligne: 1.2, alignement: 'droite', capitales: true });
        });
    }

    function etiquette(p, x, yc, texte, w, id = 'etiquette') {
        p.groupe(id, 'Étiquette', { x, y: yc - 16, w: Math.min(w, 80 + String(texte).length * 17), h: 32 }, () => {
            p.ligne(x, yc, x + 56, yc, C.cyan, 3);
            p.texte(texte, { x: x + 74, y: yc - 15, w: w - 74, taille: 20, poids: 800, espacement: 0.3, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1, nom: 'Étiquette' });
        }, { champs: ['etiquette'] });
    }

    /** Les lettres U A Z, très pâles, empilées dans le panneau de la photo (écran en largeur seulement). */
    function filigraneUAZ(p, g) {
        if (!g.paysage) { return; }
        p.groupe('filigrane', 'Lettres « UAZ »', { x: g.photo.x + 50, y: 32, w: 300, h: 990 }, () => {
            [['U', 295], ['A', 655], ['Z', 1018]].forEach(([lettre, base]) => {
                p.texte(lettre, { x: g.photo.x + 50, y: base - 263, w: 300, taille: 306, poids: 900, couleur: 'rgba(255,255,255,0.055)', interligne: 1, alignement: 'centre', capitales: true, espacement: 0 });
            });
        });
    }

    /** Un voile marine discret en haut et en bas d'une photo : le texte cyan et blanc reste lisible même sur une photo très claire. */
    function voile(p, r, haut, bas) {
        if (haut) { p.degrade(r.x, r.y, r.w, haut, [0, 0, 0, 1], [[0, 'rgba(14,28,77,0.78)'], [1, 'rgba(14,28,77,0)']]); }
        if (bas) { p.degrade(r.x, r.y + r.h - bas, r.w, bas, [0, 0, 0, 1], [[0, 'rgba(14,28,77,0)'], [1, 'rgba(14,28,77,0.68)']]); }
    }

    /** Les photos d'un écran : une, deux ou trois bandes, qui se fondent dans le marine. */
    function bandePhotos(p, g, slots) {
        const n = slots.length, r = g.photo;
        if (g.paysage) {
            const hauteurs = { 1: [1080], 2: [540, 538], 3: [275, 523, 280] }[n];
            let y = r.y;
            slots.forEach((s, i) => {
                const h = hauteurs[i];
                p.photo(s, { x: r.x, y, w: r.w, h: h - (i < n - 1 ? 2 : 0) }, { fondu: { cote: 'gauche', longueur: 380 }, id: `photo:${s}`, nom: `Photo ${i + 1}` });
                y += h;
            });

            return;
        }
        const largeurs = { 1: [1], 2: [0.5, 0.5], 3: [0.3, 0.4, 0.3] }[n];
        let x = 0;
        slots.forEach((s, i) => {
            const w = Math.round(r.w * largeurs[i]);
            p.photo(s, { x, y: r.y, w: w - (i < n - 1 ? 3 : 0), h: r.h }, { fondu: { cote: 'bas', longueur: 170 }, id: `photo:${s}`, nom: `Photo ${i + 1}` });
            x += w;
        });
    }

    /** Titre en deux couleurs : la partie blanche, puis la partie cyan, à la suite. */
    function titre(p, g, blanc, accent, o) {
        return p.texteCouleurs([{ t: blanc, c: C.blanc }, { t: o.saut ? `\n${accent}` : ` ${accent}`, c: C.cyan }], Object.assign({
            poids: 900, espacement: -0.04, interligne: 1, capitales: false, nom: 'Titre', id: 'titre', champs: o.champs,
        }, o));
    }

    /** Un grand guillemet ouvrant (deux « virgules » pleines), dessiné : le guillemet de la police est trop raide. s = hauteur. */
    function guillemet(p, x, y, s, couleur) {
        const c = p.c;
        c.fillStyle = couleur;
        [0, 1].forEach((i) => {
            const ox = x + i * s * 0.74;
            c.beginPath();
            c.arc(ox + s * 0.24, y + s * 0.66, s * 0.24, 0, Math.PI * 2);
            c.fill();
            c.beginPath();
            c.moveTo(ox + 0.005 * s, y + 0.66 * s);
            c.bezierCurveTo(ox + 0.0 * s, y + 0.30 * s, ox + 0.16 * s, y + 0.06 * s, ox + 0.46 * s, y);
            c.bezierCurveTo(ox + 0.26 * s, y + 0.18 * s, ox + 0.40 * s, y + 0.34 * s, ox + 0.48 * s, y + 0.66 * s);
            c.closePath();
            c.fill();
        });
    }

    const OUI = [{ v: '1', l: '1 photo' }, { v: '2', l: '2 photos' }, { v: '3', l: '3 photos' }];

    /* ======================================================================
       Les modèles
       ====================================================================== */
    const MODELES = {};

    /* --- 1. Écran d'accueil : « Bienvenue », « Culte du Sabbat », « Semaine de prière »… --- */
    MODELES.accueil = {
        id: 'accueil', code: 'ACC', nom: 'Écran d’accueil', icone: 'bi-display', genre: 'Écrans',
        description: 'Le grand écran d’ouverture d’un culte, d’une semaine de prière, d’une conférence : titre en deux couleurs, sous-titre, orateur et photos.',
        formats: ['16:9', '1:1', '4:5', '9:16'],
        champs: [
            { id: 'etiquette', libelle: 'Étiquette', type: 'texte', max: 28, defaut: 'Bienvenue' },
            { id: 'titre1', libelle: 'Titre, 1re ligne (blanche)', type: 'texte', max: 34, defaut: 'Semaine de' },
            { id: 'titre2', libelle: 'Titre, 2e ligne (cyan)', type: 'texte', max: 34, defaut: 'Prière' },
            { id: 'sousTitre', libelle: 'Sous-titre', type: 'texte', max: 70, defaut: 'Vivre dans la société' },
            { id: 'precision', libelle: 'Orateur ou précision', type: 'texte', max: 80, defaut: 'Pasteur RANDRIANARIVONY Herimanitra' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'Bienvenue à toutes et à tous.' },
            { id: 'photos', libelle: 'Nombre de photos', type: 'choix', options: OUI, defaut: '3' },
            { id: 'filigrane', libelle: 'Lettres « UAZ » en filigrane', type: 'interrupteur', defaut: true },
        ],
        images: [
            { id: 'photo1', libelle: 'Photo 1' },
            { id: 'photo2', libelle: 'Photo 2', visible: (d) => Number(d.photos) >= 2 },
            { id: 'photo3', libelle: 'Photo 3', visible: (d) => Number(d.photos) >= 3 },
        ],
        variantes: [
            { nom: 'Culte du Sabbat', champs: { etiquette: 'Bienvenue', titre1: 'Culte du', titre2: 'Sabbat', sousTitre: 'Le culte commence bientôt', precision: 'Un temps de foi et de communion.', pied: 'Bienvenue à toutes et à tous.', photos: '1' } },
            { nom: 'Semaine de prière', champs: { etiquette: 'Bienvenue', titre1: 'Semaine de', titre2: 'Prière', sousTitre: 'Vivre dans la société', precision: 'Pasteur RANDRIANARIVONY Herimanitra', pied: 'Bienvenue à toutes et à tous.', photos: '3' } },
        ],
        dessiner(p, d, g) {
            const k = kk(g), n = Math.min(3, Math.max(1, Number(d.photos) || 1)), t = g.texte;
            fond(p);
            bandePhotos(p, g, ['photo1', 'photo2', 'photo3'].slice(0, n));
            if (g.paysage) { voile(p, g.photo, 230, 240); }
            if (d.filigrane) { filigraneUAZ(p, g); }
            barreHaut(p, g);
            entete(p, g);

            const yc = g.paysage ? 310 : t.y0 + 50;
            etiquette(p, t.x, yc, d.etiquette, t.w);
            const tt = titre(p, g, d.titre1, d.titre2, { x: t.x, y: yc + 40, w: t.w, taille: 140 * k, min: 56, saut: true, majuscules: true, capitales: true, lignesMax: 3, champs: ['titre1', 'titre2'] });
            const st = p.texte(d.sousTitre, { id: 'sousTitre', nom: 'Sous-titre', champs: ['sousTitre'], x: t.x, y: tt.bas + 30 * k, w: t.w, taille: 36 * Math.max(k, 0.85), poids: 500, couleur: C.blanc, interligne: 1.25, lignesMax: 2 });
            if (d.precision) { p.texte(d.precision, { id: 'precision', nom: 'Orateur', champs: ['precision'], x: t.x, y: st.bas + 4, w: t.w, taille: 24 * Math.max(k, 0.9), poids: 500, couleur: C.doux, interligne: 1.35, lignesMax: 2 }); }
            pied(p, g, d.pied);
        },
    };

    /* --- 2. Direct : « Le direct commence bientôt », « en direct », « terminé » --- */
    MODELES.direct = {
        id: 'direct', code: 'DIR', nom: 'Direct', icone: 'bi-broadcast', genre: 'Écrans',
        description: 'L’écran d’un direct (culte, conférence, cérémonie) : « commence bientôt », « en direct », « terminé ». Photo en pleine page, décor de visée, thème de l’année.',
        formats: ['16:9', '1:1', '4:5', '9:16'],
        champs: [
            { id: 'surtitre', libelle: 'Surtitre', type: 'texte', max: 20, defaut: 'Live' },
            { id: 'contexte', libelle: 'Contexte', type: 'texte', max: 40, defaut: 'Culte du Sabbat' },
            { id: 'titre', libelle: 'Titre (blanc)', type: 'texte', max: 40, defaut: 'Le direct commence' },
            { id: 'accent', libelle: 'Fin du titre (cyan)', type: 'texte', max: 24, defaut: 'bientôt' },
            { id: 'sousTitre', libelle: 'Sous-titre', type: 'texte', max: 90, defaut: 'Bienvenue à l’Université Adventiste Zurcher.' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'Merci de votre présence.' },
            { id: 'badge', libelle: 'Pastille', type: 'texte', max: 30, defaut: 'Diffusion en direct' },
            { id: 'theme1', libelle: 'Thème (ligne 1)', type: 'texte', max: 34, defaut: 'Toutes choses nouvelles' },
            { id: 'theme2', libelle: 'Thème (ligne 2)', type: 'texte', max: 34, defaut: '#AllThingsNew' },
            { id: 'decor', libelle: 'Décor de visée (cercles)', type: 'interrupteur', defaut: true },
        ],
        images: [{ id: 'photo1', libelle: 'Photo' }],
        variantes: [
            { nom: 'Commence bientôt', champs: { titre: 'Le direct commence', accent: 'bientôt', sousTitre: 'Bienvenue à l’Université Adventiste Zurcher.', pied: 'Merci de votre présence.' } },
            { nom: 'En direct', champs: { titre: 'Nous sommes', accent: 'en direct', sousTitre: 'Rejoignez-nous maintenant.', pied: 'Merci de votre présence.' } },
            { nom: 'Terminé', champs: { titre: 'Le direct est', accent: 'terminé', sousTitre: 'Nous vous donnons rendez-vous une prochaine fois', pied: 'Merci de votre présence.' } },
        ],
        dessiner(p, d, g) {
            const k = kk(g), t = g.texte;
            fond(p);
            const ph = g.paysage ? { x: 1120, y: 0, w: 800, h: 1080 } : g.photo;
            p.photo('photo1', ph, Object.assign({ id: 'photo:photo1', nom: 'Photo' }, g.paysage ? {} : { fondu: { cote: 'bas', longueur: 170 } }));
            voile(p, ph, 230, g.paysage ? 330 : 0);
            const cx = g.paysage ? 1520 : g.W / 2, cy = g.paysage ? 250 : ph.y + ph.h * 0.42, R = g.paysage ? 340 : 300;
            if (d.decor) {
                p.groupe('decor', 'Décor de visée', { x: cx - R, y: cy - R, w: 2 * R, h: 2 * R }, () => {
                    p.cercle(cx, cy, R, { trait: 'rgba(0,196,212,0.30)', epaisseur: 1.5 });
                    p.cercle(cx, cy, R * 0.86, { trait: 'rgba(0,196,212,0.14)', epaisseur: 1.5 });
                    if (g.paysage) {
                        p.c.strokeStyle = 'rgba(255,255,255,0.16)'; p.c.lineWidth = 1.5; p.c.strokeRect(1150, 340, 580, 380);
                        p.ligne(1518, 0, 1518, 14, C.cyan, 2); p.ligne(1518, 686, 1518, 706, C.cyan, 2);
                        [1245, 1348, 1450].forEach((x) => p.cercle(x, 928, 40, { remplissage: 'rgba(255,255,255,0.12)' }));
                    }
                });
            }
            if (g.paysage) { p.groupe('barre-verticale', 'Barre cyan', { x: 1104, y: 0, w: 8, h: 1080 }, () => p.rect(1104, 0, 8, 1080, C.cyan)); }
            barreHaut(p, g);
            entete(p, g);

            const bx = g.paysage ? 1386 : g.marge + 8, by = g.paysage ? 105 : ph.y + 50;
            if (d.badge) {
                p.groupe('badge', 'Pastille « en direct »', { x: bx - 10, y: by - 16, w: 440, h: 32 }, () => {
                    p.cercle(bx, by, 5, { remplissage: C.cyan });
                    p.texte(d.badge, { x: bx + 22, y: by - 15, w: 420, taille: 18, poids: 700, espacement: 0.3, couleur: C.cyan, interligne: 1.7, majuscules: true, capitales: true, lignesMax: 1 });
                }, { champs: ['badge'] });
            }
            if (d.theme1 && g.paysage) {
                p.groupe('theme', 'Thème de l’année', { x: 1620, y: 756, w: 290, h: 34 }, () => {
                    p.texte(d.theme1, { x: 1620, y: 756, w: 290, taille: 11, poids: 800, espacement: 0.25, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1 });
                    if (d.theme2) { p.texte(d.theme2, { x: 1620, y: 774, w: 290, taille: 11, poids: 500, espacement: 0.2, couleur: 'rgba(255,255,255,0.55)', interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1 }); }
                }, { champs: ['theme1', 'theme2'] });
            }

            const w = g.paysage ? 960 : t.w;
            let y = g.paysage ? 241 : t.y0 + 20;
            const mS = Math.max(k, 1);
            p.texte(d.surtitre, { id: 'surtitre', nom: 'Surtitre', champs: ['surtitre'], x: t.x, y, w, taille: 12 * mS, poids: 800, espacement: 0.3, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1 });
            y += 17 * mS;
            p.texte(d.contexte, { id: 'contexte', nom: 'Contexte', champs: ['contexte'], x: t.x, y, w, taille: 12 * mS, poids: 500, espacement: 0.2, couleur: C.pale, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1 });
            p.groupe('trait', 'Trait cyan', { x: t.x, y: y + 30, w: 72, h: 6 }, () => p.rect(t.x, y + 30, 72, 6, C.cyan));
            const tt = titre(p, g, d.titre, d.accent, { x: t.x, y: y + 62, w, taille: 150 * k, min: 56, interligne: 0.9, lignesMax: 3, champs: ['titre', 'accent'] });
            p.texte(d.sousTitre, { id: 'sousTitre', nom: 'Sous-titre', champs: ['sousTitre'], x: t.x, y: tt.bas + 22, w, taille: 26 * Math.max(k, 0.9), poids: 500, couleur: C.doux, interligne: 1.35, lignesMax: 2 });
            pied(p, g, d.pied);
        },
    };

    /* --- 3. Affiche photo : une grande photo, un titre mêlant fin et gras sur un voile marine --- */
    MODELES['affiche-photo'] = {
        id: 'affiche-photo', code: 'AFF', nom: 'Affiche photo', icone: 'bi-image', genre: 'Affiches',
        description: 'Une grande photo, un titre qui mêle trait fin et gras, un voile marine qui se fond dans l’image. Pour un événement, un salon, une journée.',
        formats: ['4:5', '1:1', '9:16', '16:9'],
        champs: [
            { id: 'ligne1', libelle: 'Titre, ligne 1 (fine)', type: 'texte', max: 24, defaut: 'Journée' },
            { id: 'ligne2', libelle: 'Titre, ligne 2 (grasse)', type: 'texte', max: 24, defaut: 'Portes ouvertes' },
            { id: 'ligne3', libelle: 'Titre, ligne 3 (fine)', type: 'texte', max: 24, defaut: 'UAZ 2027' },
            { id: 'infos', libelle: 'Date et lieu', type: 'texte', max: 80, defaut: 'Samedi 14 mars · Campus de l’UAZ, Antsirabe' },
            { id: 'cta', libelle: 'Appel en bas', type: 'texte', max: 50, defaut: 'zurcher.edu.mg' },
            { id: 'sceau', libelle: 'Sceau de l’université', type: 'interrupteur', defaut: true },
        ],
        images: [{ id: 'photo1', libelle: 'Photo de fond' }],
        variantes: [],
        dessiner(p, d, g) {
            const W = g.W, H = g.H;
            fond(p);
            p.photo('photo1', { x: 0, y: 0, w: W, h: H }, { id: 'photo:photo1', nom: 'Photo de fond' });
            if (g.paysage) { p.degrade(0, 0, W, H, [0, 0, 0.7, 0], [[0, 'rgba(10,78,134,0.96)'], [0.6, 'rgba(10,78,134,0.62)'], [1, 'rgba(10,78,134,0)']]); } else { p.degrade(0, 0, W, H, [0, 0, 0, 0.62], [[0, 'rgba(10,78,134,0.97)'], [0.55, 'rgba(14,64,126,0.70)'], [1, 'rgba(19,60,120,0)']]); }
            p.degrade(0, H * 0.78, W, H * 0.22, [0, 0, 0, 1], [[0, 'rgba(14,28,77,0)'], [1, 'rgba(14,28,77,0.85)']]);
            const m = g.paysage ? 110 : W * 0.1, s = (g.paysage ? 0.07 : 0.108) * W;
            const tw = g.paysage ? W * 0.55 : W - 2 * m;
            const y = g.paysage ? 150 : H * 0.085;
            const l1 = p.texte(d.ligne1, { id: 'ligne1', nom: 'Titre, ligne 1', champs: ['ligne1'], x: m, y, w: tw, taille: s, min: 36, poids: 300, espacement: -0.01, couleur: C.blanc, interligne: 1.12, lignesMax: 1 });
            const l2 = p.texte(d.ligne2, { id: 'ligne2', nom: 'Titre, ligne 2', champs: ['ligne2'], x: m, y: l1.bas, w: tw, taille: s, min: 36, poids: 800, espacement: -0.02, couleur: C.blanc, interligne: 1.12, lignesMax: 2 });
            p.texte(d.ligne3, { id: 'ligne3', nom: 'Titre, ligne 3', champs: ['ligne3'], x: m, y: l2.bas, w: tw, taille: s, min: 36, poids: 300, espacement: 0.02, couleur: C.blanc, interligne: 1.12, lignesMax: 1, majuscules: true, capitales: true });
            if (d.sceau) {
                const sd = 90 * (g.paysage ? 1 : 0.9), sx = W - m - sd, sy = g.paysage ? 60 : H * 0.05;
                p.groupe('sceau', 'Sceau de l’université', { x: sx, y: sy, w: sd, h: sd }, () => p.image('sceau', sx, sy, sd, sd), { champs: ['sceau'] });
            }
            const by = H - (g.paysage ? 110 : W * 0.115);
            p.texte(d.infos, { id: 'infos', nom: 'Date et lieu', champs: ['infos'], x: m, y: by, w: W - 2 * m, taille: (g.paysage ? 30 : W * 0.03), min: 16, poids: 600, couleur: C.blanc, interligne: 1.3, lignesMax: 2 });
            p.texte(d.cta, { id: 'cta', nom: 'Appel', champs: ['cta'], x: m, y: by + (g.paysage ? 40 : W * 0.05), w: W - 2 * m, taille: (g.paysage ? 24 : W * 0.026), min: 14, poids: 800, espacement: 0.12, couleur: C.cyan, interligne: 1.3, lignesMax: 1, majuscules: true, capitales: true });
        },
    };

    /* --- 4. Salutation : « Bon Sabbat », un message du jour sur une photo --- */
    MODELES.salutation = {
        id: 'salutation', code: 'SAL', nom: 'Salutation (Bon Sabbat)', icone: 'bi-sunrise', genre: 'Affiches',
        description: 'Le message du jour sur une belle photo : salutation en deux langues séparées par un filet, thème de l’année et sceau en bas.',
        formats: ['1:1', '4:5', '9:16', '16:9'],
        champs: [
            { id: 'ligne1', libelle: 'Salutation, langue 1', type: 'texte', max: 40, defaut: 'Bon Sabbat' },
            { id: 'ligne2', libelle: 'Salutation, langue 2', type: 'texte', max: 50, defaut: 'Mirary Sabata mahafinaritra' },
            { id: 'compte', libelle: 'Compte ou signature', type: 'texte', max: 30, defaut: '@uaz.mg' },
            { id: 'theme1', libelle: 'Thème, langue 1', type: 'longtexte', max: 50, defaut: 'Toutes Choses\nNouvelles' },
            { id: 'theme2', libelle: 'Thème, langue 2', type: 'longtexte', max: 50, defaut: 'Havaozina\nNy Zavatra Rehetra' },
            { id: 'sceau', libelle: 'Sceau de l’université', type: 'interrupteur', defaut: true },
        ],
        images: [{ id: 'photo1', libelle: 'Photo de fond' }],
        variantes: [
            { nom: 'Bon Sabbat', champs: { ligne1: 'Bon Sabbat', ligne2: 'Mirary Sabata mahafinaritra' } },
            { nom: 'Bonne semaine', champs: { ligne1: 'Bonne semaine', ligne2: 'Faniny mahafinaritra' } },
            { nom: 'Bonne année', champs: { ligne1: 'Bonne année', ligne2: 'Arahaba ny taona vaovao' } },
        ],
        dessiner(p, d, g) {
            const W = g.W, H = g.H, m = W * (g.paysage ? 0.06 : 0.08);
            fond(p);
            p.photo('photo1', { x: 0, y: 0, w: W, h: H }, { id: 'photo:photo1', nom: 'Photo de fond' });
            p.degrade(0, 0, W, H, [0, 0, 0, 1], [[0, 'rgba(14,28,77,0.30)'], [0.5, 'rgba(14,28,77,0.12)'], [1, 'rgba(14,28,77,0.88)']]);
            const base = H - (g.paysage ? 0.2 : 0.2) * W;
            const s = (g.paysage ? 0.04 : 0.057) * W;
            const wL = W - 2 * m;
            p.texte(d.ligne2, { id: 'ligne2', nom: 'Salutation, langue 2', champs: ['ligne2'], x: m, y: base - s * 1.25, w: wL, taille: s, min: 26, poids: 600, espacement: -0.015, couleur: C.blanc, interligne: 1.15, lignesMax: 1 });
            p.groupe('filet-salut', 'Filet', { x: m, y: base - s * 1.25 - 28, w: W * 0.34, h: 10 }, () => p.ligne(m, base - s * 1.25 - 20, m + W * 0.34, base - s * 1.25 - 20, 'rgba(255,255,255,0.4)', 1.5));
            p.texte(d.ligne1, { id: 'ligne1', nom: 'Salutation, langue 1', champs: ['ligne1'], x: m, y: base - s * 1.25 - 28 - s * 1.15, w: wL, taille: s, min: 30, poids: 600, espacement: -0.015, couleur: C.blanc, interligne: 1.15, lignesMax: 1 });
            const yb = base + (g.paysage ? 36 : 40), t = (g.paysage ? 0.0165 : 0.027) * W;
            if (d.compte) { p.texte(d.compte, { id: 'compte', nom: 'Compte', champs: ['compte'], x: m, y: yb + t * 0.4, w: W * 0.2, taille: t * 1.15, min: 12, poids: 500, espacement: 0.02, couleur: 'rgba(255,255,255,0.9)', interligne: 1.2, lignesMax: 1 }); }
            if (d.theme1) { p.texte(d.theme1, { id: 'theme1', nom: 'Thème, langue 1', champs: ['theme1'], x: W * 0.24, y: yb, w: W * 0.26, taille: t * 1.2, min: 12, poids: 800, espacement: -0.01, couleur: C.blanc, interligne: 1.12, alignement: 'droite', lignesMax: 3 }); }
            if (d.theme2) { p.texte(d.theme2, { id: 'theme2', nom: 'Thème, langue 2', champs: ['theme2'], x: W * 0.53, y: yb, w: W * 0.3, taille: t * 1.2, min: 12, poids: 800, espacement: -0.01, couleur: C.blanc, interligne: 1.12, lignesMax: 3 }); }
            if (d.sceau) { const sd = W * (g.paysage ? 0.07 : 0.1); p.groupe('sceau', 'Sceau de l’université', { x: W - m - sd, y: yb - 6, w: sd, h: sd }, () => p.image('sceau', W - m - sd, yb - 6, sd, sd), { champs: ['sceau'] }); }
        },
    };

    /* --- 5. Annonce : un événement avec sa date, son lieu et son entrée --- */
    MODELES.annonce = {
        id: 'annonce', code: 'ANN', nom: 'Annonce', icone: 'bi-megaphone', genre: 'Affiches',
        description: 'Un événement à annoncer : titre, sous-titre, trois informations pratiques (quand, où, pour qui) et une ou deux photos.',
        formats: ['16:9', '1:1', '4:5', '9:16'],
        champs: [
            { id: 'etiquette', libelle: 'Étiquette', type: 'texte', max: 28, defaut: 'Événement' },
            { id: 'titre1', libelle: 'Titre (blanc)', type: 'texte', max: 34, defaut: 'Journée portes' },
            { id: 'titre2', libelle: 'Fin du titre (cyan)', type: 'texte', max: 30, defaut: 'ouvertes' },
            { id: 'sousTitre', libelle: 'Sous-titre', type: 'texte', max: 90, defaut: 'Venez découvrir nos formations et le campus.' },
            { id: 'i1t', libelle: 'Info 1 : intitulé', type: 'texte', max: 16, defaut: 'Quand' },
            { id: 'i1', libelle: 'Info 1 : texte', type: 'texte', max: 40, defaut: 'Samedi 14 mars, 9 h' },
            { id: 'i2t', libelle: 'Info 2 : intitulé', type: 'texte', max: 16, defaut: 'Où' },
            { id: 'i2', libelle: 'Info 2 : texte', type: 'texte', max: 40, defaut: 'Campus de l’UAZ, Antsirabe' },
            { id: 'i3t', libelle: 'Info 3 : intitulé', type: 'texte', max: 16, defaut: 'Entrée' },
            { id: 'i3', libelle: 'Info 3 : texte', type: 'texte', max: 40, defaut: 'Libre, sur inscription' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'Renseignements : zurcher.edu.mg' },
            { id: 'photos', libelle: 'Nombre de photos', type: 'choix', options: OUI.slice(0, 2), defaut: '1' },
            { id: 'filigrane', libelle: 'Lettres « UAZ » en filigrane', type: 'interrupteur', defaut: true },
        ],
        images: [{ id: 'photo1', libelle: 'Photo 1' }, { id: 'photo2', libelle: 'Photo 2', visible: (d) => Number(d.photos) >= 2 }],
        variantes: [],
        dessiner(p, d, g) {
            const k = kk(g), n = Math.min(2, Math.max(1, Number(d.photos) || 1)), t = g.texte;
            fond(p);
            bandePhotos(p, g, ['photo1', 'photo2'].slice(0, n));
            if (g.paysage) { voile(p, g.photo, 230, 240); }
            if (d.filigrane) { filigraneUAZ(p, g); }
            barreHaut(p, g);
            entete(p, g);

            const yc = g.paysage ? 310 : t.y0 + 50;
            etiquette(p, t.x, yc, d.etiquette, t.w);
            const tt = titre(p, g, d.titre1, d.titre2, { x: t.x, y: yc + 40, w: t.w, taille: 118 * k, min: 52, saut: false, majuscules: true, capitales: true, lignesMax: 3, champs: ['titre1', 'titre2'] });
            const st = p.texte(d.sousTitre, { id: 'sousTitre', nom: 'Sous-titre', champs: ['sousTitre'], x: t.x, y: tt.bas + 22 * k, w: t.w, taille: 32 * Math.max(k, 0.85), poids: 500, couleur: C.doux, interligne: 1.3, lignesMax: 2 });

            // Trois informations, en colonnes, sous le sous-titre et au plus près du pied de page.
            const colW = t.w / 3, vs = 26 * Math.max(k, 0.9);
            const y0 = Math.min(Math.max(st.bas + 34, g.paysage ? 790 : g.pied.filetY - 160), g.pied.filetY - 125);
            [['i1t', 'i1'], ['i2t', 'i2'], ['i3t', 'i3']].forEach(([a, b], i) => {
                const x = t.x + i * colW + (i ? 24 : 0);
                p.groupe(`info${i + 1}`, `Information ${i + 1}`, { x, y: y0, w: colW - 36, h: 110 * Math.max(k, 0.9) }, () => {
                    if (i) { p.ligne(t.x + i * colW, y0, t.x + i * colW, y0 + 110 * Math.max(k, 0.9), C.filet, 1.5); }
                    p.texte(d[a], { x, y: y0, w: colW - 36, taille: 14, poids: 800, espacement: 0.25, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1 });
                    p.texte(d[b], { x, y: y0 + 26, w: colW - 36, taille: vs, min: 16, poids: 700, couleur: C.blanc, interligne: 1.25, lignesMax: 3, nom: 'Information' });
                }, { champs: [a, b] });
            });
            pied(p, g, d.pied);
        },
    };

    /* --- 6. Verset : une citation, sur une photo sombre --- */
    MODELES.verset = {
        id: 'verset', code: 'VER', nom: 'Verset ou citation', icone: 'bi-quote', genre: 'Affiches',
        description: 'Un verset, une citation, une parole du jour : le texte en grand sur une photo assombrie, avec le logo du thème de l’année.',
        formats: ['16:9', '1:1', '4:5', '9:16'],
        champs: [
            { id: 'etiquette', libelle: 'Étiquette', type: 'texte', max: 28, defaut: 'Verset du jour' },
            { id: 'texte', libelle: 'Le texte', type: 'longtexte', max: 260, defaut: 'Voici, je fais toutes choses nouvelles.' },
            { id: 'reference', libelle: 'Référence ou auteur', type: 'texte', max: 60, defaut: 'Apocalypse 21.5' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'Université Adventiste Zurcher' },
            { id: 'logoTheme', libelle: 'Logo « Toutes choses nouvelles »', type: 'interrupteur', defaut: true },
        ],
        images: [{ id: 'photo1', libelle: 'Photo de fond' }],
        variantes: [],
        dessiner(p, d, g) {
            const k = kk(g), t = g.texte;
            fond(p);
            if (p.env.photos.photo1 && p.env.photos.photo1.img) {
                p.photo('photo1', { x: 0, y: 0, w: g.W, h: g.H }, { id: 'photo:photo1', nom: 'Photo de fond' });
                if (g.paysage) { p.degrade(0, 0, g.W, g.H, [0, 0, 1, 0], [[0, 'rgba(14,28,77,0.94)'], [0.55, 'rgba(19,35,91,0.80)'], [1, 'rgba(19,35,91,0.45)']]); } else { p.degrade(0, 0, g.W, g.H, [0, 0, 0, 1], [[0, 'rgba(14,28,77,0.78)'], [0.5, 'rgba(14,28,77,0.88)'], [1, 'rgba(14,28,77,0.96)']]); }
            } else if (p.env.apercu) {
                p.photo('photo1', { x: g.W * 0.55, y: 0, w: g.W * 0.45, h: g.H }, { id: 'photo:photo1', nom: 'Photo de fond' });
            }
            barreHaut(p, g);
            entete(p, g);

            const x = t.x, w = g.paysage ? 1060 : t.w;
            const y0 = g.paysage ? 262 : g.filetY + 60 * k;
            p.groupe('guillemet', 'Guillemet', { x: x + 4, y: y0 + 6, w: 82 * k * 1.3, h: 82 * k }, () => guillemet(p, x + 4, y0 + 6, 82 * k, 'rgba(0,196,212,0.85)'));
            etiquette(p, x + 8, y0 + 140 * k, d.etiquette, w);
            const q = p.texte(d.texte, { id: 'texte', nom: 'Le texte', champs: ['texte'], x, y: y0 + 170 * k, w, taille: 82 * k, min: 34, poids: 700, espacement: -0.02, couleur: C.blanc, interligne: 1.12, lignesMax: 8 });
            if (d.reference) { p.texte(d.reference, { id: 'reference', nom: 'Référence', champs: ['reference'], x, y: q.bas + 26, w, taille: 28 * Math.max(k, 0.85), poids: 800, espacement: 0.16, couleur: C.cyan, interligne: 1.3, majuscules: true, capitales: true, lignesMax: 1 }); }
            if (d.logoTheme && p.env.logos.theme) {
                const lw = g.paysage ? 300 : 250, lh = lw * (p.env.logos.theme.naturalHeight / p.env.logos.theme.naturalWidth);
                p.groupe('logo-theme', 'Logo « Toutes choses nouvelles »', { x: g.W - g.marge - lw, y: g.pied.filetY - lh - 34, w: lw, h: lh }, () => p.image('theme', g.W - g.marge - lw, g.pied.filetY - lh - 34, lw), { champs: ['logoTheme'] });
            }
            pied(p, g, d.pied);
        },
    };

    /* --- 7. Témoignage : une parole, un portrait rond --- */
    MODELES.temoignage = {
        id: 'temoignage', code: 'TEM', nom: 'Témoignage', icone: 'bi-chat-quote', genre: 'Affiches',
        description: 'La parole d’un étudiant, d’un diplômé, d’un enseignant : sa phrase en grand, son portrait dans un rond, son nom et sa fonction.',
        formats: ['1:1', '4:5', '9:16', '16:9'],
        champs: [
            { id: 'etiquette', libelle: 'Étiquette', type: 'texte', max: 28, defaut: 'Témoignage' },
            { id: 'texte', libelle: 'La parole', type: 'longtexte', max: 220, defaut: 'À l’UAZ, j’ai trouvé une famille et une vision pour mon avenir.' },
            { id: 'nom', libelle: 'Nom', type: 'texte', max: 40, defaut: 'Prénom NOM' },
            { id: 'fonction', libelle: 'Fonction ou promotion', type: 'texte', max: 60, defaut: 'Diplômé en sciences infirmières, promotion 2025' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'Rejoignez-nous : zurcher.edu.mg' },
        ],
        images: [{ id: 'photo1', libelle: 'Portrait' }],
        variantes: [],
        dessiner(p, d, g) {
            const k = kk(g), t = g.texte;
            fond(p);
            barreHaut(p, g);
            entete(p, g);
            const D = g.paysage ? 560 : Math.min(g.W * 0.42, 440);
            const cx = g.paysage ? 1500 : g.W / 2, cy = g.paysage ? 540 : g.filetY + 60 + D / 2;
            p.groupe('portrait', 'Portrait', { x: cx - D / 2 - 14, y: cy - D / 2 - 14, w: D + 28, h: D + 28 }, () => {
                p.cercle(cx, cy, D / 2 + 14, { trait: C.cyan, epaisseur: 4 });
                p.photo('photo1', { x: cx - D / 2, y: cy - D / 2, w: D, h: D }, { cercle: true, id: 'photo:photo1', nom: 'Portrait' });
            });
            const x = t.x, w = g.paysage ? 900 : t.w, y0 = g.paysage ? 262 : cy + D / 2 + 50;
            if (g.paysage) { guillemet(p, x + 4, y0, 70, 'rgba(0,196,212,0.85)'); }
            etiquette(p, x + (g.paysage ? 8 : 0), g.paysage ? y0 + 120 : y0, d.etiquette, w);
            const q = p.texte(d.texte, { id: 'texte', nom: 'La parole', champs: ['texte'], x, y: (g.paysage ? y0 + 150 : y0 + 30), w, taille: 66 * k, min: 30, poids: 700, espacement: -0.02, couleur: C.blanc, interligne: 1.15, lignesMax: g.paysage ? 6 : 5 });
            const n = p.texte(d.nom, { id: 'nom', nom: 'Nom', champs: ['nom'], x, y: q.bas + 24, w, taille: 30 * Math.max(k, 0.9), poids: 800, espacement: 0.12, couleur: C.cyan, interligne: 1.3, majuscules: true, capitales: true, lignesMax: 1 });
            p.texte(d.fonction, { id: 'fonction', nom: 'Fonction', champs: ['fonction'], x, y: n.bas + 2, w, taille: 24 * Math.max(k, 0.9), poids: 500, couleur: C.doux, interligne: 1.35, lignesMax: 2 });
            pied(p, g, d.pied);
        },
    };

    /* --- 8. Programme : un horaire, des activités --- */
    const LIGNES = [1, 2, 3, 4, 5, 6];
    const champsLigne = (i, h, t, l) => [
        { id: `r${i}h`, libelle: `Ligne ${i} : heure`, type: 'texte', max: 14, defaut: h },
        { id: `r${i}t`, libelle: `Ligne ${i} : activité`, type: 'texte', max: 44, defaut: t },
        { id: `r${i}l`, libelle: `Ligne ${i} : précision`, type: 'texte', max: 44, defaut: l },
    ];
    MODELES.programme = {
        id: 'programme', code: 'PRO', nom: 'Programme', icone: 'bi-list-ol', genre: 'Affiches',
        description: 'Un programme (semaine de prière, journée, conférence) : jusqu’à six lignes avec l’heure, l’activité et le lieu ou l’orateur.',
        formats: ['4:5', '1:1', '9:16', '16:9'],
        champs: [
            { id: 'titre', libelle: 'Titre (blanc)', type: 'texte', max: 30, defaut: 'Programme de la' },
            { id: 'accent', libelle: 'Fin du titre (cyan)', type: 'texte', max: 24, defaut: 'semaine' },
            ...champsLigne(1, '8 h', 'Culte du matin', 'Temple de l’UAZ'),
            ...champsLigne(2, '10 h', 'École du Sabbat', 'Salles de classe'),
            ...champsLigne(3, '12 h', 'Repas partagé', 'Cafétéria'),
            ...champsLigne(4, '15 h', 'Chants et témoignages', 'Amphithéâtre'),
            ...champsLigne(5, '', '', ''),
            ...champsLigne(6, '', '', ''),
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'Entrée libre · zurcher.edu.mg' },
        ],
        images: [{ id: 'photo1', libelle: 'Photo (écran en largeur)' }],
        variantes: [],
        dessiner(p, d, g) {
            const k = kk(g), t = g.texte, kf = mx(k, 0.8);
            fond(p);
            if (g.paysage) { p.photo('photo1', g.photo, { fondu: { cote: 'gauche', longueur: 380 }, id: 'photo:photo1', nom: 'Photo' }); voile(p, g.photo, 230, 240); }
            barreHaut(p, g);
            entete(p, g);
            const x = t.x, w = g.paysage ? 900 : t.w;
            const y0 = g.paysage ? 250 : g.filetY + 30;
            const tt = titre(p, g, d.titre, d.accent, { x, y: y0, w, taille: 96 * k, min: 44, interligne: 0.95, lignesMax: 2, champs: ['titre', 'accent'] });
            const rows = LIGNES.filter((i) => d[`r${i}t`] || d[`r${i}h`]);
            const top = tt.bas + 26, bottom = g.pied.filetY - 28;
            const rh = Math.min(rows.length ? (bottom - top) / rows.length : 0, 210 * kf);
            const f = Math.min(1.7, Math.max(0.85, rh / (138 * kf)));
            rows.forEach((i, idx) => {
                const y = top + idx * rh;
                p.groupe(`ligne${i}`, `Ligne ${i}`, { x, y, w, h: rh }, () => {
                    p.ligne(x, y, x + w, y, C.filet, 1.5);
                    const cx = x + 190 * kf * Math.min(f, 1.2);
                    p.texte(d[`r${i}h`], { x, y: y + 16 * f, w: 190 * kf * Math.min(f, 1.2), taille: 38 * kf * f, min: 20, poids: 800, couleur: C.cyan, interligne: 1.1, lignesMax: 1 });
                    p.texte(d[`r${i}t`], { x: cx, y: y + 14 * f, w: w - (cx - x), taille: 34 * kf * f, min: 18, poids: 700, couleur: C.blanc, interligne: 1.15, lignesMax: 1 });
                    if (d[`r${i}l`]) { p.texte(d[`r${i}l`], { x: cx, y: y + 14 * f + 44 * kf * f, w: w - (cx - x), taille: 22 * mx(k, 0.85) * f, min: 14, poids: 500, couleur: C.doux, interligne: 1.2, lignesMax: 1 }); }
                }, { champs: [`r${i}h`, `r${i}t`, `r${i}l`] });
            });
            pied(p, g, d.pied);
        },
    };

    /* --- 9. Inscriptions : une date limite, des filières --- */
    MODELES.inscriptions = {
        id: 'inscriptions', code: 'INS', nom: 'Inscriptions', icone: 'bi-mortarboard', genre: 'Affiches',
        description: 'L’appel à s’inscrire : titre, date limite en pastille, filières à la une et l’adresse où s’inscrire.',
        formats: ['4:5', '1:1', '9:16', '16:9'],
        champs: [
            { id: 'etiquette', libelle: 'Étiquette', type: 'texte', max: 34, defaut: 'Année universitaire 2026-2027' },
            { id: 'titre', libelle: 'Titre (blanc)', type: 'texte', max: 30, defaut: 'Inscriptions' },
            { id: 'accent', libelle: 'Fin du titre (cyan)', type: 'texte', max: 24, defaut: 'ouvertes' },
            { id: 'date', libelle: 'Date limite (pastille)', type: 'texte', max: 40, defaut: 'Jusqu’au 30 septembre' },
            { id: 'f1', libelle: 'Filière 1', type: 'texte', max: 40, defaut: 'Sciences infirmières' },
            { id: 'f2', libelle: 'Filière 2', type: 'texte', max: 40, defaut: 'Théologie' },
            { id: 'f3', libelle: 'Filière 3', type: 'texte', max: 40, defaut: 'Gestion et comptabilité' },
            { id: 'f4', libelle: 'Filière 4 (facultatif)', type: 'texte', max: 40, defaut: '' },
            { id: 'cta', libelle: 'Où s’inscrire', type: 'texte', max: 50, defaut: 'zurcher.edu.mg/admissions' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'Antsirabe, Madagascar' },
        ],
        images: [{ id: 'photo1', libelle: 'Photo' }],
        variantes: [],
        dessiner(p, d, g) {
            const k = kk(g);
            if (!g.paysage) {
                // en portrait, la photo est plus courte : il faut la place du titre, de la date, des filières et de l'adresse
                const ph = Math.round(g.photo.h * (g.H > 1500 ? 0.62 : 0.55));
                g = Object.assign({}, g, { photo: Object.assign({}, g.photo, { h: ph }), texte: Object.assign({}, g.texte, { y0: g.photo.y + ph - 24 }) });
            }
            const t = g.texte;
            fond(p);
            bandePhotos(p, g, ['photo1']);
            if (g.paysage) { voile(p, g.photo, 230, 240); }
            barreHaut(p, g);
            entete(p, g);
            const x = t.x, w = t.w;
            const yc = g.paysage ? 300 : t.y0 + 44;
            etiquette(p, x, yc, d.etiquette, w);
            const tt = titre(p, g, d.titre, d.accent, { x, y: yc + 34, w, taille: (g.paysage ? 132 : 112) * k, min: 52, saut: true, majuscules: true, capitales: true, interligne: 1, lignesMax: 2, champs: ['titre', 'accent'] });
            let y = tt.bas + 28 * k;
            if (d.date) {
                const taille = 28 * mx(k, 0.9), bh = taille * 2.1;
                p.regler(taille, 800, 0.04);
                const tw = p.largeur(String(d.date).toLocaleUpperCase('fr'), taille, 0.04), bw = Math.min(w, tw + 56), yy = y;
                p.groupe('date-limite', 'Date limite', { x, y: yy, w: bw, h: bh }, () => {
                    p.chemin(x, yy, bw, bh, bh / 2); p.c.fillStyle = C.cyan; p.c.fill();
                    p.texte(d.date, { x: x + 28, y: yy + (bh - taille * 1.2) / 2, w: bw - 56, taille, poids: 800, espacement: 0.04, couleur: C.marine3, interligne: 1.2, lignesMax: 1, majuscules: true, capitales: true });
                }, { champs: ['date'] });
                y += bh + 26 * k;
            }
            [1, 2, 3, 4].filter((i) => d[`f${i}`]).forEach((i) => {
                const s = 34 * mx(k, 0.85), h = s * 1.7, yy = y;
                p.groupe(`filiere${i}`, `Filière ${i}`, { x, y: yy, w, h }, () => {
                    p.rect(x, yy + h / 2 - 6, 12, 12, C.cyan);
                    p.texte(d[`f${i}`], { x: x + 32, y: yy + (h - s * 1.2) / 2, w: w - 32, taille: s, min: 18, poids: 700, couleur: C.blanc, interligne: 1.2, lignesMax: 1 });
                }, { champs: [`f${i}`] });
                y += h;
            });
            p.texte(d.cta, { id: 'cta', nom: 'Où s’inscrire', champs: ['cta'], x, y: Math.min(y + 24 * k, g.pied.filetY - 70), w, taille: 30 * mx(k, 0.85), min: 16, poids: 800, espacement: 0.04, couleur: C.cyan, interligne: 1.2, lignesMax: 1 });
            pied(p, g, d.pied);
        },
    };

    /* --- 10. Chiffres clés --- */
    MODELES.chiffres = {
        id: 'chiffres', code: 'CHI', nom: 'Chiffres clés', icone: 'bi-bar-chart', genre: 'Affiches',
        description: 'L’UAZ en trois ou quatre chiffres : étudiants, formations, années d’existence… chaque chiffre en grand, avec son libellé.',
        formats: ['4:5', '1:1', '16:9', '9:16'],
        champs: [
            { id: 'etiquette', libelle: 'Étiquette', type: 'texte', max: 28, defaut: 'L’UAZ en chiffres' },
            { id: 'titre', libelle: 'Titre (blanc)', type: 'texte', max: 34, defaut: 'Une université qui' },
            { id: 'accent', libelle: 'Fin du titre (cyan)', type: 'texte', max: 24, defaut: 'grandit' },
            { id: 'n1', libelle: 'Chiffre 1', type: 'texte', max: 10, defaut: '1 200' }, { id: 'l1', libelle: 'Libellé 1', type: 'texte', max: 34, defaut: 'étudiants' },
            { id: 'n2', libelle: 'Chiffre 2', type: 'texte', max: 10, defaut: '15' }, { id: 'l2', libelle: 'Libellé 2', type: 'texte', max: 34, defaut: 'formations' },
            { id: 'n3', libelle: 'Chiffre 3', type: 'texte', max: 10, defaut: '30' }, { id: 'l3', libelle: 'Libellé 3', type: 'texte', max: 34, defaut: 'ans d’histoire' },
            { id: 'n4', libelle: 'Chiffre 4 (facultatif)', type: 'texte', max: 10, defaut: '' }, { id: 'l4', libelle: 'Libellé 4', type: 'texte', max: 34, defaut: '' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'zurcher.edu.mg' },
        ],
        images: [],
        variantes: [],
        dessiner(p, d, g) {
            const k = kk(g), t = g.texte;
            fond(p);
            if (g.paysage) { p.cercle(1560, 540, 520, { remplissage: 'rgba(0,196,212,0.06)' }); p.cercle(1560, 540, 360, { trait: 'rgba(0,196,212,0.18)', epaisseur: 2 }); } else { p.cercle(g.W, g.H * 0.45, g.W * 0.55, { remplissage: 'rgba(0,196,212,0.06)' }); }
            barreHaut(p, g);
            entete(p, g);
            const x = t.x, w = t.w + (g.paysage ? 740 : 0);
            const yc = g.paysage ? 300 : g.filetY + 70;
            etiquette(p, x, yc, d.etiquette, w);
            const tt = titre(p, g, d.titre, d.accent, { x, y: yc + 40, w: g.paysage ? 1000 : w, taille: 104 * k, min: 44, interligne: 0.98, lignesMax: 2, champs: ['titre', 'accent'] });
            const items = [1, 2, 3, 4].filter((i) => d[`n${i}`]);
            const top = tt.bas + 40 * k, bottom = g.pied.filetY - 36;
            if (g.paysage) {
                const cw = 1728 / items.length;
                items.forEach((i, idx) => {
                    const cx = x + idx * cw;
                    p.groupe(`chiffre${i}`, `Chiffre ${i}`, { x: cx, y: top + 20, w: cw - 30, h: 220 }, () => {
                        p.ligne(cx, top + 20, cx + 64, top + 20, C.cyan, 4);
                        p.texte(d[`n${i}`], { x: cx, y: top + 40, w: cw - 30, taille: 150, min: 60, poids: 900, espacement: -0.04, couleur: C.blanc, interligne: 1, lignesMax: 1 });
                        p.texte(d[`l${i}`], { x: cx, y: top + 200, w: cw - 30, taille: 30, min: 16, poids: 500, couleur: C.doux, interligne: 1.25, lignesMax: 2 });
                    }, { champs: [`n${i}`, `l${i}`] });
                });
            } else {
                const rh = Math.min((bottom - top) / items.length, 210);
                items.forEach((i, idx) => {
                    const y = top + idx * rh;
                    p.groupe(`chiffre${i}`, `Chiffre ${i}`, { x, y, w, h: rh }, () => {
                        p.ligne(x, y, x + w, y, C.filet, 1.5);
                        p.texte(d[`n${i}`], { x, y: y + 6, w: w * 0.5, taille: 112 * mx(k, 0.85), min: 50, poids: 900, espacement: -0.04, couleur: C.cyan, interligne: 1, lignesMax: 1 });
                        p.texte(d[`l${i}`], { x: x + w * 0.5, y: y + 6 + 56 * mx(k, 0.85) - 24 * mx(k, 0.85), w: w * 0.5, taille: 36 * mx(k, 0.85), min: 18, poids: 600, couleur: C.blanc, interligne: 1.2, lignesMax: 2 });
                    }, { champs: [`n${i}`, `l${i}`] });
                });
            }
            pied(p, g, d.pied);
        },
    };

    /* --- 11. Thème de l'année : le logo en grand --- */
    MODELES['theme-annee'] = {
        id: 'theme-annee', code: 'THE', nom: 'Thème de l’année', icone: 'bi-stars', genre: 'Affiches',
        description: 'Le thème de l’année en grand : logo « Toutes choses nouvelles », verset de référence et photo en fond très douce.',
        formats: ['1:1', '4:5', '9:16', '16:9'],
        champs: [
            { id: 'surtitre', libelle: 'Surtitre', type: 'texte', max: 36, defaut: 'Thème de l’année 2026' },
            { id: 'verset', libelle: 'Verset', type: 'longtexte', max: 160, defaut: 'Voici, je fais toutes choses nouvelles.' },
            { id: 'reference', libelle: 'Référence', type: 'texte', max: 40, defaut: 'Apocalypse 21.5' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'Université Adventiste Zurcher' },
        ],
        images: [{ id: 'photo1', libelle: 'Photo de fond (facultative)' }],
        variantes: [],
        dessiner(p, d, g) {
            const t = g.texte;
            fond(p);
            if (p.env.photos.photo1 && p.env.photos.photo1.img) { p.photo('photo1', { x: 0, y: 0, w: g.W, h: g.H }, { id: 'photo:photo1', nom: 'Photo de fond' }); p.degrade(0, 0, g.W, g.H, [0, 0, 1, 1], [[0, 'rgba(14,28,77,0.90)'], [1, 'rgba(14,28,77,0.82)']]); }
            barreHaut(p, g);
            entete(p, g);
            const lw = g.paysage ? 640 : g.W * 0.7;
            const th = p.env.logos.theme, lh = th ? lw * (th.naturalHeight / th.naturalWidth) : lw * 0.47;
            const x0 = g.paysage ? 150 : (g.W - lw) / 2;
            const y0 = g.paysage ? 330 : g.filetY + 130;
            p.texte(d.surtitre, { id: 'surtitre', nom: 'Surtitre', champs: ['surtitre'], x: x0, y: y0 - 70, w: lw, taille: 22, poids: 800, espacement: 0.3, couleur: C.cyan, interligne: 1.4, alignement: g.paysage ? 'gauche' : 'centre', majuscules: true, capitales: true, lignesMax: 1 });
            p.groupe('logo-theme', 'Logo « Toutes choses nouvelles »', { x: x0, y: y0, w: lw, h: lh }, () => p.image('theme', x0, y0, lw));
            const xt = g.paysage ? 1000 : t.x, wt = g.paysage ? 800 : t.w, yt = g.paysage ? 380 : y0 + lh + 60;
            const q = p.texte(d.verset, { id: 'verset', nom: 'Verset', champs: ['verset'], x: xt, y: yt, w: wt, taille: (g.paysage ? 54 : 52 * g.k), min: 26, poids: 600, espacement: -0.01, couleur: C.blanc, interligne: 1.2, alignement: g.paysage ? 'gauche' : 'centre', lignesMax: 5 });
            p.texte(d.reference, { id: 'reference', nom: 'Référence', champs: ['reference'], x: xt, y: q.bas + 20, w: wt, taille: 24, poids: 800, espacement: 0.16, couleur: C.cyan, interligne: 1.3, alignement: g.paysage ? 'gauche' : 'centre', majuscules: true, capitales: true, lignesMax: 1 });
            pied(p, g, d.pied);
        },
    };

    /* --- 12. Félicitations : un portrait, un nom, un mot de l'université --- */
    MODELES.felicitations = {
        id: 'felicitations', code: 'FEL', nom: 'Félicitations', icone: 'bi-award', genre: 'Affiches',
        description: 'Une réussite, une distinction, une soutenance, un anniversaire de service : le portrait, le nom en grand et le mot de l’université.',
        formats: ['16:9', '1:1', '4:5', '9:16'],
        champs: [
            { id: 'etiquette', libelle: 'Étiquette', type: 'texte', max: 28, defaut: 'Félicitations' },
            { id: 'prenom', libelle: 'Prénom (blanc)', type: 'texte', max: 22, defaut: 'Prénom' },
            { id: 'nom', libelle: 'Nom (cyan)', type: 'texte', max: 26, defaut: 'Nom' },
            { id: 'motif', libelle: 'Motif', type: 'longtexte', max: 120, defaut: 'pour sa réussite à la licence en sciences infirmières.' },
            { id: 'message', libelle: 'Le mot de l’université', type: 'longtexte', max: 170, defaut: 'Toute la communauté de l’UAZ est fière de vous et vous souhaite pleine réussite.' },
            { id: 'filiere', libelle: 'Filière ou service', type: 'texte', max: 50, defaut: 'Sciences infirmières' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'Spiritus · Mens · Manus — Service' },
        ],
        images: [{ id: 'photo1', libelle: 'Portrait' }],
        variantes: [],
        dessiner(p, d, g) {
            const k = kk(g), t = g.texte;
            fond(p);
            p.photo('photo1', g.photo, { id: 'photo:photo1', nom: 'Portrait', fondu: g.paysage ? { cote: 'gauche', longueur: 300 } : { cote: 'bas', longueur: 190 } });
            if (g.paysage) { voile(p, g.photo, 230, 240); }
            if (g.paysage) { p.groupe('barre-verticale', 'Barre cyan', { x: 1040, y: 0, w: 6, h: 1080 }, () => p.rect(1040, 0, 6, 1080, C.cyan)); }
            barreHaut(p, g);
            entete(p, g);

            const yc = g.paysage ? 300 : t.y0 + 50;
            etiquette(p, t.x, yc, d.etiquette, t.w);
            const pr = p.texte(d.prenom, { id: 'prenom', nom: 'Prénom', champs: ['prenom'], x: t.x, y: yc + 36, w: t.w, taille: 118 * k, min: 50, poids: 900, espacement: -0.04, couleur: C.blanc, interligne: 1, majuscules: true, capitales: true, lignesMax: 1 });
            const no = p.texte(d.nom, { id: 'nom', nom: 'Nom', champs: ['nom'], x: t.x, y: pr.bas, w: t.w, taille: 118 * k, min: 50, poids: 900, espacement: -0.04, couleur: C.cyan, interligne: 1, majuscules: true, capitales: true, lignesMax: 1 });
            const mo = p.texte(d.motif, { id: 'motif', nom: 'Motif', champs: ['motif'], x: t.x, y: no.bas + 26 * k, w: t.w, taille: 34 * Math.max(k, 0.85), poids: 500, couleur: C.blanc, interligne: 1.3, lignesMax: 3 });
            const me = p.texte(d.message, { id: 'message', nom: 'Mot de l’université', champs: ['message'], x: t.x, y: mo.bas + 12, w: t.w, taille: 24 * Math.max(k, 0.9), poids: 500, couleur: C.doux, interligne: 1.45, lignesMax: 4 });
            if (d.filiere) { p.texte(d.filiere, { id: 'filiere', nom: 'Filière', champs: ['filiere'], x: t.x, y: Math.min(me.bas + 20, g.pied.filetY - 50), w: t.w, taille: 16, poids: 800, espacement: 0.25, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1 }); }
            pied(p, g, d.pied);
        },
    };

    /** Les valeurs de départ d'un modèle. */
    function valeursInitiales(modele) {
        const v = {};
        modele.champs.forEach((c) => { v[c.id] = c.defaut; });

        return v;
    }

    window.StudioModeles = {
        MODELES,
        ORDRE: ['accueil', 'direct', 'affiche-photo', 'salutation', 'annonce', 'verset', 'temoignage', 'programme', 'inscriptions', 'chiffres', 'theme-annee', 'felicitations'],
        valeursInitiales,
    };
})();
