/* =============================================================================
   Studio UAZ — l'éditeur (l'interface).

   Panneau de gauche : Modèles (choisir), Contenu (textes, photos), Éléments (ajouter, masquer, supprimer), Mes visuels (reprendre, dupliquer, supprimer).
   Au centre : le visuel, un onglet par format. Comme dans l'éditeur de newsletters, on agit sur le visuel lui-même :
     - un clic sélectionne un élément (texte, photo, décor, élément ajouté) ; une barre d'outils flotte au-dessus ;
     - on le déplace en le glissant, on le grossit (A+ / A−, ou la poignée d'un élément ajouté), on le modifie (✎) ou on le supprime (Suppr) ;
     - une photo du modèle se recadre en la glissant (molette : zoom) ;
     - Annuler / Rétablir (Ctrl+Z / Ctrl+Y), copie d'un élément ajouté (Ctrl+D), flèches pour ajuster au pixel.
   En haut : le nom, l'état de l'enregistrement (automatique), la retouche photo et l'export (PNG, PNG ×2, JPEG, ZIP, médiathèque).
   Le dessin est celui de moteur.js : à l'échelle de l'écran pour l'aperçu, à la taille réelle (ou ×2) pour l'export.
   ========================================================================== */
(() => {
    'use strict';

    const boot = JSON.parse(document.getElementById('st-boot').textContent);
    const { MODELES, ORDRE, valeursInitiales } = window.StudioModeles;
    const M = window.StudioMoteur;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (id) => document.getElementById(id);

    /* ---------- Petits outils ---------- */
    const h = (tag, attrs = {}, ...enfants) => {
        const el = document.createElement(tag);
        Object.entries(attrs).forEach(([k, v]) => {
            if (v === false || v == null) { return; }
            if (k === 'class') { el.className = v; } else if (k.startsWith('on')) { el.addEventListener(k.slice(2), v); } else if (k === 'value') { el.value = v; } else { el.setAttribute(k, v === true ? '' : v); }
        });
        enfants.flat().forEach((e) => { if (e != null && e !== false) { el.append(e.nodeType ? e : document.createTextNode(e)); } });

        return el;
    };
    const icone = (nom) => h('i', { class: `bi ${nom}`, 'aria-hidden': 'true' });
    const slug = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40) || 'visuel';
    const borne = (v, a, b) => Math.min(b, Math.max(a, v));
    const clone = (o) => JSON.parse(JSON.stringify(o));

    function toast(texte, type = 'info', duree = 4500) {
        const el = h('div', { class: `st-toast st-toast--${type}` }, texte);
        el.addEventListener('click', () => el.remove());
        $('stToasts').append(el);
        setTimeout(() => el.remove(), duree);
    }

    async function api(url, methode = 'GET', corps = null) {
        const init = { method: methode, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } };
        if (corps instanceof FormData) { init.body = corps; } else if (corps) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(corps); }
        const r = await fetch(url, init);
        let d = null;
        try { d = await r.json(); } catch (e) { d = null; }
        if (!r.ok) {
            const message = (d && (d.message || Object.values(d.errors || {}).flat()[0])) || `Erreur ${r.status} du serveur.`;
            throw Object.assign(new Error(message), { statut: r.status });
        }

        return d;
    }

    /* ---------- État ---------- */
    const optionsVides = () => ({ masques: [], decalages: {}, tailles: {}, libres: [] });
    const etat = { id: null, nom: '', modele: 'accueil', format: '16:9', champs: {}, photos: {}, options: optionsVides(), onglet: 'modeles', designs: boot.designs || [] };
    const env = { photos: {}, logos: {}, cache: new Map(), apercu: true, echelle: 1 };
    let sale = false, minuterieSauvegarde = 0, jetonRendu = 0, dernieresAlertes = [], zones = [], selection = null, compteurLibres = 0;
    const modele = () => MODELES[etat.modele];
    const decalagesFormat = () => (etat.options.decalages[etat.format] = etat.options.decalages[etat.format] || {});

    function nomParDefaut(m) { return `${m.nom} — ${new Date().toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })}`; }

    function nouveau(idModele = etat.modele) {
        const m = MODELES[idModele];
        Object.assign(etat, { id: null, modele: idModele, format: m.formats.includes(etat.format) ? etat.format : m.formats[0], champs: valeursInitiales(m), photos: {}, options: optionsVides() });
        etat.nom = nomParDefaut(m);
        env.photos = {};
        selection = null;
        sale = false;
        historique.vider();
    }

    /* ---------- Historique : annuler / rétablir ---------- */
    const historique = (() => {
        const pile = [];
        let i = -1, minuterie = 0;
        const instantane = () => JSON.stringify({ m: etat.modele, f: etat.format, c: etat.champs, p: etat.photos, o: etat.options });
        const majBoutons = () => { $('stAnnuler').disabled = i <= 0; $('stRetablir').disabled = i >= pile.length - 1; };
        const poser = () => {
            clearTimeout(minuterie);
            const s = instantane();
            if (pile[i] === s) { return; }
            pile.length = i + 1; pile.push(s); i = pile.length - 1;
            if (pile.length > 80) { pile.shift(); i--; }
            majBoutons();
        };
        const restaurer = (s) => {
            const d = JSON.parse(s);
            Object.assign(etat, { modele: d.m, format: d.f, champs: d.c, photos: d.p, options: d.o });
            const prises = d.o.libres.map((l) => Number(String(l.id).replace(/\D/g, '')) || 0);
            compteurLibres = Math.max(compteurLibres, ...prises, 0);
            if (selection && !zones.some((z) => z.id === selection)) { selection = null; }
            marquerModifie(false);
            dessinerTout();
        };
        return {
            vider() { clearTimeout(minuterie); pile.length = 0; i = -1; poser(); },
            /** Après une action de la personne ; `vite` = tout de suite, sinon regroupé (frappe au clavier, curseurs). */
            pousser(vite = true) { if (vite) { poser(); } else { clearTimeout(minuterie); minuterie = setTimeout(poser, 500); } },
            annuler() { poser(); if (i > 0) { restaurer(pile[--i]); majBoutons(); } },
            retablir() { if (i < pile.length - 1) { restaurer(pile[++i]); majBoutons(); } },
        };
    })();

    /* ---------- Rendu ---------- */
    function poseEnv(slot) {
        const p = etat.photos[slot];
        const r = env.photos[slot];
        if (!p) { delete env.photos[slot]; return; }
        if (r && r.url === p.url && r.img) {
            Object.assign(r, { focus: p.focus, zoom: p.zoom, libelle: p.libelle });

            return;
        }
        env.photos[slot] = { url: p.url, img: null, w: p.w, h: p.h, focus: p.focus, zoom: p.zoom, version: ++poseEnv.v, libelle: p.libelle };
    }
    poseEnv.v = 0;

    async function chargerPhotos() {
        const attentes = [];
        Object.keys(etat.photos).forEach((slot) => {
            poseEnv(slot);
            const r = env.photos[slot];
            if (r && !r.img) {
                attentes.push(M.chargerImage(r.url).then((img) => {
                    if (env.photos[slot] !== r) { return; }
                    if (!img) { toast(`La photo « ${r.libelle || slot} » n'a pas pu être chargée.`, 'error'); delete env.photos[slot]; delete etat.photos[slot]; return; }
                    r.img = img; r.w = img.naturalWidth; r.h = img.naturalHeight;
                }));
            }
        });
        Object.keys(env.photos).forEach((slot) => { if (!etat.photos[slot]) { delete env.photos[slot]; } });
        await Promise.all(attentes);
    }

    function echelleAffichage() {
        const toile = $('stToile');
        const f = M.FORMATS[etat.format];
        const dispoL = toile.clientWidth - 48, dispoH = toile.clientHeight - 48;
        const s = Math.max(0.05, Math.min(dispoL / f.w, dispoH / f.h));

        return { s, w: Math.floor(f.w * s), h: Math.floor(f.h * s) };
    }

    let rafRendu = 0;
    function planifierRendu() {
        cancelAnimationFrame(rafRendu);
        rafRendu = requestAnimationFrame(dessiner);
    }

    async function dessiner() {
        const mon = ++jetonRendu;
        await chargerPhotos();
        if (mon !== jetonRendu) { return; }
        const m = modele();
        const f = M.FORMATS[etat.format];
        const { s, w, h: hh } = echelleAffichage();
        const canvas = $('stCanvas');
        const cadre = $('stCadre');
        cadre.style.width = `${w}px`; cadre.style.height = `${hh}px`;
        const echelle = Math.min(1, Math.max(0.35, (w * (window.devicePixelRatio || 1)) / f.w));
        const res = M.rendre(canvas, m, etat.champs, etat.format, { photos: env.photos, logos: env.logos, cache: env.cache, apercu: true, echelle, options: etat.options });
        canvas.style.width = '100%'; canvas.style.height = '100%';
        dernieresAlertes = res.alertes;
        zones = res.zones;
        $('stDims').textContent = `${f.w} × ${f.h} px · ${Math.round(s * 100)} %`;
        $('stAlertes').replaceChildren(...res.alertes.map((a) => h('li', { class: `st-alerte st-alerte--${a.niveau}` }, icone(a.niveau === 'erreur' ? 'bi-exclamation-octagon' : 'bi-exclamation-triangle'), a.message)));
        const story = etat.format === '9:16';
        $('stZoneBloc').hidden = !story;
        $('stZoneHaut').hidden = $('stZoneBas').hidden = !(story && $('stZone').checked);
        placerSelection();
        if (etat.onglet === 'elements' && !glissement) { dessinerElements(); }
    }

    function dessinerTout() {
        dessinerModeles();
        dessinerFormats();
        dessinerContenu();
        dessinerElements();
        $('stNom').value = etat.nom;
        planifierRendu();
    }

    /* ---------- Enregistrement automatique ---------- */
    function marquerModifie(pousser = true, vite = true) {
        sale = true;
        if (pousser) { historique.pousser(vite); }
        majEtat('Modifications non enregistrées…');
        clearTimeout(minuterieSauvegarde);
        minuterieSauvegarde = setTimeout(sauvegarder, 1400);
    }

    function majEtat(texte) { $('stEtat').textContent = texte; }

    function donneesEnvoi() {
        const photos = {};
        Object.entries(etat.photos).forEach(([slot, p]) => { photos[slot] = { media: p.media, focus: p.focus, zoom: p.zoom }; });

        return { name: etat.nom || nomParDefaut(modele()), template: etat.modele, format: etat.format, data: { champs: etat.champs, photos, options: etat.options } };
    }

    async function vignette() {
        const c = document.createElement('canvas');
        M.rendre(c, modele(), etat.champs, modele().formats[0], { photos: env.photos, logos: env.logos, cache: new Map(), apercu: false, echelle: 0.2, options: etat.options });
        const maxW = 360, s = Math.min(1, maxW / c.width), t = document.createElement('canvas');
        t.width = Math.round(c.width * s); t.height = Math.round(c.height * s);
        t.getContext('2d').drawImage(c, 0, 0, t.width, t.height);

        return t.toDataURL('image/jpeg', 0.72);
    }

    async function sauvegarder() {
        clearTimeout(minuterieSauvegarde);
        if (!sale) { return; }
        sale = false;
        majEtat('Enregistrement…');
        try {
            const corps = Object.assign(donneesEnvoi(), { thumb: await vignette() });
            if (etat.id) {
                await api(`${boot.urls.design}/${etat.id}`, 'PUT', corps);
            } else {
                const d = await api(boot.urls.designs, 'POST', corps);
                etat.id = d.design.id;
            }
            majEtat(`Enregistré à ${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`);
            etat.designs = (await api(boot.urls.designs)).designs;
            if (etat.onglet === 'visuels') { dessinerVisuels(); }
        } catch (e) {
            sale = true;
            majEtat('Enregistrement impossible : nouvel essai bientôt');
            console.error(e);
            clearTimeout(minuterieSauvegarde);
            minuterieSauvegarde = setTimeout(sauvegarder, 8000);
        }
    }

    /* ---------- Onglets ---------- */
    function onglet(nom) {
        etat.onglet = nom;
        document.querySelectorAll('.st-onglet').forEach((b) => { const on = b.dataset.onglet === nom; b.classList.toggle('is-on', on); b.setAttribute('aria-selected', String(on)); });
        document.querySelectorAll('.st-volet').forEach((v) => { v.hidden = v.dataset.volet !== nom; });
        if (nom === 'visuels') { dessinerVisuels(); }
        if (nom === 'elements') { dessinerElements(); }
    }
    document.querySelectorAll('.st-onglet').forEach((b) => b.addEventListener('click', () => onglet(b.dataset.onglet)));

    /* ---------- Modèles ---------- */
    function dessinerModeles() {
        const zone = $('stModeles');
        zone.replaceChildren(h('p', { class: 'st-aide' }, 'Choisissez le style de votre visuel. Vos textes et vos photos restent quand vous changez de modèle ; les éléments que vous avez ajoutés aussi.'));
        ['Écrans', 'Affiches'].forEach((genre) => {
            const ids = ORDRE.filter((id) => MODELES[id].genre === genre);
            zone.append(h('h3', { class: 'st-titre-groupe' }, genre === 'Écrans' ? 'Écrans (direct, accueil)' : 'Affiches pour les réseaux et l’impression'));
            ids.forEach((id) => {
                const m = MODELES[id];
                const c = document.createElement('canvas');
                c.className = 'st-modele__vue';
                M.rendre(c, m, valeursInitiales(m), m.formats[0], { photos: {}, logos: env.logos, cache: new Map(), apercu: false, echelle: 0.3, options: optionsVides() });
                zone.append(h('button', { type: 'button', class: `st-modele${etat.modele === id ? ' is-on' : ''}`, 'aria-pressed': String(etat.modele === id), onclick: () => choisirModele(id) },
                    h('span', { class: 'st-modele__cadre' }, c),
                    h('span', { class: 'st-modele__nom' }, icone(m.icone), m.nom),
                    h('span', { class: 'st-modele__desc' }, m.description)));
            });
        });
    }

    function choisirModele(id) {
        if (id === etat.modele) { onglet('contenu'); return; }
        const ancien = modele();
        const neuf = MODELES[id];
        const anciensDefauts = valeursInitiales(ancien);
        const champs = valeursInitiales(neuf);
        // Les valeurs retouchées à la main et communes aux deux modèles (pied de page, étiquette…) sont reprises.
        Object.keys(champs).forEach((k) => { if (k in etat.champs && etat.champs[k] !== anciensDefauts[k] && typeof etat.champs[k] === typeof champs[k]) { champs[k] = etat.champs[k]; } });
        etat.modele = id;
        etat.champs = champs;
        if (!neuf.formats.includes(etat.format)) { etat.format = neuf.formats[0]; }
        const slotsNeufs = new Set([...neuf.images.map((i) => i.id), ...etat.options.libres.filter((l) => l.type === 'photo').map((l) => l.slot)]);
        Object.keys(etat.photos).forEach((s) => { if (!slotsNeufs.has(s)) { delete etat.photos[s]; } });
        // Les éléments masqués, déplacés ou agrandis étaient ceux de l'ancien modèle ; les éléments ajoutés restent.
        etat.options.masques = []; etat.options.decalages = {}; etat.options.tailles = {};
        selection = null;
        marquerModifie();
        dessinerTout();
        onglet('contenu');
    }

    /* ---------- Champs (textes du modèle) ---------- */
    function champ(c, apres = null) {
        const valeur = etat.champs[c.id];
        const enregistre = (v, vite = false) => { etat.champs[c.id] = v; marquerModifie(true, vite); planifierRendu(); };
        if (c.type === 'interrupteur') {
            return h('label', { class: 'st-interrupteur' }, h('input', { type: 'checkbox', checked: !!valeur, onchange: (e) => { enregistre(e.target.checked, true); if (apres) { apres(); } } }), h('span', {}, c.libelle));
        }
        if (c.type === 'choix') {
            return h('div', { class: 'st-champ' }, h('span', { class: 'st-libelle' }, c.libelle),
                h('div', { class: 'st-segment', role: 'group' }, c.options.map((o) => h('button', { type: 'button', 'aria-pressed': String(String(valeur) === o.v), onclick: () => { enregistre(o.v, true); dessinerContenu(); if (apres) { apres(); } } }, o.l))));
        }
        const compteur = h('em', {}, `${String(valeur ?? '').length}/${c.max}`);
        const maj = (e) => { compteur.textContent = `${e.target.value.length}/${c.max}`; enregistre(e.target.value); };
        const saisie = c.type === 'longtexte'
            ? h('textarea', { rows: '3', maxlength: String(c.max), value: valeur ?? '', oninput: maj })
            : h('input', { type: 'text', maxlength: String(c.max), value: valeur ?? '', autocomplete: 'off', oninput: maj });

        return h('label', { class: 'st-champ' }, h('span', { class: 'st-libelle' }, c.libelle, compteur), saisie);
    }

    function emplacementPhoto(im) {
        const p = etat.photos[im.id];
        const curseur = (nom, valeur, min, max, pas, fn) => h('label', { class: 'st-curseur' }, h('span', {}, nom),
            h('input', { type: 'range', min: String(min), max: String(max), step: String(pas), value: String(valeur), oninput: (e) => { fn(parseFloat(e.target.value)); marquerModifie(true, false); planifierRendu(); } }));

        return h('div', { class: 'st-photo' },
            h('div', { class: 'st-photo__haut' },
                h('div', { class: 'st-photo__vue' }, p ? h('img', { src: p.thumb || p.url, alt: '' }) : icone('bi-image')),
                h('div', { class: 'st-photo__corps' },
                    h('strong', {}, im.libelle),
                    h('div', { class: 'st-photo__actions' },
                        h('button', { type: 'button', class: 'st-btn st-btn--petit st-btn--primaire', onclick: () => ouvrirMedias(im.id) }, icone('bi-images'), p ? 'Changer' : 'Choisir'),
                        p && h('button', { type: 'button', class: 'st-btn st-btn--petit', onclick: () => ouvrirRetouche(im.id), title: 'Ouvre la photo dans la retouche photo ; « Renvoyer au Studio UAZ » la remet ici.' }, icone('bi-sliders'), 'Retoucher'),
                        p && h('button', { type: 'button', class: 'st-btn st-btn--petit', onclick: () => retirerPhoto(im.id), 'aria-label': `Retirer ${im.libelle}` }, icone('bi-x-lg')))),
            ),
            p && h('div', { class: 'st-photo__reglages' },
                curseur('Cadrage ↔', p.focus[0], 0, 1, 0.01, (v) => { p.focus = [v, p.focus[1]]; }),
                curseur('Cadrage ↕', p.focus[1], 0, 1, 0.01, (v) => { p.focus = [p.focus[0], v]; }),
                curseur('Zoom', p.zoom, 1, 3, 0.05, (v) => { p.zoom = v; })));
    }

    function retirerPhoto(slot) {
        delete etat.photos[slot];
        marquerModifie();
        dessinerContenu();
        dessinerElements();
        planifierRendu();
    }

    function dessinerContenu() {
        const m = modele();
        const zone = $('stContenu');
        const garde = zone.scrollTop;
        zone.replaceChildren(
            h('div', { class: 'st-entete' }, icone(m.icone), h('div', {}, h('strong', {}, m.nom), h('span', {}, m.description))),
            m.variantes.length ? h('div', { class: 'st-variantes' }, h('span', { class: 'st-libelle' }, 'Modèles rapides'),
                h('div', {}, m.variantes.map((v) => h('button', { type: 'button', class: 'st-puce', onclick: () => { Object.assign(etat.champs, v.champs); marquerModifie(); dessinerContenu(); planifierRendu(); } }, v.nom)))) : null,
            h('section', { class: 'st-groupe' }, h('h3', {}, 'Textes'), m.champs.map((c) => champ(c))),
            m.images.length ? h('section', { class: 'st-groupe' }, h('h3', {}, 'Photos'), m.images.filter((i) => !i.visible || i.visible(etat.champs)).map(emplacementPhoto)) : null,
            h('button', { type: 'button', class: 'st-lien', onclick: () => { etat.champs = valeursInitiales(m); marquerModifie(); dessinerContenu(); planifierRendu(); } }, icone('bi-arrow-counterclockwise'), ' Remettre les textes d’exemple'),
        );
        zone.scrollTop = garde;
        $('stNom').value = etat.nom;
    }

    /* ---------- Formats ---------- */
    function dessinerFormats() {
        const m = modele();
        $('stFormats').replaceChildren(...m.formats.map((f) => h('button', { type: 'button', role: 'tab', class: `st-format${etat.format === f ? ' is-on' : ''}`, 'aria-selected': String(etat.format === f), title: M.FORMATS[f].usage, onclick: () => { etat.format = f; marquerModifie(); dessinerFormats(); fermerPopover(); planifierRendu(); } },
            h('span', { class: 'st-format__forme', style: `--a:${M.FORMATS[f].w};--b:${M.FORMATS[f].h}`, 'aria-hidden': 'true' }), h('span', {}, M.FORMATS[f].nom))));
    }

    /* ======================================================================
       Les éléments du visuel : sélection, déplacement, taille, suppression, ajout
       ====================================================================== */
    const TYPES_AJOUT = [
        ['texte', 'bi-fonts', 'Texte'], ['pastille', 'bi-tag', 'Pastille'], ['forme', 'bi-square', 'Rectangle'], ['cercle', 'bi-circle', 'Cercle'],
        ['ligne', 'bi-dash-lg', 'Ligne'], ['photo', 'bi-image', 'Photo'], ['sceau', 'bi-patch-check', 'Sceau UAZ'], ['theme', 'bi-stars', 'Logo du thème'],
    ];
    const COULEURS_PALETTE = ['#ffffff', '#00c4d4', '#1a2b6a', '#0e1c4d', '#e0b957', '#000000', '#e45f5f'];

    const zoneDe = (id) => zones.find((z) => z.id === id) || null;
    const libreDe = (id) => etat.options.libres.find((l) => l.id === id) || null;

    function ajouter(type) {
        const id = `l${++compteurLibres}`;
        const base = { id, x: 0.12 + (compteurLibres % 5) * 0.03, y: 0.3 + (compteurLibres % 5) * 0.03 };
        let e;
        if (type === 'texte') { e = Object.assign(base, { type: 'texte', texte: 'Votre texte', w: 0.5, taille: 0.06, couleur: '#ffffff', poids: 800, align: 'gauche', majuscules: false }); } else if (type === 'pastille') { e = Object.assign(base, { type: 'pastille', texte: 'Nouveau', w: 0.6, taille: 0.032, couleur: '#0e1c4d', fond: '#00c4d4', poids: 800, majuscules: true, espacement: 0.08 }); } else if (type === 'forme') { e = Object.assign(base, { type: 'forme', kind: 'rect', w: 0.3, h: 0.18, couleur: '#00c4d4', opacite: 0.9, rayon: 0.01 }); } else if (type === 'cercle') { e = Object.assign(base, { type: 'forme', kind: 'cercle', w: 0.2, h: 0.2, couleur: '#00c4d4', opacite: 0.9 }); } else if (type === 'ligne') { e = Object.assign(base, { type: 'ligne', w: 0.3, epaisseur: 5, couleur: '#00c4d4' }); } else if (type === 'photo') { e = Object.assign(base, { type: 'photo', w: 0.4, h: 0.3, slot: `lp${compteurLibres}`, nom: 'Photo ajoutée' }); } else if (type === 'sceau') { e = Object.assign(base, { type: 'logo', logo: 'sceau', w: 0.16 }); } else { e = Object.assign(base, { type: 'logo', logo: 'theme', w: 0.4 }); }
        etat.options.libres.push(e);
        selection = id;
        marquerModifie();
        dessinerElements();
        planifierRendu();
        if (type === 'photo') { ouvrirMedias(e.slot); } else if (e.type === 'texte' || e.type === 'pastille') { setTimeout(() => ouvrirPopover(), 120); }
    }

    function supprimerSelection() {
        if (!selection) { return; }
        const l = libreDe(selection);
        if (l) {
            etat.options.libres = etat.options.libres.filter((x) => x.id !== selection);
            if (l.type === 'photo') { delete etat.photos[l.slot]; }
            selection = null;
        } else {
            const z = zoneDe(selection);
            if (z && z.type === 'photo' && z.slot) { delete etat.photos[z.slot]; } else if (!etat.options.masques.includes(selection)) { etat.options.masques.push(selection); }
            selection = null;
        }
        fermerPopover();
        marquerModifie();
        dessinerElements();
        planifierRendu();
    }

    function afficher(id) {
        etat.options.masques = etat.options.masques.filter((x) => x !== id);
        const l = libreDe(id);
        if (l) { delete l.masque; }
        marquerModifie();
        dessinerElements();
        planifierRendu();
    }

    function dupliquerSelection() {
        const l = selection && libreDe(selection);
        if (!l) { return; }
        const copie = Object.assign(clone(l), { id: `l${++compteurLibres}`, x: Math.min(0.9, l.x + 0.03), y: Math.min(0.9, l.y + 0.03) });
        if (l.type === 'photo') { copie.slot = `lp${compteurLibres}`; if (etat.photos[l.slot]) { etat.photos[copie.slot] = clone(etat.photos[l.slot]); } }
        etat.options.libres.push(copie);
        selection = copie.id;
        marquerModifie();
        dessinerElements();
        planifierRendu();
    }

    function ordre(sens) {
        const l = selection && libreDe(selection);
        if (!l) { return; }
        const liste = etat.options.libres, i = liste.indexOf(l), j = borne(i + sens, 0, liste.length - 1);
        if (i === j) { return; }
        liste.splice(i, 1); liste.splice(j, 0, l);
        marquerModifie();
        planifierRendu();
    }

    function grossir(facteur) {
        const l = selection && libreDe(selection);
        if (l) {
            if (l.type === 'texte' || l.type === 'pastille') { l.taille = borne((l.taille || 0.04) * facteur, 0.012, 0.3); } else { l.w = borne(l.w * facteur, 0.03, 1.5); if (l.h) { l.h = borne(l.h * facteur, 0.02, 1.5); } }
        } else if (selection) {
            etat.options.tailles[selection] = borne((etat.options.tailles[selection] || 1) * facteur, 0.4, 2.6);
        }
        marquerModifie(true, false);
        planifierRendu();
    }

    function repositionner() {
        if (!selection || libreDe(selection)) { return; }
        delete decalagesFormat()[selection];
        delete etat.options.tailles[selection];
        marquerModifie();
        planifierRendu();
    }

    /* --- La liste des éléments (onglet « Éléments ») --- */
    function dessinerElements() {
        const zone = $('stElements');
        const garde = zone.scrollTop;
        const vues = new Map();
        zones.forEach((z) => { if (!vues.has(z.id)) { vues.set(z.id, z); } });
        const lignes = [...vues.values()].filter((z) => !(z.type === 'photo' && z.slot && !z.libre && false));
        zone.replaceChildren(
            h('p', { class: 'st-aide' }, 'Cliquez sur un élément du visuel pour le modifier, le déplacer ou le supprimer. Ajoutez ici vos propres éléments.'),
            h('h3', { class: 'st-titre-groupe' }, 'Ajouter un élément'),
            h('div', { class: 'st-ajouts' }, TYPES_AJOUT.map(([t, i, n]) => h('button', { type: 'button', class: 'st-ajout', onclick: () => ajouter(t) }, icone(i), h('span', {}, n)))),
            h('h3', { class: 'st-titre-groupe' }, 'Éléments de ce visuel'),
            h('ul', { class: 'st-elements' }, lignes.slice().reverse().map((z) => {
                const cache = z.masque;
                return h('li', { class: `st-element${selection === z.id ? ' is-on' : ''}${cache ? ' is-masque' : ''}` },
                    h('button', { type: 'button', class: 'st-element__nom', disabled: cache, onclick: () => selectionner(z.id, true) }, icone({ texte: 'bi-fonts', photo: 'bi-image', decor: 'bi-bounding-box', libre: 'bi-plus-square' }[z.libre && z.type !== 'photo' ? 'libre' : z.type] || 'bi-square'), h('span', {}, z.nom || z.id), z.libre ? h('em', {}, 'ajouté') : null, cache ? h('em', {}, 'masqué') : null),
                    cache
                        ? h('button', { type: 'button', class: 'st-btn st-btn--icone st-btn--petit', title: 'Réafficher', 'aria-label': `Réafficher ${z.nom}`, onclick: () => afficher(z.id) }, icone('bi-eye'))
                        : z.type === 'photo' && z.vide
                            ? h('button', { type: 'button', class: 'st-btn st-btn--icone st-btn--petit', title: 'Choisir une photo', 'aria-label': `Choisir ${z.nom}`, onclick: () => ouvrirMedias(z.slot) }, icone('bi-images'))
                            : h('button', { type: 'button', class: 'st-btn st-btn--icone st-btn--petit', title: z.libre || z.type === 'photo' ? 'Supprimer' : 'Masquer', 'aria-label': `Supprimer ${z.nom}`, onclick: () => { selection = z.id; supprimerSelection(); } }, icone(z.libre || z.type === 'photo' ? 'bi-trash3' : 'bi-eye-slash')));
            })));
        zone.scrollTop = garde;
    }

    /* --- Sélection sur le visuel --- */
    const priorite = (z) => (z.libre && z.type !== 'photo' ? 0 : z.type === 'texte' ? 1 : z.type === 'decor' ? (z.rect.w * z.rect.h > 160000 ? 4 : 2) : 3);

    function trouver(x, y) {
        const tol = 6;
        const candidats = zones.filter((z) => z.rect && !z.masque && x >= z.rect.x - tol && x <= z.rect.x + z.rect.w + tol && y >= z.rect.y - tol && y <= z.rect.y + z.rect.h + tol);
        if (!candidats.length) { return null; }
        const ordres = new Map(zones.map((z, i) => [z, i]));
        candidats.sort((a, b) => priorite(a) - priorite(b) || ordres.get(b) - ordres.get(a));

        return candidats[0];
    }

    function selectionner(id, defiler = false) {
        if (selection !== id) { fermerPopover(); }
        selection = id;
        placerSelection();
        if (etat.onglet === 'elements') { dessinerElements(); }
        if (defiler && id) { const z = zoneDe(id); if (!z) { return; } }
    }

    const echelle = () => echelleAffichage().s;

    function placerSelection() {
        const boite = $('stSel'), barre = $('stBarreEl');
        const z = selection && zoneDe(selection);
        if (!z || !z.rect || z.masque) { boite.hidden = true; barre.hidden = true; return; }
        const s = echelle();
        const r = z.rect;
        const pad = 4;
        Object.assign(boite.style, { left: `${r.x * s - pad}px`, top: `${r.y * s - pad}px`, width: `${r.w * s + 2 * pad}px`, height: `${r.h * s + 2 * pad}px` });
        boite.hidden = false;
        boite.classList.toggle('st-sel--libre', !!libreDe(z.id) && libreDe(z.id).type !== 'ligne');
        dessinerBarre(z);
        barre.hidden = false;
        const bw = barre.offsetWidth, cw = $('stCadre').clientWidth;
        const dessus = r.y * s - pad - barre.offsetHeight - 8;
        barre.style.top = `${dessus >= 4 ? dessus : (r.y + r.h) * s + pad + 8}px`;
        barre.style.left = `${borne(r.x * s - pad, 4, Math.max(4, cw - bw - 4))}px`;
        if (popoverOuvert) { positionnerPopover(); }
    }

    function dessinerBarre(z) {
        const barre = $('stBarreEl');
        const l = libreDe(z.id);
        const bouton = (nom, titre, fn, danger = false) => h('button', { type: 'button', class: `st-barre-el__btn${danger ? ' is-danger' : ''}`, title: titre, 'aria-label': titre, onclick: (e) => { e.stopPropagation(); fn(); } }, icone(nom));
        const boutons = [h('span', { class: 'st-barre-el__nom' }, z.nom || z.id)];
        if (z.type === 'photo' && !z.libre) {
            boutons.push(bouton('bi-images', 'Changer la photo', () => ouvrirMedias(z.slot)));
            if (!z.vide) { boutons.push(bouton('bi-sliders', 'Retoucher la photo', () => ouvrirRetouche(z.slot))); }
            boutons.push(bouton('bi-trash3', 'Retirer la photo', supprimerSelection, true));
        } else {
            if ((z.champs && z.champs.length) || l) { boutons.push(bouton('bi-pencil', 'Modifier', ouvrirPopover)); }
            if (z.type === 'texte' || (l && (l.type === 'texte' || l.type === 'pastille'))) { boutons.push(bouton('bi-dash-lg', 'Plus petit', () => grossir(0.9)), bouton('bi-plus-lg', 'Plus grand', () => grossir(1.1))); } else if (l) { boutons.push(bouton('bi-fullscreen-exit', 'Plus petit', () => grossir(0.9)), bouton('bi-fullscreen', 'Plus grand', () => grossir(1.1))); }
            if (l) { boutons.push(bouton('bi-files', 'Dupliquer (Ctrl+D)', dupliquerSelection), bouton('bi-layers', 'Passer devant', () => ordre(1)), bouton('bi-layers-half', 'Passer derrière', () => ordre(-1))); } else { boutons.push(bouton('bi-arrow-counterclockwise', 'Remettre à sa place et à sa taille', repositionner)); }
            boutons.push(bouton(l || z.type === 'photo' ? 'bi-trash3' : 'bi-eye-slash', l ? 'Supprimer (Suppr)' : 'Masquer (Suppr)', supprimerSelection, true));
        }
        barre.replaceChildren(...boutons);
    }

    /* --- Fenêtre « Modifier » d'un élément --- */
    let popoverOuvert = false;
    function fermerPopover() { popoverOuvert = false; $('stPopover').hidden = true; }

    function ouvrirPopover() {
        const z = selection && zoneDe(selection);
        if (!z) { return; }
        popoverOuvert = true;
        const pop = $('stPopover');
        const l = libreDe(z.id);
        const rendre = () => planifierRendu();
        const maj = (fn, vite = false) => { fn(); marquerModifie(true, vite); rendre(); };
        const curseur = (nom, valeur, min, max, pas, cle) => h('label', { class: 'st-curseur' }, h('span', {}, nom), h('input', { type: 'range', min: String(min), max: String(max), step: String(pas), value: String(valeur), oninput: (e) => maj(() => { l[cle] = parseFloat(e.target.value); }) }));
        const palette = (nom, cle) => h('div', { class: 'st-champ' }, h('span', { class: 'st-libelle' }, nom), h('div', { class: 'st-palette' },
            COULEURS_PALETTE.map((c) => h('button', { type: 'button', class: `st-couleur${l[cle] === c ? ' is-on' : ''}`, style: `background:${c}`, 'aria-label': c, title: c, onclick: () => { maj(() => { l[cle] = c; }, true); ouvrirPopover(); } })),
            h('input', { type: 'color', value: /^#[0-9a-f]{6}$/i.test(l[cle] || '') ? l[cle] : '#ffffff', 'aria-label': 'Autre couleur', oninput: (e) => maj(() => { l[cle] = e.target.value; }) })));
        const choixSeg = (nom, cle, options) => h('div', { class: 'st-champ' }, h('span', { class: 'st-libelle' }, nom), h('div', { class: 'st-segment' }, options.map(([v, t]) => h('button', { type: 'button', 'aria-pressed': String(l[cle] === v), onclick: () => { maj(() => { l[cle] = v; }, true); ouvrirPopover(); } }, t))));
        const contenu = [h('div', { class: 'st-popover__tete' }, h('strong', {}, z.nom || z.id), h('button', { type: 'button', class: 'st-btn st-btn--icone st-btn--petit', 'aria-label': 'Fermer', onclick: fermerPopover }, icone('bi-x-lg')))];

        if (!l) {
            const defs = (z.champs || []).map((id) => modele().champs.find((c) => c.id === id)).filter(Boolean);
            defs.forEach((c) => contenu.push(champ(c, () => ouvrirPopover())));
            if (!defs.length) { contenu.push(h('p', { class: 'st-aide' }, 'Cet élément n’a pas de texte à modifier : déplacez-le, ou masquez-le avec la corbeille.')); }
        } else if (l.type === 'texte' || l.type === 'pastille') {
            contenu.push(h('label', { class: 'st-champ' }, h('span', { class: 'st-libelle' }, 'Texte'), h('textarea', { rows: '3', maxlength: '300', value: l.texte, oninput: (e) => maj(() => { l.texte = e.target.value; }) })));
            contenu.push(curseur('Taille', l.taille, 0.012, 0.2, 0.002, 'taille'));
            contenu.push(palette('Couleur du texte', 'couleur'));
            if (l.type === 'pastille') { contenu.push(palette('Couleur du fond', 'fond')); }
            contenu.push(choixSeg('Graisse', 'poids', [[300, 'Fin'], [500, 'Normal'], [800, 'Gras'], [900, 'Très gras']]));
            if (l.type === 'texte') { contenu.push(choixSeg('Alignement', 'align', [['gauche', 'Gauche'], ['centre', 'Centre'], ['droite', 'Droite']])); contenu.push(curseur('Largeur', l.w, 0.1, 1, 0.01, 'w')); }
            contenu.push(h('label', { class: 'st-interrupteur' }, h('input', { type: 'checkbox', checked: !!l.majuscules, onchange: (e) => maj(() => { l.majuscules = e.target.checked; }, true) }), h('span', {}, 'Tout en majuscules')));
        } else if (l.type === 'forme') {
            contenu.push(palette('Couleur', 'couleur'), curseur('Opacité', l.opacite == null ? 1 : l.opacite, 0.05, 1, 0.05, 'opacite'));
            if (l.kind === 'rect') { contenu.push(curseur('Coins arrondis', l.rayon || 0, 0, 0.2, 0.005, 'rayon')); }
            contenu.push(h('label', { class: 'st-interrupteur' }, h('input', { type: 'checkbox', checked: !!l.contour, onchange: (e) => maj(() => { l.contour = e.target.checked; }, true) }), h('span', {}, 'Contour seulement')));
        } else if (l.type === 'ligne') {
            contenu.push(palette('Couleur', 'couleur'), curseur('Épaisseur', l.epaisseur, 1, 30, 1, 'epaisseur'), curseur('Longueur', l.w, 0.03, 1, 0.01, 'w'));
        } else if (l.type === 'logo') {
            contenu.push(choixSeg('Logo', 'logo', [['sceau', 'Sceau de l’UAZ'], ['theme', 'Toutes choses nouvelles']]), curseur('Taille', l.w, 0.04, 1, 0.01, 'w'));
        } else if (l.type === 'photo') {
            const p = etat.photos[l.slot];
            contenu.push(h('div', { class: 'st-photo__actions' }, h('button', { type: 'button', class: 'st-btn st-btn--petit st-btn--primaire', onclick: () => ouvrirMedias(l.slot) }, icone('bi-images'), p ? 'Changer la photo' : 'Choisir une photo')));
            if (p) {
                const cp = (nom, v, min, max, pas, fn) => h('label', { class: 'st-curseur' }, h('span', {}, nom), h('input', { type: 'range', min: String(min), max: String(max), step: String(pas), value: String(v), oninput: (e) => maj(() => fn(parseFloat(e.target.value))) }));
                contenu.push(cp('Cadrage ↔', p.focus[0], 0, 1, 0.01, (v) => { p.focus = [v, p.focus[1]]; }), cp('Cadrage ↕', p.focus[1], 0, 1, 0.01, (v) => { p.focus = [p.focus[0], v]; }), cp('Zoom', p.zoom, 1, 3, 0.05, (v) => { p.zoom = v; }));
            }
        }
        if (l) { contenu.push(curseur('Opacité', l.opacite == null ? 1 : l.opacite, 0.05, 1, 0.05, 'opacite')); }
        pop.replaceChildren(...contenu);
        pop.hidden = false;
        positionnerPopover();
    }

    function positionnerPopover() {
        const pop = $('stPopover'), z = selection && zoneDe(selection), cadre = $('stCadre').getBoundingClientRect(), scene = $('stToile').getBoundingClientRect();
        if (!z || !z.rect) { return; }
        const s = echelle();
        const droite = cadre.left + (z.rect.x + z.rect.w) * s + 14;
        let gauche = droite + 320 <= scene.right ? droite : cadre.left + z.rect.x * s - 334;
        gauche = borne(gauche, scene.left + 8, Math.max(scene.left + 8, scene.right - 330));
        const haut = borne(cadre.top + z.rect.y * s, scene.top + 8, Math.max(scene.top + 8, scene.bottom - pop.offsetHeight - 8));
        Object.assign(pop.style, { left: `${gauche}px`, top: `${haut}px` });
    }

    /* --- Pointeur : survol, sélection, glissement --- */
    let glissement = null;
    const surcouche = $('stSurcouche');

    function pointCanvas(e) {
        const r = $('stCadre').getBoundingClientRect();
        const s = r.width / M.FORMATS[etat.format].w;

        return { x: (e.clientX - r.left) / s, y: (e.clientY - r.top) / s, s };
    }

    surcouche.addEventListener('pointerdown', (e) => {
        if (e.target.closest('#stBarreEl, #stPopover')) { return; }
        const p = pointCanvas(e);
        const poignee = e.target.closest('.st-poignee');
        let z = poignee && selection ? zoneDe(selection) : trouver(p.x, p.y);
        if (!z) { selection = null; fermerPopover(); placerSelection(); if (etat.onglet === 'elements') { dessinerElements(); } return; }
        if (!poignee && selection !== z.id) { fermerPopover(); }
        selection = z.id;
        const l = libreDe(z.id);
        surcouche.setPointerCapture(e.pointerId);
        glissement = {
            id: z.id, mode: poignee ? 'taille' : (z.type === 'photo' && !z.libre ? 'cadrage' : 'deplacer'), x0: p.x, y0: p.y, bouge: false,
            dec0: (decalagesFormat()[z.id] || [0, 0]).slice(), l0: l ? clone(l) : null, photo0: z.slot && etat.photos[z.slot] ? clone(etat.photos[z.slot]) : null, zone: z,
        };
        placerSelection();
    });

    surcouche.addEventListener('pointermove', (e) => {
        const p = pointCanvas(e);
        if (!glissement) {
            const z = trouver(p.x, p.y);
            const survol = $('stSurvol');
            if (z && z.id !== selection) {
                const s = p.s;
                Object.assign(survol.style, { left: `${z.rect.x * s - 3}px`, top: `${z.rect.y * s - 3}px`, width: `${z.rect.w * s + 6}px`, height: `${z.rect.h * s + 6}px` });
                survol.hidden = false;
            } else { survol.hidden = true; }
            surcouche.style.cursor = z ? (z.id === selection ? (z.type === 'photo' && !z.libre ? 'grab' : 'move') : 'pointer') : 'default';

            return;
        }
        const g = glissement;
        const dx = p.x - g.x0, dy = p.y - g.y0;
        if (!g.bouge && Math.hypot(dx, dy) * p.s < 3) { return; }
        g.bouge = true;
        $('stSurvol').hidden = true;
        const F = M.FORMATS[etat.format];
        if (g.mode === 'deplacer') {
            if (g.l0) { const l = libreDe(g.id); l.x = borne(g.l0.x + dx / F.w, -0.2, 1.1); l.y = borne(g.l0.y + dy / F.h, -0.2, 1.1); } else { decalagesFormat()[g.id] = [Math.round(g.dec0[0] + dx), Math.round(g.dec0[1] + dy)]; }
        } else if (g.mode === 'taille' && g.l0) {
            const l = libreDe(g.id);
            if (l.type === 'texte' || l.type === 'pastille' || l.type === 'ligne') { l.w = borne(g.l0.w + dx / F.w, 0.05, 1.5); if (l.type !== 'ligne') { l.taille = borne((g.l0.taille || 0.04) * (1 + dy / (F.w * 0.4)), 0.012, 0.3); } } else if (l.type === 'logo') { l.w = borne(g.l0.w + dx / F.w, 0.04, 1.5); } else { l.w = borne(g.l0.w + dx / F.w, 0.03, 1.5); l.h = borne(g.l0.h + dy / F.w, 0.02, 1.5); }
        } else if (g.mode === 'cadrage' && g.photo0) {
            const ph = etat.photos[g.zone.slot], z = g.zone;
            if (ph && z.sw) {
                // la photo suit le doigt : on déplace le point focal de l'inverse du déplacement, en unités de la photo
                const ux = (dx * z.sw) / z.rw / z.iw, uy = (dy * z.sh) / z.rh / z.ih;
                ph.focus = [borne(g.photo0.focus[0] - ux, 0, 1), borne(g.photo0.focus[1] - uy, 0, 1)];
            }
        }
        planifierRendu();
    });

    const finGlissement = (e) => {
        if (!glissement) { return; }
        const g = glissement;
        glissement = null;
        try { surcouche.releasePointerCapture(e.pointerId); } catch (err) { /* déjà relâché */ }
        if (g.bouge) { marquerModifie(); dessinerContenu(); }
        if (etat.onglet === 'elements') { dessinerElements(); }
    };
    surcouche.addEventListener('pointerup', finGlissement);
    surcouche.addEventListener('pointercancel', finGlissement);
    surcouche.addEventListener('pointerleave', () => { $('stSurvol').hidden = true; });
    surcouche.addEventListener('dblclick', (e) => {
        const p = pointCanvas(e), z = trouver(p.x, p.y);
        if (!z) { return; }
        selection = z.id;
        if (z.type === 'photo' && !z.libre) { ouvrirMedias(z.slot); return; }
        if ((z.champs && z.champs.length) || libreDe(z.id)) { ouvrirPopover(); }
    });
    surcouche.addEventListener('wheel', (e) => {
        const p = pointCanvas(e), z = trouver(p.x, p.y);
        const cible = z && z.type === 'photo' && z.slot && etat.photos[z.slot];
        if (!cible) { return; }
        e.preventDefault();
        cible.zoom = borne(cible.zoom * (e.deltaY < 0 ? 1.06 : 1 / 1.06), 1, 3);
        marquerModifie(true, false);
        planifierRendu();
    }, { passive: false });

    document.addEventListener('keydown', (e) => {
        const enSaisie = e.target.closest && e.target.closest('input, textarea, select, [contenteditable="true"]');
        if (e.target.closest && e.target.closest('dialog[open]')) { return; }
        const mod = e.ctrlKey || e.metaKey;
        if (mod && !e.altKey && ['z', 'y', 'd'].includes(e.key.toLowerCase())) {
            if (enSaisie && e.key.toLowerCase() !== 'd') { return; }
            e.preventDefault();
            const k = e.key.toLowerCase();
            if (k === 'z' && !e.shiftKey) { historique.annuler(); } else if (k === 'y' || (k === 'z' && e.shiftKey)) { historique.retablir(); } else if (k === 'd') { dupliquerSelection(); }

            return;
        }
        if (enSaisie || !selection) { return; }
        if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); supprimerSelection(); } else if (e.key === 'Escape') { selection = null; fermerPopover(); placerSelection(); } else if (e.key.startsWith('Arrow')) {
            e.preventDefault();
            const pas = e.shiftKey ? 10 : 1, F = M.FORMATS[etat.format];
            const dx = { ArrowLeft: -pas, ArrowRight: pas }[e.key] || 0, dy = { ArrowUp: -pas, ArrowDown: pas }[e.key] || 0;
            const l = libreDe(selection);
            if (l) { l.x += dx / F.w; l.y += dy / F.h; } else { const d = decalagesFormat()[selection] || [0, 0]; decalagesFormat()[selection] = [d[0] + dx, d[1] + dy]; }
            marquerModifie(true, false);
            planifierRendu();
        }
    });
    $('stAnnuler').addEventListener('click', () => historique.annuler());
    $('stRetablir').addEventListener('click', () => historique.retablir());

    /* ---------- Mes visuels ---------- */
    function dessinerVisuels() {
        const zone = $('stVisuels');
        zone.replaceChildren(
            h('button', { type: 'button', class: 'st-btn st-btn--primaire st-btn--plein', onclick: () => { nouveau('accueil'); dessinerTout(); onglet('modeles'); } }, icone('bi-plus-lg'), 'Nouveau visuel'),
            etat.designs.length ? null : h('p', { class: 'st-aide' }, 'Vos visuels apparaîtront ici : ils s’enregistrent tout seuls dès que vous modifiez quelque chose.'),
            h('ul', { class: 'st-liste' }, etat.designs.map((d) => h('li', { class: `st-visuel${d.id === etat.id ? ' is-on' : ''}` },
                h('button', { type: 'button', class: 'st-visuel__ouvrir', onclick: () => ouvrirVisuel(d.id) },
                    d.thumb ? h('img', { src: d.thumb, alt: '' }) : h('span', { class: 'st-visuel__vide' }),
                    h('span', { class: 'st-visuel__texte' }, h('strong', {}, d.name), h('span', {}, `${(MODELES[d.template] || { nom: d.template }).nom} · ${d.updated}`))),
                h('div', { class: 'st-visuel__actions' },
                    h('button', { type: 'button', class: 'st-btn st-btn--icone st-btn--petit', title: 'Dupliquer', 'aria-label': `Dupliquer ${d.name}`, onclick: () => dupliquer(d.id) }, icone('bi-files')),
                    h('button', { type: 'button', class: 'st-btn st-btn--icone st-btn--petit', title: 'Supprimer', 'aria-label': `Supprimer ${d.name}`, onclick: () => supprimer(d) }, icone('bi-trash3')))))));
    }

    async function charger(id) {
        const d = (await api(`${boot.urls.design}/${id}`)).design;
        if (!MODELES[d.template]) { throw new Error('Ce modèle n’existe plus.'); }

        return d;
    }

    async function ouvrirVisuel(id) {
        if (sale) { await sauvegarder(); }
        try {
            const d = await charger(id);
            const m = MODELES[d.template];
            Object.assign(etat, { id: d.id, nom: d.name, modele: d.template, format: m.formats.includes(d.format) ? d.format : m.formats[0] });
            etat.champs = Object.assign(valeursInitiales(m), d.data.champs || {});
            etat.options = Object.assign(optionsVides(), d.data.options || {});
            etat.photos = {};
            Object.entries(d.data.photos || {}).forEach(([slot, p]) => { etat.photos[slot] = { media: p.media, url: p.url, w: p.width, h: p.height, focus: p.focus, zoom: p.zoom, libelle: p.alt || slot }; });
            compteurLibres = Math.max(0, ...etat.options.libres.map((l) => Number(String(l.id).replace(/\D/g, '')) || 0));
            env.photos = {};
            selection = null;
            sale = false;
            historique.vider();
            majEtat('Visuel ouvert');
            dessinerTout();
            onglet('contenu');
        } catch (e) { toast(e.message, 'error'); }
    }

    async function dupliquer(id) {
        try {
            const d = await charger(id);
            const photos = {};
            Object.entries(d.data.photos || {}).forEach(([slot, p]) => { photos[slot] = { media: p.media, focus: p.focus, zoom: p.zoom }; });
            await api(boot.urls.designs, 'POST', { name: `${d.name} (copie)`.slice(0, 120), template: d.template, format: d.format, data: { champs: d.data.champs, photos, options: d.data.options || optionsVides() } });
            etat.designs = (await api(boot.urls.designs)).designs;
            dessinerVisuels();
            toast('Visuel dupliqué.', 'success');
        } catch (e) { toast(e.message, 'error'); }
    }

    async function supprimer(d) {
        if (!window.confirm(`Supprimer « ${d.name} » ? Les photos restent dans la médiathèque.`)) { return; }
        try {
            await api(`${boot.urls.design}/${d.id}`, 'DELETE');
            if (etat.id === d.id) { etat.id = null; sale = false; }
            etat.designs = etat.designs.filter((x) => x.id !== d.id);
            dessinerVisuels();
        } catch (e) { toast(e.message, 'error'); }
    }

    /* ---------- Médiathèque ---------- */
    const med = { slot: null, page: 1, requete: '' };
    const dlg = $('stMedias');

    function ouvrirMedias(slot) {
        med.slot = slot; med.page = 1;
        $('stMediasGrille').replaceChildren();
        dlg.showModal();
        chargerMedias(true);
    }

    async function chargerMedias(reinit) {
        if (reinit) { med.page = 1; $('stMediasGrille').replaceChildren(); }
        $('stMediasEtat').textContent = 'Chargement…';
        try {
            const url = new URL(boot.urls.media, location.origin);
            url.search = new URLSearchParams({ q: med.requete, type: 'image', page: med.page }).toString();
            const d = await api(url.toString());
            d.data.forEach((a) => {
                $('stMediasGrille').append(h('button', { type: 'button', class: 'st-vignette', title: a.title, onclick: () => choisirMedia(a) }, h('img', { src: a.thumb, alt: a.alt || '', loading: 'lazy' }), h('span', {}, a.title)));
            });
            $('stMediasPlus').hidden = !d.has_more;
            $('stMediasEtat').textContent = d.data.length || med.page > 1 ? '' : 'Aucune photo : envoyez-en une.';
        } catch (e) { $('stMediasEtat').textContent = e.message; }
    }

    function poserPhoto(slot, a) {
        const ancien = etat.photos[slot];
        etat.photos[slot] = { media: a.id, url: a.full, thumb: a.thumb, w: a.width, h: a.height, focus: ancien ? ancien.focus : [0.5, 0.3], zoom: ancien ? ancien.zoom : 1, libelle: a.title || a.alt || slot };
        marquerModifie();
        dessinerContenu();
        dessinerElements();
        planifierRendu();
    }

    function choisirMedia(a) {
        if (!a.full) { toast('Ce fichier n’est pas une image.', 'error'); return; }
        dlg.close();
        poserPhoto(med.slot, a);
        if (popoverOuvert) { ouvrirPopover(); }
    }

    async function envoyerFichier(fichier, titre = null) {
        const corps = new FormData();
        const nom = (titre || fichier.name || 'photo').replace(/\.[^.]+$/, '');
        corps.append('files[]', fichier, fichier.name || 'photo.jpg');
        corps.append('alts[0]', nom.length >= 3 ? nom : `Photo ${nom}`);

        return (await api(boot.urls.upload, 'POST', corps)).data[0];
    }

    $('stMediasFermer').addEventListener('click', () => dlg.close());
    dlg.addEventListener('click', (e) => { if (e.target === dlg) { dlg.close(); } });
    $('stMediasPlus').addEventListener('click', () => { med.page++; chargerMedias(false); });
    let minuterieRecherche = 0;
    $('stMediasRecherche').addEventListener('input', (e) => { clearTimeout(minuterieRecherche); minuterieRecherche = setTimeout(() => { med.requete = e.target.value.trim(); chargerMedias(true); }, 250); });
    $('stMediasFichier').addEventListener('change', async (e) => {
        const f = e.target.files[0]; e.target.value = '';
        if (!f) { return; }
        $('stMediasEtat').textContent = 'Envoi de la photo…';
        try { const a = await envoyerFichier(f); dlg.close(); poserPhoto(med.slot, a); } catch (err) { $('stMediasEtat').textContent = err.message; }
    });

    /* ---------- Pont avec la retouche photo (même site, même session) ---------- */
    const ponts = new Map();
    function ouvrirRetouche(slot) {
        const p = etat.photos[slot];
        if (!p) { return; }
        const jeton = `st${Array.from(crypto.getRandomValues(new Uint32Array(2)), (n) => n.toString(36)).join('')}`;
        const fenetre = window.open(`${boot.urls.retouche}?studio=${jeton}`, '_blank');
        if (!fenetre) { toast('Le navigateur a bloqué l’ouverture de la retouche photo : autorisez les fenêtres de ce site, puis réessayez.', 'error', 9000); return; }
        ponts.set(jeton, { slot, fenetre });
        toast('La retouche photo s’ouvre dans un autre onglet. « Renvoyer au Studio UAZ » y remet la photo ici.');
    }

    window.addEventListener('message', async (e) => {
        const d = e.data;
        if (e.origin !== location.origin || !d || typeof d !== 'object' || !ponts.has(d.jeton)) { return; }
        const pont = ponts.get(d.jeton);
        if (e.source !== pont.fenetre) { return; }
        const p = etat.photos[pont.slot];
        if (d.type === 'studio-uaz:pret') {
            if (!p) { return; }
            try {
                const blob = await (await fetch(p.url, { credentials: 'same-origin' })).blob();
                pont.fenetre.postMessage({ type: 'studio-uaz:photo', jeton: d.jeton, blob, nom: `${slug(p.libelle)}.jpg`, emplacement: pont.slot, libelle: p.libelle }, location.origin);
            } catch (err) { toast('La photo n’a pas pu être envoyée à la retouche.', 'error'); }
        } else if (d.type === 'studio-uaz:retour' && d.blob instanceof Blob) {
            try {
                const a = await envoyerFichier(new File([d.blob], String(d.nom || 'photo-retouchee.jpg'), { type: d.blob.type || 'image/jpeg' }), `${p ? p.libelle : 'Photo'} (retouchée)`);
                poserPhoto(pont.slot, a);
                pont.fenetre.postMessage({ type: 'studio-uaz:recu', jeton: d.jeton }, location.origin);
                toast('Photo retouchée reçue.', 'success');
            } catch (err) { toast(err.message, 'error'); }
        }
    });

    /* ---------- Export ---------- */
    function nomFichier(format, ext) {
        const c = etat.champs;
        const titre = [c.titre1 || c.titre || c.ligne1 || c.prenom || c.texte || c.etiquette, c.titre2 || c.accent || c.ligne2 || c.nom].filter(Boolean).join(' ');
        const date = new Date().toISOString().slice(0, 10);

        return `UAZ_${modele().code}_${date}_${slug(titre)}_${format.replace(':', 'x')}.${ext}`;
    }

    async function fabriquer(format, type, ech) {
        await chargerPhotos();
        const c = document.createElement('canvas');
        const res = M.rendre(c, modele(), etat.champs, format, { photos: env.photos, logos: env.logos, cache: new Map(), apercu: false, echelle: ech, options: etat.options });
        const blob = await new Promise((ok) => c.toBlob(ok, type, type === 'image/jpeg' ? 0.93 : undefined));
        if (!blob) { throw new Error('Le navigateur n’a pas pu fabriquer l’image (mémoire insuffisante ?). Essayez sans « ×2 ».'); }

        return { blob, alertes: res.alertes, w: c.width, h: c.height };
    }

    function telecharger(blob, nom) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob); a.download = nom;
        document.body.append(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 5000);
    }

    async function exporter(sorte) {
        const erreurs = dernieresAlertes.filter((a) => a.niveau === 'erreur');
        if (erreurs.length) { toast(`Export impossible : ${erreurs[0].message}`, 'error', 7000); return; }
        majEtat('Export en cours…');
        try {
            if (sorte === 'zip') {
                const fichiers = [];
                for (const f of modele().formats) {
                    const r = await fabriquer(f, 'image/png', 1);
                    if (r.alertes.some((a) => a.niveau === 'erreur')) { throw new Error(`Le format ${f} déborde : ${r.alertes[0].message}`); }
                    fichiers.push({ nom: nomFichier(f, 'png'), blob: r.blob });
                }
                telecharger(await window.StudioZip.zip(fichiers), `UAZ_${modele().code}_${slug(etat.nom)}.zip`);
                toast(`${fichiers.length} formats exportés dans un fichier ZIP.`, 'success');
            } else if (sorte === 'media') {
                const r = await fabriquer(etat.format, 'image/png', 1);
                const a = await envoyerFichier(new File([r.blob], nomFichier(etat.format, 'png'), { type: 'image/png' }), `Visuel ${etat.nom}`);
                toast(`« ${a.title} » est dans la médiathèque : choisissez-la dans une newsletter (bloc Image) ou une page.`, 'success', 8000);
            } else {
                const [genre, ech] = sorte.split('-');
                const type = genre === 'jpg' ? 'image/jpeg' : 'image/png';
                const r = await fabriquer(etat.format, type, Number(ech));
                telecharger(r.blob, nomFichier(etat.format, genre === 'jpg' ? 'jpg' : 'png'));
                toast(`Exporté : ${r.w} × ${r.h} px.`, 'success');
            }
        } catch (e) { toast(e.message, 'error', 8000); } finally { majEtat(sale ? 'Modifications non enregistrées…' : 'Prêt'); }
    }

    $('stExporter').addEventListener('click', (e) => {
        e.stopPropagation();
        const ouvert = !$('stMenuExport').hidden;
        $('stMenuExport').hidden = ouvert; $('stExporter').setAttribute('aria-expanded', String(!ouvert));
    });
    document.addEventListener('click', () => { $('stMenuExport').hidden = true; $('stExporter').setAttribute('aria-expanded', 'false'); });
    $('stMenuExport').querySelectorAll('[data-export]').forEach((b) => b.addEventListener('click', () => exporter(b.dataset.export)));

    /* ---------- Divers ---------- */
    $('stNom').addEventListener('input', (e) => { etat.nom = e.target.value; marquerModifie(false); });
    $('stZone').addEventListener('change', planifierRendu);
    if (window.ResizeObserver) { new ResizeObserver(planifierRendu).observe($('stToile')); } else { window.addEventListener('resize', planifierRendu); }
    window.addEventListener('beforeunload', (e) => { if (sale) { sauvegarder(); e.preventDefault(); e.returnValue = ''; } });

    /* Largeur du panneau : glisser la poignée, ou les flèches ; double-clic = taille d'origine. */
    (() => {
        const poignee = $('stResizer'), panneau = poignee.parentElement;
        const STOCKAGE = 'st-panel-width', MIN = 280;
        const original = panneau.getBoundingClientRect().width || 380;
        const limite = () => Math.max(MIN + 60, Math.min(760, window.innerWidth * 0.55));
        const appliquer = (largeur, sauver = true) => {
            const v = Math.round(Math.max(MIN, Math.min(limite(), largeur)));
            document.body.style.setProperty('--st-panneau', `${v}px`);
            if (sauver) { try { localStorage.setItem(STOCKAGE, String(v)); } catch (e) { /* ignoré */ } }
        };
        try { const s = Number(localStorage.getItem(STOCKAGE)); if (s) { appliquer(s, false); } } catch (e) { /* ignoré */ }
        poignee.addEventListener('pointerdown', (e) => { e.preventDefault(); poignee.setPointerCapture(e.pointerId); document.body.classList.add('is-resizing'); });
        poignee.addEventListener('pointermove', (e) => { if (poignee.hasPointerCapture(e.pointerId)) { appliquer(e.clientX, false); } });
        const fin = (e) => { if (!poignee.hasPointerCapture(e.pointerId)) { return; } poignee.releasePointerCapture(e.pointerId); document.body.classList.remove('is-resizing'); appliquer(panneau.getBoundingClientRect().width); planifierRendu(); };
        poignee.addEventListener('pointerup', fin); poignee.addEventListener('pointercancel', fin);
        poignee.addEventListener('dblclick', () => { appliquer(original); planifierRendu(); });
        poignee.addEventListener('keydown', (e) => {
            const pas = e.shiftKey ? 60 : 20, now = panneau.getBoundingClientRect().width;
            if (e.key === 'ArrowLeft') { e.preventDefault(); appliquer(now - pas); planifierRendu(); } else if (e.key === 'ArrowRight') { e.preventDefault(); appliquer(now + pas); planifierRendu(); }
        });
    })();

    /* ---------- Démarrage ---------- */
    (async () => {
        majEtat('Chargement des polices…');
        await M.chargerPolices(boot.urls.fonts);
        env.logos.sceau = await M.chargerImage(boot.urls.seal);
        env.logos.theme = await M.chargerImage(boot.urls.themeLogo);
        nouveau('accueil');
        dessinerTout();
        majEtat('Prêt');
        window.StudioEditeur = { etat, env, exporter, fabriquer, choisirModele, ouvrirVisuel, sauvegarder, dessiner, planifierRendu, ajouter, selectionner, supprimerSelection, historique, get zones() { return zones; }, get selection() { return selection; } };
    })();
})();
