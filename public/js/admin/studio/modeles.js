/* =============================================================================
   Studio UAZ — les modèles.

   Un modèle est une définition de données (champs, emplacements photo, variantes rapides) et une fonction de dessin qui reçoit le stylo du moteur
   (moteur.js), les valeurs saisies et la géométrie du format. Ajouter un modèle = ajouter une entrée à MODELES, sans toucher à l'éditeur.

   Chaque élément dessiné porte un identifiant, un nom et les champs qu'il affiche : sur le visuel, on peut le sélectionner, le modifier, le déplacer,
   le grossir ou le supprimer (masquer), comme un bloc de newsletter (voir editeur.js).

   Règles de composition appliquées partout :
   - UNE marge (96 px sur un écran, 72 px sur une publication) : tous les textes s'alignent sur le même bord gauche, les éléments de droite sur le même bord droit ;
   - UNE échelle typographique (titre, sous-titre, texte, petit texte, étiquette) avec un grand écart entre le titre et le reste : une seule chose domine ;
   - des espacements pris sur une grille de 8 px (le pas entre deux éléments est toujours un multiple de 8 : proximité = parenté) ;
   - le bloc de texte est centré (un peu au-dessus du milieu, centre optique) dans l'espace libre entre l'en-tête et le pied de page, jamais collé en haut avec un grand vide dessous ;
   - en Story, rien d'important dans les 250 px du haut et du bas (zones couvertes par l'interface) ;
   - des retours à la ligne équilibrés (pas de mot seul sur la dernière ligne) et des titres alignés optiquement ;
   - du texte lisible : jamais sous 16 px, un voile marine sous le texte posé sur une photo.
   ========================================================================== */
(() => {
    'use strict';

    const { COULEURS: C } = window.StudioMoteur;
    const mx = (a, b) => Math.max(a, b);

    /** Échelle typographique : display (grand titre), titre, h2 (sous-titre), corps, petit, label. */
    const echelle = (g) => (g.paysage
        ? { display: 140, titre: 118, h2: 36, corps: 28, petit: 22, label: 20 }
        : { display: 112, titre: 100, h2: 36, corps: 28, petit: 24, label: 20 });
    /** Espacement sur la grille de 8 : sp(g, 3) = 24 px sur un écran, un peu moins sur une publication. */
    const sp = (g, n) => Math.round(n * 8 * (g.paysage ? 1 : 0.875));

    /** Où va le contenu quand il n'y a pas de photo en bande : entre l'en-tête (ou sa ligne) et le pied de page. */
    const zonePleine = (g) => ({ x: g.zone.x, w: g.zone.w, y0: g.paysage ? g.zone.y0 : g.filetY + 28, y1: g.zone.y1 });

    /** Portrait et carré : la photo en bande plus courte (fraction de sa hauteur d'origine), la zone de texte plus grande. */
    function photoCourte(g, fraction) {
        if (g.paysage) { return g; }
        const ph = Math.round(g.photo.h * fraction);

        return Object.assign({}, g, { photo: Object.assign({}, g.photo, { h: ph }), zone: Object.assign({}, g.zone, { y0: g.photo.y + ph + 8 }) });
    }

    /**
     * Centre un bloc dans une zone, un peu au-dessus du milieu (centre optique). fn(pen, y) dessine à partir de y et renvoie le bas du bloc ;
     * elle est d'abord jouée sur un stylo d'essai pour mesurer le bloc.
     */
    function centrer(p, zone, fn, ratio = 0.42) {
        const dispo = zone.y1 - zone.y0;
        let zoom = 1;
        // Le bloc est trop haut pour sa zone (titre long, petit format) : tout son texte rétrécit d'un cran jusqu'à ce qu'il tienne (au plus 35 %).
        for (; zoom > 0.66; zoom -= 0.05) {
            const essai = p.essai();
            essai.zoom = zoom;
            if (Math.max(0, fn(essai, 0)) <= dispo) { break; }
        }
        const mesure = p.essai();
        mesure.zoom = zoom;
        const hauteur = Math.max(0, fn(mesure, 0));
        const y = zone.y0 + Math.max(0, (dispo - hauteur) * ratio);
        p.zoom = zoom;
        const bas = fn(p, y);
        p.zoom = 1;

        return bas;
    }

    /** Accroche un bloc au bas d'une zone (affiches : le texte d'information repose sur la marge du bas). */
    function ancrerBas(p, yBas, fn) {
        const hauteur = Math.max(0, fn(p.essai(), 0));

        return fn(p, yBas - hauteur);
    }

    /* --- Éléments communs --- */
    function fond(p) {
        p.degrade(0, 0, p.W, p.H, [0, 0, 1, 1], [[0, C.marine1], [0.45, C.marine2], [1, C.marine3]]);
    }

    function barreHaut(p, g) {
        p.rect(g.marge, 0, g.paysage ? 128 : 100, 6, C.cyan);
    }

    function entete(p, g) {
        p.groupe('entete', 'En-tête (sceau et nom)', { x: g.sceau.x, y: g.sceau.y, w: 560, h: g.sceau.d }, () => {
            p.image('sceau', g.sceau.x, g.sceau.y, g.sceau.d, g.sceau.d);
            p.texte('UNIVERSITÉ ADVENTISTE', { x: g.tx, y: g.t1.c - g.t1.taille / 2, w: 640, taille: g.t1.taille, poids: 500, espacement: 0.055, couleur: C.blanc, interligne: 1, capitales: true, lignesMax: 1, equilibre: false });
            p.texte('Zurcher', { x: g.tx, y: g.t2.c - g.t2.taille / 2, w: 640, taille: g.t2.taille, poids: 700, espacement: -0.01, couleur: C.cyan, interligne: 1, lignesMax: 1, equilibre: false });
            if (g.paysage) { p.ligne(g.filetX[0], g.filetY, g.filetX[1], g.filetY, C.filet, 1.5); }
        });
    }

    function pied(p, g, gauche) {
        const xr = g.W - g.marge;
        p.groupe('filet', 'Filet du pied de page', { x: g.filetX[0], y: g.pied.filetY - 6, w: g.filetX[1] - g.filetX[0], h: 12 }, () => p.ligne(g.filetX[0], g.pied.filetY, g.filetX[1], g.pied.filetY, C.filet, 1.5));
        p.texte(gauche, { id: 'pied', nom: 'Mot du pied de page', champs: ['pied'], x: g.marge, y: g.pied.y - 17, w: g.W * 0.62, taille: 22, poids: 500, couleur: C.pale, interligne: 1.35, lignesMax: 1, equilibre: false });
        p.groupe('marque', 'Signature « UAZ »', { x: xr - 126, y: g.pied.y - 14, w: 126, h: 28 }, () => {
            p.ligne(xr - 126, g.pied.y, xr - 78, g.pied.y, C.cyan, 2);
            p.texte('UAZ', { x: xr - 90, y: g.pied.y - 12, w: 90, taille: 20, poids: 800, espacement: 0.3, couleur: C.cyan, interligne: 1.2, alignement: 'droite', capitales: true, equilibre: false });
        });
    }

    /** L'étiquette (filet + mot en capitales). y = haut du bloc de 32 px ; renvoie son bas. */
    function etiquette(p, x, y, texte, w, id = 'etiquette', ecart = 0) {
        if (!texte) { return y; }
        p.groupe(id, 'Étiquette', { x, y, w: Math.min(w, 80 + String(texte).length * 17), h: 32 }, () => {
            p.ligne(x, y + 16, x + 56, y + 16, C.cyan, 3);
            p.texte(texte, { x: x + 74, y: y + 1, w: w - 74, taille: 20, poids: 800, espacement: 0.3, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1, nom: 'Étiquette', equilibre: false });
        }, { champs: ['etiquette'] });

        return y + 32 + ecart;
    }

    /** Les lettres U A Z, très pâles, empilées dans le panneau de la photo (écran en largeur seulement). */
    function filigraneUAZ(p, g) {
        if (!g.paysage) { return; }
        p.groupe('filigrane', 'Lettres « UAZ »', { x: g.photo.x + 50, y: 32, w: 300, h: 990 }, () => {
            [['U', 295], ['A', 655], ['Z', 1018]].forEach(([lettre, base]) => {
                p.texte(lettre, { x: g.photo.x + 50, y: base - 263, w: 300, taille: 306, poids: 900, couleur: 'rgba(255,255,255,0.055)', interligne: 1, alignement: 'centre', capitales: true, espacement: 0, equilibre: false });
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

    /** Titre en deux couleurs : la partie blanche, puis la partie cyan, à la suite. Aligné optiquement sur la marge. */
    function titre(p, blanc, accent, o) {
        return p.texteCouleurs([{ t: blanc, c: C.blanc }, { t: o.saut ? `\n${accent}` : ` ${accent}`, c: C.cyan }], Object.assign({
            poids: 900, espacement: -0.04, interligne: 1, capitales: false, nom: 'Titre', id: 'titre', optique: true,
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
            { id: 'precision', libelle: 'Orateur ou précision (facultatif)', type: 'texte', max: 80, defaut: '' },
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
            const n = Math.min(3, Math.max(1, Number(d.photos) || 1)), z = g.zone, S = echelle(g);
            fond(p);
            bandePhotos(p, g, ['photo1', 'photo2', 'photo3'].slice(0, n));
            if (g.paysage) { voile(p, g.photo, 230, 240); }
            if (d.filigrane) { filigraneUAZ(p, g); }
            barreHaut(p, g);
            entete(p, g);
            centrer(p, z, (q, y0) => {
                let y = etiquette(q, z.x, y0, d.etiquette, z.w, 'etiquette', sp(g, 4));
                const tt = titre(q, d.titre1, d.titre2, { x: z.x, y, w: z.w, taille: S.display, min: 56, saut: true, majuscules: true, capitales: true, lignesMax: 3, champs: ['titre1', 'titre2'] });
                y = tt.bas + sp(g, 4);
                const st = q.texte(d.sousTitre, { id: 'sousTitre', nom: 'Sous-titre', champs: ['sousTitre'], x: z.x, y, w: z.w, taille: S.h2, min: 24, poids: 500, couleur: C.blanc, interligne: 1.25, lignesMax: 2 });
                y = st.bas;
                if (d.precision) { y = q.texte(d.precision, { id: 'precision', nom: 'Orateur', champs: ['precision'], x: z.x, y: y + sp(g, 1), w: z.w, taille: S.petit, min: 18, poids: 500, couleur: C.doux, interligne: 1.35, lignesMax: 2 }).bas; }

                return y;
            });
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
            { id: 'contexte', libelle: 'Contexte (facultatif)', type: 'texte', max: 40, defaut: '' },
            { id: 'titre', libelle: 'Titre (blanc)', type: 'texte', max: 40, defaut: 'Le direct commence' },
            { id: 'accent', libelle: 'Fin du titre (cyan)', type: 'texte', max: 24, defaut: 'bientôt' },
            { id: 'sousTitre', libelle: 'Sous-titre', type: 'texte', max: 90, defaut: 'Bienvenue à l’Université Adventiste Zurcher.' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'Merci de votre présence.' },
            { id: 'badge', libelle: 'Pastille', type: 'texte', max: 30, defaut: 'Diffusion en direct' },
            { id: 'theme1', libelle: 'Thème (ligne 1, facultatif)', type: 'texte', max: 34, defaut: '' },
            { id: 'theme2', libelle: 'Thème (ligne 2, facultatif)', type: 'texte', max: 34, defaut: '' },
            { id: 'decor', libelle: 'Décor de visée (cercles)', type: 'interrupteur', defaut: true },
        ],
        images: [{ id: 'photo1', libelle: 'Photo' }],
        variantes: [
            { nom: 'Commence bientôt', champs: { titre: 'Le direct commence', accent: 'bientôt', sousTitre: 'Bienvenue à l’Université Adventiste Zurcher.', pied: 'Merci de votre présence.' } },
            { nom: 'En direct', champs: { titre: 'Nous sommes', accent: 'en direct', sousTitre: 'Rejoignez-nous maintenant.', pied: 'Merci de votre présence.' } },
            { nom: 'Terminé', champs: { titre: 'Le direct est', accent: 'terminé', sousTitre: 'Nous vous donnons rendez-vous une prochaine fois', pied: 'Merci de votre présence.' } },
        ],
        dessiner(p, d, g0) {
            const g = photoCourte(g0, g0.story ? 0.85 : g0.H < 1200 ? 0.78 : 0.9), z = g.zone, S = echelle(g);
            fond(p);
            const ph = g.paysage ? { x: 1120, y: 0, w: 800, h: 1080 } : g.photo;
            p.photo('photo1', ph, Object.assign({ id: 'photo:photo1', nom: 'Photo' }, g.paysage ? {} : { fondu: { cote: 'bas', longueur: 170 } }));
            voile(p, ph, 230, g.paysage ? 330 : 0);
            const cx = g.paysage ? 1520 : g.W / 2, cy = g.paysage ? 250 : ph.y + ph.h * 0.42, R = g.paysage ? 340 : 300;
            if (d.decor) {
                p.groupe('decor', 'Décor de visée', { x: cx - R, y: cy - R, w: 2 * R, h: 2 * R }, () => {
                    p.cercle(cx, cy, R, { trait: 'rgba(0,196,212,0.30)', epaisseur: 1.5 });
                    p.cercle(cx, cy, R * 0.86, { trait: 'rgba(0,196,212,0.14)', epaisseur: 1.5 });
                });
            }
            if (g.paysage) { p.groupe('barre-verticale', 'Barre cyan', { x: 1104, y: 0, w: 8, h: 1080 }, () => p.rect(1104, 0, 8, 1080, C.cyan)); }
            barreHaut(p, g);
            entete(p, g);

            const bx = g.paysage ? 1386 : g.marge + 8, by = g.paysage ? 105 : ph.y + 50;
            if (d.badge) {
                p.groupe('badge', 'Pastille « en direct »', { x: bx - 10, y: by - 16, w: 440, h: 32 }, () => {
                    p.cercle(bx, by, 5, { remplissage: C.cyan });
                    p.texte(d.badge, { x: bx + 22, y: by - 15, w: 420, taille: 18, poids: 700, espacement: 0.3, couleur: C.cyan, interligne: 1.7, majuscules: true, capitales: true, lignesMax: 1, equilibre: false });
                }, { champs: ['badge'] });
            }
            if (d.theme1 && g.paysage) {
                p.groupe('theme', 'Thème de l’année', { x: 1620, y: 750, w: 290, h: 44 }, () => {
                    p.texte(d.theme1, { x: 1620, y: 750, w: 290, taille: 14, poids: 800, espacement: 0.2, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1, equilibre: false });
                    if (d.theme2) { p.texte(d.theme2, { x: 1620, y: 774, w: 290, taille: 14, poids: 500, espacement: 0.16, couleur: 'rgba(255,255,255,0.6)', interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1, equilibre: false }); }
                }, { champs: ['theme1', 'theme2'] });
            }

            const w = g.paysage ? 960 : z.w;
            centrer(p, z, (q, y0) => {
                let y = y0;
                if (d.surtitre) { q.texte(d.surtitre, { id: 'surtitre', nom: 'Surtitre', champs: ['surtitre'], x: z.x, y, w, taille: 18, poids: 800, espacement: 0.3, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1, equilibre: false }); y += 28; }
                if (d.contexte) { q.texte(d.contexte, { id: 'contexte', nom: 'Contexte', champs: ['contexte'], x: z.x, y, w, taille: 18, poids: 500, espacement: 0.2, couleur: C.pale, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1, equilibre: false }); y += 28; }
                if (d.surtitre || d.contexte) { y += sp(g, 2); }
                q.groupe('trait', 'Trait cyan', { x: z.x, y, w: 72, h: 6 }, () => q.rect(z.x, y, 72, 6, C.cyan));
                y += 6 + sp(g, 4);
                const tt = titre(q, d.titre, d.accent, { x: z.x, y, w, taille: S.display * 1.07, min: 56, interligne: 0.92, lignesMax: 3, champs: ['titre', 'accent'] });

                return q.texte(d.sousTitre, { id: 'sousTitre', nom: 'Sous-titre', champs: ['sousTitre'], x: z.x, y: tt.bas + sp(g, 3), w, taille: S.h2 * 0.78, min: 20, poids: 500, couleur: C.doux, interligne: 1.35, lignesMax: 2 }).bas;
            });
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
            { id: 'cta', libelle: 'Appel en bas (facultatif)', type: 'texte', max: 50, defaut: '' },
            { id: 'sceau', libelle: 'Sceau de l’université', type: 'interrupteur', defaut: true },
        ],
        images: [{ id: 'photo1', libelle: 'Photo de fond' }],
        variantes: [],
        dessiner(p, d, g) {
            const W = g.W, H = g.H, m = g.paysage ? 110 : Math.round(W * 0.09);
            fond(p);
            p.photo('photo1', { x: 0, y: 0, w: W, h: H }, { id: 'photo:photo1', nom: 'Photo de fond' });
            if (g.paysage) { p.degrade(0, 0, W, H, [0, 0, 0.7, 0], [[0, 'rgba(10,78,134,0.96)'], [0.6, 'rgba(10,78,134,0.62)'], [1, 'rgba(10,78,134,0)']]); } else { p.degrade(0, 0, W, H, [0, 0, 0, 0.62], [[0, 'rgba(10,78,134,0.97)'], [0.55, 'rgba(14,64,126,0.70)'], [1, 'rgba(19,60,120,0)']]); }
            p.degrade(0, H * 0.74, W, H * 0.26, [0, 0, 0, 1], [[0, 'rgba(14,28,77,0)'], [1, 'rgba(14,28,77,0.88)']]);
            const s = g.paysage ? 76 : Math.round(W * 0.1), tw = g.paysage ? W * 0.56 : W - 2 * m;
            const haut = Math.max(g.sur.haut + (g.paysage ? 54 : 24), g.paysage ? 150 : H * 0.075);
            const l1 = p.texte(d.ligne1, { id: 'ligne1', nom: 'Titre, ligne 1', champs: ['ligne1'], x: m, y: haut, w: tw, taille: s, min: 36, poids: 300, espacement: -0.01, couleur: C.blanc, interligne: 1.12, lignesMax: 1, optique: true, equilibre: false });
            const l2 = p.texte(d.ligne2, { id: 'ligne2', nom: 'Titre, ligne 2', champs: ['ligne2'], x: m, y: l1.bas, w: tw, taille: s, min: 36, poids: 800, espacement: -0.02, couleur: C.blanc, interligne: 1.12, lignesMax: 2, optique: true });
            p.texte(d.ligne3, { id: 'ligne3', nom: 'Titre, ligne 3', champs: ['ligne3'], x: m, y: l2.bas, w: tw, taille: s, min: 36, poids: 300, espacement: 0.02, couleur: C.blanc, interligne: 1.12, lignesMax: 1, majuscules: true, capitales: true, optique: true, equilibre: false });
            if (d.sceau) {
                const sd = g.paysage ? 90 : 84, sx = W - m - sd, sy = Math.max(g.sur.haut, g.paysage ? 60 : H * 0.045);
                p.groupe('sceau', 'Sceau de l’université', { x: sx, y: sy, w: sd, h: sd }, () => p.image('sceau', sx, sy, sd, sd), { champs: ['sceau'] });
            }
            // Le bloc d'information repose sur la marge du bas (zone de sécurité en Story).
            ancrerBas(p, H - g.sur.bas - (g.paysage ? 14 : 0), (q, y0) => {
                const ti = g.paysage ? 30 : Math.round(W * 0.03);
                const inf = q.texte(d.infos, { id: 'infos', nom: 'Date et lieu', champs: ['infos'], x: m, y: y0, w: W - 2 * m, taille: ti, min: 18, poids: 600, couleur: C.blanc, interligne: 1.3, lignesMax: 2 });

                if (!d.cta) { return inf.bas; }

                return q.texte(d.cta, { id: 'cta', nom: 'Appel', champs: ['cta'], x: m, y: inf.bas + sp(g, 1), w: W - 2 * m, taille: mx(20, ti * 0.8), min: 16, poids: 800, espacement: 0.12, couleur: C.cyan, interligne: 1.3, lignesMax: 1, majuscules: true, capitales: true, equilibre: false }).bas;
            });
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
            { id: 'compte', libelle: 'Compte ou signature (facultatif)', type: 'texte', max: 30, defaut: '' },
            { id: 'theme1', libelle: 'Thème, langue 1', type: 'texte', max: 40, defaut: 'Toutes choses nouvelles' },
            { id: 'theme2', libelle: 'Thème, langue 2 (facultatif)', type: 'texte', max: 40, defaut: '' },
            { id: 'sceau', libelle: 'Sceau de l’université', type: 'interrupteur', defaut: true },
        ],
        images: [{ id: 'photo1', libelle: 'Photo de fond' }],
        variantes: [
            { nom: 'Bon Sabbat', champs: { ligne1: 'Bon Sabbat', ligne2: 'Mirary Sabata mahafinaritra' } },
            { nom: 'Bonne semaine', champs: { ligne1: 'Bonne semaine', ligne2: 'Faniny mahafinaritra' } },
            { nom: 'Bonne année', champs: { ligne1: 'Bonne année', ligne2: 'Arahaba ny taona vaovao' } },
        ],
        dessiner(p, d, g) {
            const W = g.W, H = g.H, m = g.paysage ? 96 : 72;
            fond(p);
            p.photo('photo1', { x: 0, y: 0, w: W, h: H }, { id: 'photo:photo1', nom: 'Photo de fond' });
            p.degrade(0, 0, W, H, [0, 0, 0, 1], [[0, 'rgba(14,28,77,0.30)'], [0.45, 'rgba(14,28,77,0.12)'], [1, 'rgba(14,28,77,0.9)']]);
            const s = g.paysage ? 64 : Math.round(W * 0.062), t = g.paysage ? 24 : Math.round(W * 0.026), wL = W - 2 * m;
            ancrerBas(p, H - g.sur.bas, (q, y0) => {
                const l1 = q.texte(d.ligne1, { id: 'ligne1', nom: 'Salutation, langue 1', champs: ['ligne1'], x: m, y: y0, w: wL, taille: s, min: 28, poids: 600, espacement: -0.015, couleur: C.blanc, interligne: 1.15, lignesMax: 1, optique: true, equilibre: false });
                const yf = l1.bas + sp(g, 2);
                q.groupe('filet-salut', 'Filet', { x: m, y: yf - 5, w: W * 0.34, h: 10 }, () => q.ligne(m, yf, m + W * 0.34, yf, 'rgba(255,255,255,0.45)', 1.5));
                const l2 = q.texte(d.ligne2, { id: 'ligne2', nom: 'Salutation, langue 2', champs: ['ligne2'], x: m, y: yf + sp(g, 2), w: wL, taille: s, min: 28, poids: 600, espacement: -0.015, couleur: C.blanc, interligne: 1.15, lignesMax: 1, optique: true, equilibre: false });
                const yb = l2.bas + sp(g, 5), sd = g.paysage ? 76 : Math.round(W * 0.09);
                let bas = l2.bas;
                const wTexte = W - 2 * m - (d.sceau ? sd + sp(g, 4) : 0);
                if (d.theme1) { bas = q.texte(d.theme1, { id: 'theme1', nom: 'Thème, langue 1', champs: ['theme1'], x: m, y: yb, w: wTexte, taille: t * 1.15, min: 16, poids: 700, couleur: C.blanc, interligne: 1.2, lignesMax: 2 }).bas; }
                if (d.theme2) { bas = q.texte(d.theme2, { id: 'theme2', nom: 'Thème, langue 2', champs: ['theme2'], x: m, y: d.theme1 ? bas + sp(g, 1) : yb, w: wTexte, taille: t, min: 16, poids: 500, couleur: C.doux, interligne: 1.2, lignesMax: 2 }).bas; }
                if (d.compte) { bas = q.texte(d.compte, { id: 'compte', nom: 'Compte', champs: ['compte'], x: m, y: d.theme1 || d.theme2 ? bas + sp(g, 1) : yb, w: wTexte, taille: t, min: 16, poids: 500, espacement: 0.02, couleur: 'rgba(255,255,255,0.85)', interligne: 1.2, lignesMax: 1, equilibre: false }).bas; }
                if (d.sceau) { q.groupe('sceau', 'Sceau de l’université', { x: W - m - sd, y: yb, w: sd, h: sd }, () => q.image('sceau', W - m - sd, yb, sd, sd), { champs: ['sceau'] }); bas = mx(bas, yb + sd); }

                return bas;
            });
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
            { id: 'sousTitre', libelle: 'Sous-titre', type: 'texte', max: 90, defaut: 'Venez découvrir nos formations.' },
            { id: 'i1t', libelle: 'Info 1 : intitulé', type: 'texte', max: 16, defaut: 'Quand' },
            { id: 'i1', libelle: 'Info 1 : texte', type: 'texte', max: 40, defaut: 'Samedi 14 mars, 9 h' },
            { id: 'i2t', libelle: 'Info 2 : intitulé', type: 'texte', max: 16, defaut: 'Où' },
            { id: 'i2', libelle: 'Info 2 : texte', type: 'texte', max: 40, defaut: 'Campus de l’UAZ' },
            { id: 'i3t', libelle: 'Info 3 : intitulé', type: 'texte', max: 16, defaut: 'Entrée' },
            { id: 'i3', libelle: 'Info 3 : texte', type: 'texte', max: 40, defaut: 'Libre' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'zurcher.edu.mg' },
            { id: 'photos', libelle: 'Nombre de photos', type: 'choix', options: OUI.slice(0, 2), defaut: '1' },
            { id: 'filigrane', libelle: 'Lettres « UAZ » en filigrane', type: 'interrupteur', defaut: false },
        ],
        images: [{ id: 'photo1', libelle: 'Photo 1' }, { id: 'photo2', libelle: 'Photo 2', visible: (d) => Number(d.photos) >= 2 }],
        variantes: [],
        dessiner(p, d, g0) {
            const n = Math.min(2, Math.max(1, Number(d.photos) || 1)), g = photoCourte(g0, g0.story ? 0.86 : 0.8), z = g.zone, S = echelle(g);
            fond(p);
            bandePhotos(p, g, ['photo1', 'photo2'].slice(0, n));
            if (g.paysage) { voile(p, g.photo, 230, 240); }
            if (d.filigrane) { filigraneUAZ(p, g); }
            barreHaut(p, g);
            entete(p, g);

            // Les trois informations reposent sur le bas de la zone ; le titre et le sous-titre se centrent dans la place qui reste.
            const colW = z.w / 3, vs = S.corps - 2, hInfo = 16 + sp(g, 1) + vs * 1.25 * 2;
            const yInfo = z.y1 - hInfo;
            [['i1t', 'i1'], ['i2t', 'i2'], ['i3t', 'i3']].forEach(([a, b], i) => {
                const x = z.x + i * colW + (i ? sp(g, 3) : 0);
                p.groupe(`info${i + 1}`, `Information ${i + 1}`, { x, y: yInfo, w: colW - sp(g, 4), h: hInfo }, () => {
                    if (i) { p.ligne(z.x + i * colW, yInfo, z.x + i * colW, yInfo + hInfo, C.filet, 1.5); }
                    p.texte(d[a], { x, y: yInfo, w: colW - sp(g, 4), taille: 16, poids: 800, espacement: 0.25, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1, equilibre: false });
                    p.texte(d[b], { x, y: yInfo + 16 + sp(g, 1), w: colW - sp(g, 4), taille: vs, min: 18, poids: 700, couleur: C.blanc, interligne: 1.25, lignesMax: 2, nom: 'Information' });
                }, { champs: [a, b] });
            });
            centrer(p, { x: z.x, w: z.w, y0: z.y0, y1: yInfo - sp(g, 4) }, (q, y0) => {
                let y = etiquette(q, z.x, y0, d.etiquette, z.w, 'etiquette', sp(g, 4));
                const tt = titre(q, d.titre1, d.titre2, { x: z.x, y, w: z.w, taille: S.titre, min: 52, saut: false, majuscules: true, capitales: true, lignesMax: 3, champs: ['titre1', 'titre2'] });
                y = tt.bas + sp(g, 3);

                return q.texte(d.sousTitre, { id: 'sousTitre', nom: 'Sous-titre', champs: ['sousTitre'], x: z.x, y, w: z.w, taille: S.h2 - 4, min: 22, poids: 500, couleur: C.doux, interligne: 1.3, lignesMax: 2 }).bas;
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
            { id: 'etiquette', libelle: 'Étiquette (facultatif)', type: 'texte', max: 28, defaut: '' },
            { id: 'texte', libelle: 'Le texte', type: 'longtexte', max: 260, defaut: 'Voici, je fais toutes choses nouvelles.' },
            { id: 'reference', libelle: 'Référence ou auteur', type: 'texte', max: 60, defaut: 'Apocalypse 21.5' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'Université Adventiste Zurcher' },
            { id: 'logoTheme', libelle: 'Logo « Toutes choses nouvelles »', type: 'interrupteur', defaut: true },
        ],
        images: [{ id: 'photo1', libelle: 'Photo de fond' }],
        variantes: [],
        dessiner(p, d, g) {
            const S = echelle(g), zp = zonePleine(g);
            fond(p);
            if (p.env.photos.photo1 && p.env.photos.photo1.img) {
                p.photo('photo1', { x: 0, y: 0, w: g.W, h: g.H }, { id: 'photo:photo1', nom: 'Photo de fond' });
                if (g.paysage) { p.degrade(0, 0, g.W, g.H, [0, 0, 1, 0], [[0, 'rgba(14,28,77,0.94)'], [0.55, 'rgba(19,35,91,0.80)'], [1, 'rgba(19,35,91,0.45)']]); } else { p.degrade(0, 0, g.W, g.H, [0, 0, 0, 1], [[0, 'rgba(14,28,77,0.78)'], [0.5, 'rgba(14,28,77,0.88)'], [1, 'rgba(14,28,77,0.96)']]); }
            } else if (p.env.apercu) {
                p.photo('photo1', { x: g.W * 0.55, y: 0, w: g.W * 0.45, h: g.H }, { id: 'photo:photo1', nom: 'Photo de fond' });
            }
            barreHaut(p, g);
            entete(p, g);

            const logo = d.logoTheme && p.env.logos.theme ? p.env.logos.theme : null;
            const lw = g.paysage ? 300 : 240, lh = logo ? lw * (logo.naturalHeight / logo.naturalWidth) : 0;
            // En portrait le logo prend sa place au bas de la zone ; le texte se centre dans ce qui reste. En largeur, il est à droite et ne gêne pas.
            const reserve = !g.paysage && logo ? lh + sp(g, 4) : 0;
            const zone = { x: zp.x, w: zp.w, y0: zp.y0, y1: zp.y1 - reserve };
            const w = g.paysage ? 1040 : zp.w, sGuill = S.display * 0.6;
            centrer(p, zone, (q, y0) => {
                q.groupe('guillemet', 'Guillemet', { x: zp.x + 4, y: y0, w: sGuill * 1.3, h: sGuill }, () => guillemet(q, zp.x + 4, y0, sGuill, 'rgba(0,196,212,0.85)'));
                let y = y0 + sGuill + sp(g, 3);
                y = etiquette(q, zp.x, y, d.etiquette, w, 'etiquette', sp(g, 3));
                const t = q.texte(d.texte, { id: 'texte', nom: 'Le texte', champs: ['texte'], x: zp.x, y, w, taille: g.paysage ? 80 : 76, min: 34, poids: 700, espacement: -0.02, couleur: C.blanc, interligne: 1.14, lignesMax: 8, optique: true });
                y = t.bas;
                if (d.reference) { y = q.texte(d.reference, { id: 'reference', nom: 'Référence', champs: ['reference'], x: zp.x, y: y + sp(g, 3), w, taille: S.corps, poids: 800, espacement: 0.16, couleur: C.cyan, interligne: 1.3, majuscules: true, capitales: true, lignesMax: 1, equilibre: false }).bas; }

                return y;
            });
            if (logo) {
                const ly = g.paysage ? g.pied.filetY - lh - sp(g, 4) : zp.y1 - lh;
                p.groupe('logo-theme', 'Logo « Toutes choses nouvelles »', { x: g.W - g.marge - lw, y: ly, w: lw, h: lh }, () => p.image('theme', g.W - g.marge - lw, ly, lw), { champs: ['logoTheme'] });
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
            { id: 'etiquette', libelle: 'Étiquette (facultatif)', type: 'texte', max: 28, defaut: '' },
            { id: 'texte', libelle: 'La parole', type: 'longtexte', max: 220, defaut: 'À l’UAZ, j’ai trouvé une famille et une vision pour mon avenir.' },
            { id: 'nom', libelle: 'Nom', type: 'texte', max: 40, defaut: 'Prénom NOM' },
            { id: 'fonction', libelle: 'Fonction ou promotion', type: 'texte', max: 60, defaut: 'Diplômée, promotion 2025' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'zurcher.edu.mg' },
        ],
        images: [{ id: 'photo1', libelle: 'Portrait' }],
        variantes: [],
        dessiner(p, d, g) {
            const S = echelle(g), zp = zonePleine(g);
            fond(p);
            barreHaut(p, g);
            entete(p, g);
            const D = g.paysage ? 540 : Math.min(g.W * 0.4, g.story ? 420 : 380);
            const portrait = (q, cx, cy) => q.groupe('portrait', 'Portrait', { x: cx - D / 2 - 14, y: cy - D / 2 - 14, w: D + 28, h: D + 28 }, () => {
                q.cercle(cx, cy, D / 2 + 14, { trait: C.cyan, epaisseur: 4 });
                q.photo('photo1', { x: cx - D / 2, y: cy - D / 2, w: D, h: D }, { cercle: true, id: 'photo:photo1', nom: 'Portrait' });
            });
            const texte = (q, y0, w) => {
                let y = etiquette(q, zp.x, y0, d.etiquette, w, 'etiquette', sp(g, 3));
                const t = q.texte(d.texte, { id: 'texte', nom: 'La parole', champs: ['texte'], x: zp.x, y, w, taille: g.paysage ? 62 : 58, min: 28, poids: 700, espacement: -0.02, couleur: C.blanc, interligne: 1.16, lignesMax: g.paysage ? 6 : 5, optique: true });
                const n = q.texte(d.nom, { id: 'nom', nom: 'Nom', champs: ['nom'], x: zp.x, y: t.bas + sp(g, 3), w, taille: S.corps, poids: 800, espacement: 0.12, couleur: C.cyan, interligne: 1.3, majuscules: true, capitales: true, lignesMax: 1, equilibre: false });
                y = n.bas;

                return q.texte(d.fonction, { id: 'fonction', nom: 'Fonction', champs: ['fonction'], x: zp.x, y: y + sp(g, 1), w, taille: S.petit, poids: 500, couleur: C.doux, interligne: 1.35, lignesMax: 2 }).bas;
            };
            if (g.paysage) {
                portrait(p, 1500, 540);
                centrer(p, zp, (q, y0) => texte(q, y0, 900));
            } else {
                // Portrait rond et texte forment un seul bloc, centré dans la zone.
                centrer(p, zp, (q, y0) => {
                    portrait(q, g.W / 2, y0 + D / 2 + 14);

                    return texte(q, y0 + D + 28 + sp(g, 5), zp.w);
                });
            }
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
            ...champsLigne(1, '8 h', 'Culte du matin', ''),
            ...champsLigne(2, '10 h', 'École du Sabbat', ''),
            ...champsLigne(3, '12 h', 'Repas partagé', ''),
            ...champsLigne(4, '15 h', 'Chants et témoignages', ''),
            ...champsLigne(5, '', '', ''),
            ...champsLigne(6, '', '', ''),
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: 'zurcher.edu.mg' },
        ],
        images: [{ id: 'photo1', libelle: 'Photo (écran en largeur)' }],
        variantes: [],
        dessiner(p, d, g) {
            const S = echelle(g), zp = zonePleine(g), w = g.paysage ? 900 : zp.w;
            fond(p);
            if (g.paysage) { p.photo('photo1', g.photo, { fondu: { cote: 'gauche', longueur: 380 }, id: 'photo:photo1', nom: 'Photo' }); voile(p, g.photo, 230, 240); }
            barreHaut(p, g);
            entete(p, g);
            const rows = LIGNES.filter((i) => d[`r${i}t`] || d[`r${i}h`]);
            centrer(p, zp, (q, y0) => {
                const tt = titre(q, d.titre, d.accent, { x: zp.x, y: y0, w, taille: S.titre * 0.8, min: 44, interligne: 0.98, lignesMax: 2, champs: ['titre', 'accent'] });
                const top = tt.bas + sp(g, 4);
                const rh = Math.min(g.paysage ? 128 : 124, rows.length ? (zp.y1 - top - sp(g, 1)) / rows.length : 0);
                const f = rh / 124;
                rows.forEach((i, idx) => {
                    const y = top + idx * rh, cx = zp.x + Math.round(176 * Math.min(1.15, f + 0.1));
                    q.groupe(`ligne${i}`, `Ligne ${i}`, { x: zp.x, y, w, h: rh }, () => {
                        q.ligne(zp.x, y, zp.x + w, y, C.filet, 1.5);
                        q.texte(d[`r${i}h`], { x: zp.x, y: y + sp(g, 2), w: cx - zp.x - sp(g, 2), taille: mx(26, 40 * f), min: 22, poids: 800, couleur: C.cyan, interligne: 1.1, lignesMax: 1, equilibre: false });
                        const a = q.texte(d[`r${i}t`], { x: cx, y: y + sp(g, 2) - 2, w: zp.x + w - cx, taille: mx(24, 36 * f), min: 20, poids: 700, couleur: C.blanc, interligne: 1.15, lignesMax: 1, equilibre: false });
                        if (d[`r${i}l`]) { q.texte(d[`r${i}l`], { x: cx, y: a.bas + 2, w: zp.x + w - cx, taille: mx(20, 24 * f), min: 18, poids: 500, couleur: C.doux, interligne: 1.2, lignesMax: 1, equilibre: false }); }
                    }, { champs: [`r${i}h`, `r${i}t`, `r${i}l`] });
                });

                return top + rows.length * rh;
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
            { id: 'etiquette', libelle: 'Étiquette (facultatif)', type: 'texte', max: 34, defaut: '' },
            { id: 'titre', libelle: 'Titre (blanc)', type: 'texte', max: 30, defaut: 'Inscriptions' },
            { id: 'accent', libelle: 'Fin du titre (cyan)', type: 'texte', max: 24, defaut: 'ouvertes' },
            { id: 'date', libelle: 'Date limite (pastille)', type: 'texte', max: 40, defaut: 'Jusqu’au 30 septembre' },
            { id: 'f1', libelle: 'Filière 1', type: 'texte', max: 40, defaut: 'Sciences infirmières' },
            { id: 'f2', libelle: 'Filière 2', type: 'texte', max: 40, defaut: 'Théologie' },
            { id: 'f3', libelle: 'Filière 3', type: 'texte', max: 40, defaut: 'Gestion et comptabilité' },
            { id: 'f4', libelle: 'Filière 4 (facultatif)', type: 'texte', max: 40, defaut: '' },
            { id: 'cta', libelle: 'Où s’inscrire', type: 'texte', max: 50, defaut: 'zurcher.edu.mg/admissions' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: '' },
        ],
        images: [{ id: 'photo1', libelle: 'Photo' }],
        variantes: [],
        dessiner(p, d, g0) {
            const g = photoCourte(g0, g0.story ? 0.7 : g0.H > 1200 ? 0.62 : 0.58), z = g.zone, S = echelle(g);
            fond(p);
            bandePhotos(p, g, ['photo1']);
            if (g.paysage) { voile(p, g.photo, 230, 240); }
            barreHaut(p, g);
            entete(p, g);
            centrer(p, z, (q, y0) => {
                let y = etiquette(q, z.x, y0, d.etiquette, z.w, 'etiquette', sp(g, 3));
                const tt = titre(q, d.titre, d.accent, { x: z.x, y, w: z.w, taille: S.display * 0.94, min: 52, saut: true, majuscules: true, capitales: true, interligne: 1, lignesMax: 2, champs: ['titre', 'accent'] });
                y = tt.bas + sp(g, 3);
                if (d.date) {
                    const taille = S.corps, bh = Math.round(taille * 2.1);
                    q.regler(taille, 800, 0.04);
                    const tw = q.largeur(String(d.date).toLocaleUpperCase('fr'), taille, 0.04), bw = Math.min(z.w, tw + 56), yy = y;
                    q.groupe('date-limite', 'Date limite', { x: z.x, y: yy, w: bw, h: bh }, () => {
                        q.chemin(z.x, yy, bw, bh, bh / 2); q.c.fillStyle = C.cyan; q.c.fill();
                        q.texte(d.date, { x: z.x + 28, y: yy + (bh - taille * 1.2) / 2, w: bw - 56, taille, poids: 800, espacement: 0.04, couleur: C.marine3, interligne: 1.2, lignesMax: 1, majuscules: true, capitales: true, equilibre: false });
                    }, { champs: ['date'] });
                    y += bh + sp(g, 4);
                }
                [1, 2, 3, 4].filter((i) => d[`f${i}`]).forEach((i) => {
                    const s = S.corps + 4, h = Math.round(s * 1.7), yy = y;
                    q.groupe(`filiere${i}`, `Filière ${i}`, { x: z.x, y: yy, w: z.w, h }, () => {
                        q.rect(z.x, yy + h / 2 - 6, 12, 12, C.cyan);
                        q.texte(d[`f${i}`], { x: z.x + sp(g, 4), y: yy + (h - s * 1.2) / 2, w: z.w - sp(g, 4), taille: s, min: 20, poids: 700, couleur: C.blanc, interligne: 1.2, lignesMax: 1, equilibre: false });
                    }, { champs: [`f${i}`] });
                    y += h;
                });

                return q.texte(d.cta, { id: 'cta', nom: 'Où s’inscrire', champs: ['cta'], x: z.x, y: y + sp(g, 3), w: z.w, taille: S.corps + 2, min: 18, poids: 800, espacement: 0.04, couleur: C.cyan, interligne: 1.2, lignesMax: 1, equilibre: false }).bas;
            });
            pied(p, g, d.pied);
        },
    };

    /* --- 10. Chiffres clés --- */
    MODELES.chiffres = {
        id: 'chiffres', code: 'CHI', nom: 'Chiffres clés', icone: 'bi-bar-chart', genre: 'Affiches',
        description: 'L’UAZ en trois ou quatre chiffres : étudiants, formations, années d’existence… chaque chiffre en grand, avec son libellé.',
        formats: ['4:5', '1:1', '16:9', '9:16'],
        champs: [
            { id: 'etiquette', libelle: 'Étiquette (facultatif)', type: 'texte', max: 28, defaut: '' },
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
            const S = echelle(g), zp = zonePleine(g), W = g.paysage ? 1728 : zp.w;
            fond(p);
            if (g.paysage) { p.cercle(1560, 540, 520, { remplissage: 'rgba(0,196,212,0.06)' }); p.cercle(1560, 540, 360, { trait: 'rgba(0,196,212,0.18)', epaisseur: 2 }); } else { p.cercle(g.W, g.H * 0.45, g.W * 0.55, { remplissage: 'rgba(0,196,212,0.06)' }); }
            barreHaut(p, g);
            entete(p, g);
            const items = [1, 2, 3, 4].filter((i) => d[`n${i}`]);
            centrer(p, zp, (q, y0) => {
                let y = etiquette(q, zp.x, y0, d.etiquette, W, 'etiquette', sp(g, 3));
                const tt = titre(q, d.titre, d.accent, { x: zp.x, y, w: g.paysage ? 1000 : zp.w, taille: S.titre * 0.88, min: 44, interligne: 0.98, lignesMax: 2, champs: ['titre', 'accent'] });
                y = tt.bas + sp(g, 5);
                if (g.paysage) {
                    const cw = W / items.length;
                    items.forEach((i, idx) => {
                        const cx = zp.x + idx * cw;
                        q.groupe(`chiffre${i}`, `Chiffre ${i}`, { x: cx, y, w: cw - 32, h: 230 }, () => {
                            q.ligne(cx, y, cx + 64, y, C.cyan, 4);
                            const n = q.texte(d[`n${i}`], { x: cx, y: y + sp(g, 2), w: cw - 32, taille: 150, min: 60, poids: 900, espacement: -0.04, couleur: C.blanc, interligne: 1, lignesMax: 1, optique: true, equilibre: false });
                            q.texte(d[`l${i}`], { x: cx, y: n.bas + sp(g, 1), w: cw - 32, taille: 30, min: 18, poids: 500, couleur: C.doux, interligne: 1.25, lignesMax: 2 });
                        }, { champs: [`n${i}`, `l${i}`] });
                    });

                    return y + 230;
                }
                const rh = Math.min(170, (zp.y1 - y) / items.length);
                items.forEach((i, idx) => {
                    const yy = y + idx * rh;
                    q.groupe(`chiffre${i}`, `Chiffre ${i}`, { x: zp.x, y: yy, w: zp.w, h: rh }, () => {
                        q.ligne(zp.x, yy, zp.x + zp.w, yy, C.filet, 1.5);
                        const n = q.texte(d[`n${i}`], { x: zp.x, y: yy + sp(g, 2), w: zp.w * 0.5, taille: mx(56, rh * 0.62), min: 44, poids: 900, espacement: -0.04, couleur: C.cyan, interligne: 1, lignesMax: 1, optique: true, equilibre: false });
                        q.texte(d[`l${i}`], { x: zp.x + zp.w * 0.52, y: yy + sp(g, 2) + (n.h - S.h2 * 1.2) / 2, w: zp.w * 0.48, taille: S.h2, min: 20, poids: 600, couleur: C.blanc, interligne: 1.2, lignesMax: 2 });
                    }, { champs: [`n${i}`, `l${i}`] });
                });

                return y + items.length * rh;
            });
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
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: '' },
        ],
        images: [{ id: 'photo1', libelle: 'Photo de fond (facultative)' }],
        variantes: [],
        dessiner(p, d, g) {
            const S = echelle(g), zp = zonePleine(g);
            fond(p);
            if (p.env.photos.photo1 && p.env.photos.photo1.img) { p.photo('photo1', { x: 0, y: 0, w: g.W, h: g.H }, { id: 'photo:photo1', nom: 'Photo de fond' }); p.degrade(0, 0, g.W, g.H, [0, 0, 1, 1], [[0, 'rgba(14,28,77,0.90)'], [1, 'rgba(14,28,77,0.82)']]); }
            barreHaut(p, g);
            entete(p, g);
            const th = p.env.logos.theme, lw = g.paysage ? 640 : Math.min(g.W * 0.7, 640), lh = th ? lw * (th.naturalHeight / th.naturalWidth) : lw * 0.47;
            const centre = g.paysage ? 'gauche' : 'centre';
            if (g.paysage) {
                // Le logo à gauche, le verset à droite : deux colonnes de même axe vertical.
                centrer(p, zp, (q, y0) => {
                    const yl = y0, bas = yl + lh;
                    q.texte(d.surtitre, { id: 'surtitre', nom: 'Surtitre', champs: ['surtitre'], x: zp.x, y: yl - 56, w: lw, taille: S.label + 2, poids: 800, espacement: 0.3, couleur: C.cyan, interligne: 1.4, majuscules: true, capitales: true, lignesMax: 1, equilibre: false });
                    q.groupe('logo-theme', 'Logo « Toutes choses nouvelles »', { x: zp.x, y: yl, w: lw, h: lh }, () => q.image('theme', zp.x, yl, lw));

                    return bas;
                });
                centrer(p, zp, (q, y0) => {
                    const v = q.texte(d.verset, { id: 'verset', nom: 'Verset', champs: ['verset'], x: 940, y: y0, w: 880, taille: 56, min: 28, poids: 600, espacement: -0.01, couleur: C.blanc, interligne: 1.2, lignesMax: 5, optique: true });

                    return q.texte(d.reference, { id: 'reference', nom: 'Référence', champs: ['reference'], x: 940, y: v.bas + sp(g, 3), w: 880, taille: S.corps, poids: 800, espacement: 0.16, couleur: C.cyan, interligne: 1.3, majuscules: true, capitales: true, lignesMax: 1, equilibre: false }).bas;
                });
            } else {
                centrer(p, zp, (q, y0) => {
                    const s = q.texte(d.surtitre, { id: 'surtitre', nom: 'Surtitre', champs: ['surtitre'], x: zp.x, y: y0, w: zp.w, taille: S.label + 2, poids: 800, espacement: 0.3, couleur: C.cyan, interligne: 1.4, alignement: centre, majuscules: true, capitales: true, lignesMax: 1, equilibre: false });
                    const yl = s.bas + sp(g, 4), xl = (g.W - lw) / 2;
                    q.groupe('logo-theme', 'Logo « Toutes choses nouvelles »', { x: xl, y: yl, w: lw, h: lh }, () => q.image('theme', xl, yl, lw));
                    const v = q.texte(d.verset, { id: 'verset', nom: 'Verset', champs: ['verset'], x: zp.x, y: yl + lh + sp(g, 6), w: zp.w, taille: 50, min: 26, poids: 600, espacement: -0.01, couleur: C.blanc, interligne: 1.2, alignement: centre, lignesMax: 5 });

                    return q.texte(d.reference, { id: 'reference', nom: 'Référence', champs: ['reference'], x: zp.x, y: v.bas + sp(g, 3), w: zp.w, taille: S.corps - 4, poids: 800, espacement: 0.16, couleur: C.cyan, interligne: 1.3, alignement: centre, majuscules: true, capitales: true, lignesMax: 1, equilibre: false }).bas;
                });
            }
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
            { id: 'motif', libelle: 'Motif', type: 'longtexte', max: 120, defaut: 'pour sa réussite à la licence.' },
            { id: 'message', libelle: 'Le mot de l’université (facultatif)', type: 'longtexte', max: 170, defaut: '' },
            { id: 'filiere', libelle: 'Filière ou service (facultatif)', type: 'texte', max: 50, defaut: '' },
            { id: 'pied', libelle: 'Mot du pied de page', type: 'texte', max: 70, defaut: '' },
        ],
        images: [{ id: 'photo1', libelle: 'Portrait' }],
        variantes: [],
        dessiner(p, d, g) {
            const z = g.zone, S = echelle(g);
            fond(p);
            p.photo('photo1', g.photo, { id: 'photo:photo1', nom: 'Portrait', fondu: g.paysage ? { cote: 'gauche', longueur: 300 } : { cote: 'bas', longueur: 190 } });
            if (g.paysage) { voile(p, g.photo, 230, 240); }
            if (g.paysage) { p.groupe('barre-verticale', 'Barre cyan', { x: 1040, y: 0, w: 6, h: 1080 }, () => p.rect(1040, 0, 6, 1080, C.cyan)); }
            barreHaut(p, g);
            entete(p, g);
            centrer(p, z, (q, y0) => {
                let y = etiquette(q, z.x, y0, d.etiquette, z.w, 'etiquette', sp(g, 3));
                const pr = q.texte(d.prenom, { id: 'prenom', nom: 'Prénom', champs: ['prenom'], x: z.x, y, w: z.w, taille: S.display * 0.85, min: 50, poids: 900, espacement: -0.04, couleur: C.blanc, interligne: 1, majuscules: true, capitales: true, lignesMax: 1, optique: true, equilibre: false });
                const no = q.texte(d.nom, { id: 'nom', nom: 'Nom', champs: ['nom'], x: z.x, y: pr.bas, w: z.w, taille: S.display * 0.85, min: 50, poids: 900, espacement: -0.04, couleur: C.cyan, interligne: 1, majuscules: true, capitales: true, lignesMax: 1, optique: true, equilibre: false });
                const mo = q.texte(d.motif, { id: 'motif', nom: 'Motif', champs: ['motif'], x: z.x, y: no.bas + sp(g, 3), w: z.w, taille: S.h2 - 2, min: 22, poids: 500, couleur: C.blanc, interligne: 1.3, lignesMax: 3 });
                const me = d.message ? q.texte(d.message, { id: 'message', nom: 'Mot de l’université', champs: ['message'], x: z.x, y: mo.bas + sp(g, 2), w: z.w, taille: S.petit, min: 18, poids: 500, couleur: C.doux, interligne: 1.45, lignesMax: 4 }) : mo;
                if (!d.filiere) { return me.bas; }

                return q.texte(d.filiere, { id: 'filiere', nom: 'Filière', champs: ['filiere'], x: z.x, y: me.bas + sp(g, 3), w: z.w, taille: S.label - 2, poids: 800, espacement: 0.25, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1, equilibre: false }).bas;
            });
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
