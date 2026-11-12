/* =============================================================================
   Studio UAZ — les modèles.

   Un modèle est une définition de données (champs, emplacements photo, variantes rapides) et une fonction de dessin qui reçoit le stylo du moteur
   (moteur.js), les valeurs saisies et la géométrie du format. Ajouter un modèle = ajouter une entrée à MODELES, sans toucher à l'éditeur.

   Le style vient des écrans de l'université : fond marine en dégradé, cyan du thème « Toutes choses nouvelles », titres très gras (Inter 800-900,
   serrés), filets fins, photo qui se fond dans le marine, lettres « UAZ » en filigrane, en-tête et pied de page identiques partout.
   ========================================================================== */
(() => {
    'use strict';

    const { COULEURS: C } = window.StudioMoteur;
    const kk = (g) => (g.paysage ? 1 : g.k);

    /* --- Éléments communs --- */
    function fond(p) {
        p.degrade(0, 0, p.W, p.H, [0, 0, 1, 1], [[0, C.marine1], [0.45, C.marine2], [1, C.marine3]]);
    }

    function barreHaut(p, g) {
        p.rect(g.marge, 0, 128 * kk(g), 6, C.cyan);
    }

    function entete(p, g) {
        p.image('sceau', g.sceau.x, g.sceau.y, g.sceau.d, g.sceau.d);
        p.texte('UNIVERSITÉ ADVENTISTE', { x: g.tx, y: g.t1.c - g.t1.taille / 2, w: 640, taille: g.t1.taille, poids: 500, espacement: 0.055, couleur: C.blanc, interligne: 1, capitales: true, lignesMax: 1 });
        p.texte('Zurcher', { x: g.tx, y: g.t2.c - g.t2.taille / 2, w: 640, taille: g.t2.taille, poids: 700, espacement: -0.01, couleur: C.cyan, interligne: 1, lignesMax: 1 });
        if (g.paysage) { p.ligne(g.filetX[0], g.filetY, g.filetX[1], g.filetY, C.filet, 1.5); }
    }

    function pied(p, g, gauche) {
        const k = kk(g);
        p.ligne(g.filetX[0], g.pied.filetY, g.filetX[1], g.pied.filetY, C.filet, 1.5);
        p.texte(gauche, { x: g.marge, y: g.pied.y - 17, w: g.W * 0.62, taille: 22 * Math.max(k, 0.9), poids: 500, couleur: C.pale, interligne: 1.35, lignesMax: 1, nom: 'Pied de page' });
        const xr = g.W - g.marge;
        p.ligne(xr - 126, g.pied.y, xr - 78, g.pied.y, C.cyan, 2);
        p.texte('UAZ', { x: xr - 90, y: g.pied.y - 12, w: 90, taille: 20, poids: 800, espacement: 0.3, couleur: C.cyan, interligne: 1.2, alignement: 'droite', capitales: true });
    }

    function etiquette(p, x, yc, texte, w) {
        p.ligne(x, yc, x + 56, yc, C.cyan, 3);
        p.texte(texte, { x: x + 74, y: yc - 15, w: w - 74, taille: 20, poids: 800, espacement: 0.3, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1, nom: 'Étiquette' });
    }

    /** Les lettres U A Z, très pâles, empilées dans le panneau de la photo (écran en largeur seulement). */
    function filigraneUAZ(p, g) {
        if (!g.paysage) { return; }
        [['U', 295], ['A', 655], ['Z', 1018]].forEach(([lettre, base]) => {
            p.texte(lettre, { x: g.photo.x + 50, y: base - 263, w: 300, taille: 306, poids: 900, couleur: 'rgba(255,255,255,0.055)', interligne: 1, alignement: 'centre', capitales: true, espacement: 0 });
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
                p.photo(s, { x: r.x, y, w: r.w, h: h - (i < n - 1 ? 2 : 0) }, { fondu: { cote: 'gauche', longueur: 380 } });
                y += h;
            });

            return;
        }
        const largeurs = { 1: [1], 2: [0.5, 0.5], 3: [0.3, 0.4, 0.3] }[n];
        let x = 0;
        slots.forEach((s, i) => {
            const w = Math.round(r.w * largeurs[i]);
            p.photo(s, { x, y: r.y, w: w - (i < n - 1 ? 3 : 0), h: r.h }, { fondu: { cote: 'bas', longueur: 170 } });
            x += w;
        });
    }

    /** Titre en deux couleurs : la partie blanche, puis la partie cyan, à la suite. */
    function titre(p, g, blanc, accent, o) {
        return p.texteCouleurs([{ t: blanc, c: C.blanc }, { t: o.saut ? `\n${accent}` : ` ${accent}`, c: C.cyan }], Object.assign({
            poids: 900, espacement: -0.04, interligne: 1, capitales: false, nom: 'Titre',
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
        id: 'accueil', code: 'ACC', nom: 'Écran d’accueil', icone: 'bi-display',
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
            const tt = titre(p, g, d.titre1, d.titre2, { x: t.x, y: yc + 40, w: t.w, taille: 140 * k, min: 56, saut: true, majuscules: true, capitales: true, lignesMax: 3 });
            const st = p.texte(d.sousTitre, { x: t.x, y: tt.bas + 30 * k, w: t.w, taille: 36 * Math.max(k, 0.85), poids: 500, couleur: C.blanc, interligne: 1.25, lignesMax: 2, nom: 'Sous-titre' });
            if (d.precision) { p.texte(d.precision, { x: t.x, y: st.bas + 4, w: t.w, taille: 24 * Math.max(k, 0.9), poids: 500, couleur: C.doux, interligne: 1.35, lignesMax: 2, nom: 'Orateur' }); }
            pied(p, g, d.pied);
        },
    };

    /* --- 2. Direct : « Le direct commence bientôt », « en direct », « terminé » --- */
    MODELES.direct = {
        id: 'direct', code: 'DIR', nom: 'Direct', icone: 'bi-broadcast',
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
            p.photo('photo1', ph, g.paysage ? {} : { fondu: { cote: 'bas', longueur: 170 } });
            voile(p, ph, 230, g.paysage ? 330 : 0);
            const cx = g.paysage ? 1520 : g.W / 2, cy = g.paysage ? 250 : ph.y + ph.h * 0.42, R = g.paysage ? 340 : 300;
            if (d.decor) {
                p.cercle(cx, cy, R, { trait: 'rgba(0,196,212,0.30)', epaisseur: 1.5 });
                p.cercle(cx, cy, R * 0.86, { trait: 'rgba(0,196,212,0.14)', epaisseur: 1.5 });
                if (g.paysage) {
                    p.c.strokeStyle = 'rgba(255,255,255,0.16)'; p.c.lineWidth = 1.5; p.c.strokeRect(1150, 340, 580, 380);
                    p.ligne(1518, 0, 1518, 14, C.cyan, 2); p.ligne(1518, 686, 1518, 706, C.cyan, 2);
                    [1245, 1348, 1450].forEach((x) => p.cercle(x, 928, 40, { remplissage: 'rgba(255,255,255,0.12)' }));
                }
            }
            if (g.paysage) { p.rect(1104, 0, 8, 1080, C.cyan); }
            barreHaut(p, g);
            entete(p, g);

            const bx = g.paysage ? 1386 : g.marge + 8, by = g.paysage ? 105 : ph.y + 50;
            if (d.badge) {
                p.cercle(bx, by, 5, { remplissage: C.cyan });
                p.texte(d.badge, { x: bx + 22, y: by - 15, w: 420, taille: 18, poids: 700, espacement: 0.3, couleur: C.cyan, interligne: 1.7, majuscules: true, capitales: true, lignesMax: 1 });
            }
            if (d.theme1 && g.paysage) {
                p.texte(d.theme1, { x: 1620, y: 756, w: 290, taille: 11, poids: 800, espacement: 0.25, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1 });
                if (d.theme2) { p.texte(d.theme2, { x: 1620, y: 774, w: 290, taille: 11, poids: 500, espacement: 0.2, couleur: 'rgba(255,255,255,0.55)', interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1 }); }
            }

            const w = g.paysage ? 960 : t.w;
            let y = g.paysage ? 241 : t.y0 + 20;
            p.texte(d.surtitre, { x: t.x, y, w, taille: 12 * Math.max(k, 1), poids: 800, espacement: 0.3, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1 });
            y += 17 * Math.max(k, 1);
            p.texte(d.contexte, { x: t.x, y, w, taille: 12 * Math.max(k, 1), poids: 500, espacement: 0.2, couleur: C.pale, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1 });
            p.rect(t.x, y + 30, 72, 6, C.cyan);
            const tt = titre(p, g, d.titre, d.accent, { x: t.x, y: y + 62, w, taille: 150 * k, min: 56, interligne: 0.9, lignesMax: 3 });
            p.texte(d.sousTitre, { x: t.x, y: tt.bas + 22, w, taille: 26 * Math.max(k, 0.9), poids: 500, couleur: C.doux, interligne: 1.35, lignesMax: 2, nom: 'Sous-titre' });
            pied(p, g, d.pied);
        },
    };

    /* --- 3. Annonce : un événement avec sa date, son lieu et son entrée --- */
    MODELES.annonce = {
        id: 'annonce', code: 'ANN', nom: 'Annonce', icone: 'bi-megaphone',
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
            const tt = titre(p, g, d.titre1, d.titre2, { x: t.x, y: yc + 40, w: t.w, taille: 118 * k, min: 52, saut: false, majuscules: true, capitales: true, lignesMax: 3 });
            const st = p.texte(d.sousTitre, { x: t.x, y: tt.bas + 22 * k, w: t.w, taille: 32 * Math.max(k, 0.85), poids: 500, couleur: C.doux, interligne: 1.3, lignesMax: 2, nom: 'Sous-titre' });

            // Trois informations, en colonnes, sous le sous-titre et au plus près du pied de page.
            const colW = t.w / 3, vs = 26 * Math.max(k, 0.9);
            const y0 = Math.min(Math.max(st.bas + 34, g.paysage ? 790 : g.pied.filetY - 160), g.pied.filetY - 125);
            [['i1t', 'i1'], ['i2t', 'i2'], ['i3t', 'i3']].forEach(([a, b], i) => {
                const x = t.x + i * colW + (i ? 24 : 0);
                if (i) { p.ligne(t.x + i * colW, y0, t.x + i * colW, y0 + 110 * Math.max(k, 0.9), C.filet, 1.5); }
                p.texte(d[a], { x, y: y0, w: colW - 36, taille: 14, poids: 800, espacement: 0.25, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1 });
                p.texte(d[b], { x, y: y0 + 26, w: colW - 36, taille: vs, min: 16, poids: 700, couleur: C.blanc, interligne: 1.25, lignesMax: 3, nom: 'Information' });
            });
            pied(p, g, d.pied);
        },
    };

    /* --- 4. Verset : une citation, sur une photo sombre --- */
    MODELES.verset = {
        id: 'verset', code: 'VER', nom: 'Verset ou citation', icone: 'bi-quote',
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
                p.photo('photo1', { x: 0, y: 0, w: g.W, h: g.H });
                if (g.paysage) { p.degrade(0, 0, g.W, g.H, [0, 0, 1, 0], [[0, 'rgba(14,28,77,0.94)'], [0.55, 'rgba(19,35,91,0.80)'], [1, 'rgba(19,35,91,0.45)']]); } else { p.degrade(0, 0, g.W, g.H, [0, 0, 0, 1], [[0, 'rgba(14,28,77,0.78)'], [0.5, 'rgba(14,28,77,0.88)'], [1, 'rgba(14,28,77,0.96)']]); }
            } else if (p.env.apercu) {
                p.photo('photo1', { x: g.W * 0.55, y: 0, w: g.W * 0.45, h: g.H });
            }
            barreHaut(p, g);
            entete(p, g);

            const x = t.x, w = g.paysage ? 1060 : t.w;
            const y0 = g.paysage ? 262 : g.filetY + 60 * k;
            guillemet(p, x + 4, y0 + 6, 82 * k, 'rgba(0,196,212,0.85)');
            etiquette(p, x + 8, y0 + 140 * k, d.etiquette, w);
            const q = p.texte(d.texte, { x, y: y0 + 170 * k, w, taille: 82 * k, min: 34, poids: 700, espacement: -0.02, couleur: C.blanc, interligne: 1.12, lignesMax: 8, nom: 'Le texte' });
            if (d.reference) { p.texte(d.reference, { x, y: q.bas + 26, w, taille: 28 * Math.max(k, 0.85), poids: 800, espacement: 0.16, couleur: C.cyan, interligne: 1.3, majuscules: true, capitales: true, lignesMax: 1 }); }
            if (d.logoTheme && p.env.logos.theme) {
                const lw = g.paysage ? 300 : 250, lh = lw * (p.env.logos.theme.naturalHeight / p.env.logos.theme.naturalWidth);
                p.image('theme', g.W - g.marge - lw, g.pied.filetY - lh - 34, lw);
            }
            pied(p, g, d.pied);
        },
    };

    /* --- 5. Félicitations : un portrait, un nom, un mot de l'université --- */
    MODELES.felicitations = {
        id: 'felicitations', code: 'FEL', nom: 'Félicitations', icone: 'bi-award',
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
            p.photo('photo1', g.photo, { fondu: g.paysage ? { cote: 'gauche', longueur: 300 } : { cote: 'bas', longueur: 190 } });
            if (g.paysage) { voile(p, g.photo, 230, 240); }
            if (g.paysage) { p.rect(1040, 0, 6, 1080, C.cyan); }
            barreHaut(p, g);
            entete(p, g);

            const yc = g.paysage ? 300 : t.y0 + 50;
            etiquette(p, t.x, yc, d.etiquette, t.w);
            const pr = p.texte(d.prenom, { x: t.x, y: yc + 36, w: t.w, taille: 118 * k, min: 50, poids: 900, espacement: -0.04, couleur: C.blanc, interligne: 1, majuscules: true, capitales: true, lignesMax: 1, nom: 'Prénom' });
            const no = p.texte(d.nom, { x: t.x, y: pr.bas, w: t.w, taille: 118 * k, min: 50, poids: 900, espacement: -0.04, couleur: C.cyan, interligne: 1, majuscules: true, capitales: true, lignesMax: 1, nom: 'Nom' });
            const mo = p.texte(d.motif, { x: t.x, y: no.bas + 26 * k, w: t.w, taille: 34 * Math.max(k, 0.85), poids: 500, couleur: C.blanc, interligne: 1.3, lignesMax: 3, nom: 'Motif' });
            const me = p.texte(d.message, { x: t.x, y: mo.bas + 12, w: t.w, taille: 24 * Math.max(k, 0.9), poids: 500, couleur: C.doux, interligne: 1.45, lignesMax: 4, nom: 'Message' });
            if (d.filiere) { p.texte(d.filiere, { x: t.x, y: Math.min(me.bas + 20, g.pied.filetY - 50), w: t.w, taille: 16, poids: 800, espacement: 0.25, couleur: C.cyan, interligne: 1.5, majuscules: true, capitales: true, lignesMax: 1 }); }
            pied(p, g, d.pied);
        },
    };

    /** Les valeurs de départ d'un modèle. */
    function valeursInitiales(modele) {
        const v = {};
        modele.champs.forEach((c) => { v[c.id] = c.defaut; });

        return v;
    }

    window.StudioModeles = { MODELES, ORDRE: ['accueil', 'direct', 'annonce', 'verset', 'felicitations'], valeursInitiales };
})();
