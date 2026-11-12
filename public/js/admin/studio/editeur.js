/* =============================================================================
   Studio UAZ — l'éditeur (l'interface).

   Panneau de gauche : Modèles (choisir), Contenu (textes, photos, cadrage), Mes visuels (reprendre, dupliquer, supprimer). Au centre : l'aperçu, un onglet
   par format. En haut : le nom, l'état de l'enregistrement (automatique), la retouche photo et l'export (PNG, PNG ×2, JPEG, ZIP des quatre formats).
   Le dessin est celui de moteur.js, à l'échelle de l'écran pour l'aperçu et à la taille réelle (ou ×2) pour l'export.
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
    const attendre = (ms) => new Promise((ok) => setTimeout(ok, ms));
    const slug = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40) || 'visuel';

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
    const etat = { id: null, nom: '', modele: 'accueil', format: '16:9', champs: {}, photos: {}, onglet: 'modeles', designs: boot.designs || [] };
    const env = { photos: {}, logos: {}, cache: new Map(), apercu: true, echelle: 1 };
    let sale = false, minuterieSauvegarde = 0, jetonRendu = 0, dernieresAlertes = [];
    const modele = () => MODELES[etat.modele];

    function nomParDefaut(m) {
        return `${m.nom} — ${new Date().toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })}`;
    }

    function nouveau(idModele = etat.modele) {
        const m = MODELES[idModele];
        Object.assign(etat, { id: null, modele: idModele, format: m.formats.includes(etat.format) ? etat.format : m.formats[0], champs: valeursInitiales(m), photos: {} });
        etat.nom = nomParDefaut(m);
        env.photos = {};
        sale = false;
    }

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

    function dimensionsAffichage() {
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
        const { s, w, h } = dimensionsAffichage();
        const canvas = $('stCanvas');
        const cadre = $('stCadre');
        cadre.style.width = `${w}px`; cadre.style.height = `${h}px`;
        const echelle = Math.min(1, Math.max(0.35, (w * (window.devicePixelRatio || 1)) / f.w));
        const res = M.rendre(canvas, m, etat.champs, etat.format, { photos: env.photos, logos: env.logos, cache: env.cache, apercu: true, echelle });
        canvas.style.width = '100%'; canvas.style.height = '100%';
        dernieresAlertes = res.alertes;
        $('stDims').textContent = `${f.w} × ${f.h} px · ${Math.round(s * 100)} %`;
        const liste = $('stAlertes');
        liste.replaceChildren(...res.alertes.map((a) => h('li', { class: `st-alerte st-alerte--${a.niveau}` }, h('i', { class: `bi ${a.niveau === 'erreur' ? 'bi-exclamation-octagon' : 'bi-exclamation-triangle'}`, 'aria-hidden': 'true' }), a.message)));
        const story = etat.format === '9:16';
        $('stZoneBloc').hidden = !story;
        $('stZoneHaut').hidden = $('stZoneBas').hidden = !(story && $('stZone').checked);
    }

    /* ---------- Enregistrement automatique ---------- */
    function marquerModifie() {
        sale = true;
        majEtat('Modifications non enregistrées…');
        clearTimeout(minuterieSauvegarde);
        minuterieSauvegarde = setTimeout(sauvegarder, 1400);
    }

    function majEtat(texte) { $('stEtat').textContent = texte; }

    function donneesEnvoi() {
        const photos = {};
        Object.entries(etat.photos).forEach(([slot, p]) => { photos[slot] = { media: p.media, focus: p.focus, zoom: p.zoom }; });

        return { name: etat.nom || nomParDefaut(modele()), template: etat.modele, format: etat.format, data: { champs: etat.champs, photos } };
    }

    async function vignette() {
        const c = document.createElement('canvas');
        M.rendre(c, modele(), etat.champs, modele().formats[0], { photos: env.photos, logos: env.logos, cache: new Map(), apercu: false, echelle: 0.2 });
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
            const l = await api(boot.urls.designs);
            etat.designs = l.designs;
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
    }
    document.querySelectorAll('.st-onglet').forEach((b) => b.addEventListener('click', () => onglet(b.dataset.onglet)));

    /* ---------- Modèles ---------- */
    function dessinerModeles() {
        const zone = $('stModeles');
        zone.replaceChildren(h('p', { class: 'st-aide' }, 'Choisissez le style de votre visuel. Vos textes et vos photos restent quand vous changez de modèle.'));
        ORDRE.forEach((id) => {
            const m = MODELES[id];
            const c = document.createElement('canvas');
            c.className = 'st-modele__vue';
            M.rendre(c, m, valeursInitiales(m), m.formats[0], { photos: {}, logos: env.logos, cache: new Map(), apercu: false, echelle: 0.2 });
            const carte = h('button', { type: 'button', class: `st-modele${etat.modele === id ? ' is-on' : ''}`, 'aria-pressed': String(etat.modele === id), onclick: () => choisirModele(id) },
                c,
                h('span', { class: 'st-modele__nom' }, h('i', { class: `bi ${m.icone}`, 'aria-hidden': 'true' }), m.nom),
                h('span', { class: 'st-modele__desc' }, m.description));
            zone.append(carte);
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
        const slotsNeufs = new Set(neuf.images.map((i) => i.id));
        Object.keys(etat.photos).forEach((s) => { if (!slotsNeufs.has(s)) { delete etat.photos[s]; } });
        marquerModifie();
        dessinerFormats();
        dessinerModeles();
        dessinerContenu();
        onglet('contenu');
        planifierRendu();
    }

    /* ---------- Contenu ---------- */
    function champ(c) {
        const valeur = etat.champs[c.id];
        const enregistre = (v) => { etat.champs[c.id] = v; marquerModifie(); planifierRendu(); };
        if (c.type === 'interrupteur') {
            return h('label', { class: 'st-interrupteur' }, h('input', { type: 'checkbox', checked: !!valeur, onchange: (e) => { enregistre(e.target.checked); } }), h('span', {}, c.libelle));
        }
        if (c.type === 'choix') {
            return h('div', { class: 'st-champ' }, h('span', { class: 'st-libelle' }, c.libelle),
                h('div', { class: 'st-segment', role: 'group' }, c.options.map((o) => h('button', { type: 'button', 'aria-pressed': String(String(valeur) === o.v), onclick: () => { enregistre(o.v); dessinerContenu(); } }, o.l))));
        }
        const compteur = h('em', {}, `${String(valeur ?? '').length}/${c.max}`);
        const saisie = c.type === 'longtexte'
            ? h('textarea', { rows: '3', maxlength: String(c.max), value: valeur ?? '', oninput: (e) => { compteur.textContent = `${e.target.value.length}/${c.max}`; enregistre(e.target.value); } })
            : h('input', { type: 'text', maxlength: String(c.max), value: valeur ?? '', autocomplete: 'off', oninput: (e) => { compteur.textContent = `${e.target.value.length}/${c.max}`; enregistre(e.target.value); } });

        return h('label', { class: 'st-champ' }, h('span', { class: 'st-libelle' }, c.libelle, compteur), saisie);
    }

    function emplacementPhoto(im) {
        const p = etat.photos[im.id];
        const curseur = (nom, valeur, min, max, pas, fn) => h('label', { class: 'st-curseur' }, h('span', {}, nom),
            h('input', { type: 'range', min: String(min), max: String(max), step: String(pas), value: String(valeur), oninput: (e) => { fn(parseFloat(e.target.value)); marquerModifie(); planifierRendu(); } }));
        const bloc = h('div', { class: 'st-photo' },
            h('div', { class: 'st-photo__haut' },
                h('div', { class: 'st-photo__vue' }, p ? h('img', { src: p.thumb || p.url, alt: '' }) : h('i', { class: 'bi bi-image', 'aria-hidden': 'true' })),
                h('div', { class: 'st-photo__corps' },
                    h('strong', {}, im.libelle),
                    h('div', { class: 'st-photo__actions' },
                        h('button', { type: 'button', class: 'st-btn st-btn--petit st-btn--primaire', onclick: () => ouvrirMedias(im.id) }, h('i', { class: 'bi bi-images', 'aria-hidden': 'true' }), p ? 'Changer' : 'Choisir'),
                        p && h('button', { type: 'button', class: 'st-btn st-btn--petit', onclick: () => ouvrirRetouche(im.id), title: 'Ouvre la photo dans la retouche photo ; « Renvoyer au Studio UAZ » la remet ici.' }, h('i', { class: 'bi bi-sliders', 'aria-hidden': 'true' }), 'Retoucher'),
                        p && h('button', { type: 'button', class: 'st-btn st-btn--petit', onclick: () => { delete etat.photos[im.id]; marquerModifie(); dessinerContenu(); planifierRendu(); }, 'aria-label': `Retirer ${im.libelle}` }, h('i', { class: 'bi bi-x-lg', 'aria-hidden': 'true' })))),
            ),
            p && h('div', { class: 'st-photo__reglages' },
                curseur('Cadrage ↔', p.focus[0], 0, 1, 0.01, (v) => { p.focus = [v, p.focus[1]]; }),
                curseur('Cadrage ↕', p.focus[1], 0, 1, 0.01, (v) => { p.focus = [p.focus[0], v]; }),
                curseur('Zoom', p.zoom, 1, 3, 0.05, (v) => { p.zoom = v; })));

        return bloc;
    }

    function dessinerContenu() {
        const m = modele();
        const zone = $('stContenu');
        const garde = zone.scrollTop;
        zone.replaceChildren(
            h('div', { class: 'st-entete' }, h('i', { class: `bi ${m.icone}`, 'aria-hidden': 'true' }), h('div', {}, h('strong', {}, m.nom), h('span', {}, m.description))),
            m.variantes.length ? h('div', { class: 'st-variantes' }, h('span', { class: 'st-libelle' }, 'Modèles rapides'),
                h('div', {}, m.variantes.map((v) => h('button', { type: 'button', class: 'st-puce', onclick: () => { Object.assign(etat.champs, v.champs); marquerModifie(); dessinerContenu(); planifierRendu(); } }, v.nom)))) : null,
            h('section', { class: 'st-groupe' }, h('h3', {}, 'Textes'), m.champs.map(champ)),
            h('section', { class: 'st-groupe' }, h('h3', {}, 'Photos'), m.images.filter((i) => !i.visible || i.visible(etat.champs)).map(emplacementPhoto)),
            h('button', { type: 'button', class: 'st-lien', onclick: () => { etat.champs = valeursInitiales(m); marquerModifie(); dessinerContenu(); planifierRendu(); } }, h('i', { class: 'bi bi-arrow-counterclockwise', 'aria-hidden': 'true' }), ' Remettre les textes d’exemple'),
        );
        zone.scrollTop = garde;
        $('stNom').value = etat.nom;
    }

    /* ---------- Formats ---------- */
    function dessinerFormats() {
        const m = modele();
        $('stFormats').replaceChildren(...m.formats.map((f) => h('button', { type: 'button', role: 'tab', class: `st-format${etat.format === f ? ' is-on' : ''}`, 'aria-selected': String(etat.format === f), title: M.FORMATS[f].usage, onclick: () => { etat.format = f; marquerModifie(); dessinerFormats(); planifierRendu(); } },
            h('span', { class: 'st-format__forme', style: `--a:${M.FORMATS[f].w};--b:${M.FORMATS[f].h}`, 'aria-hidden': 'true' }), h('span', {}, M.FORMATS[f].nom))));
    }

    /* ---------- Mes visuels ---------- */
    function dessinerVisuels() {
        const zone = $('stVisuels');
        zone.replaceChildren(
            h('button', { type: 'button', class: 'st-btn st-btn--primaire st-btn--plein', onclick: () => { nouveau('accueil'); dessinerTout(); onglet('modeles'); } }, h('i', { class: 'bi bi-plus-lg', 'aria-hidden': 'true' }), 'Nouveau visuel'),
            etat.designs.length ? null : h('p', { class: 'st-aide' }, 'Vos visuels apparaîtront ici : ils s’enregistrent tout seuls dès que vous modifiez quelque chose.'),
            h('ul', { class: 'st-liste' }, etat.designs.map((d) => h('li', { class: `st-visuel${d.id === etat.id ? ' is-on' : ''}` },
                h('button', { type: 'button', class: 'st-visuel__ouvrir', onclick: () => ouvrirVisuel(d.id) },
                    d.thumb ? h('img', { src: d.thumb, alt: '' }) : h('span', { class: 'st-visuel__vide' }),
                    h('span', { class: 'st-visuel__texte' }, h('strong', {}, d.name), h('span', {}, `${(MODELES[d.template] || { nom: d.template }).nom} · ${d.updated}`))),
                h('div', { class: 'st-visuel__actions' },
                    h('button', { type: 'button', class: 'st-btn st-btn--icone st-btn--petit', title: 'Dupliquer', 'aria-label': `Dupliquer ${d.name}`, onclick: () => dupliquer(d.id) }, h('i', { class: 'bi bi-files', 'aria-hidden': 'true' })),
                    h('button', { type: 'button', class: 'st-btn st-btn--icone st-btn--petit', title: 'Supprimer', 'aria-label': `Supprimer ${d.name}`, onclick: () => supprimer(d) }, h('i', { class: 'bi bi-trash3', 'aria-hidden': 'true' })))))));
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
            etat.photos = {};
            Object.entries(d.data.photos || {}).forEach(([slot, p]) => { etat.photos[slot] = { media: p.media, url: p.url, w: p.width, h: p.height, focus: p.focus, zoom: p.zoom, libelle: p.alt || slot }; });
            env.photos = {};
            sale = false;
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
            await api(boot.urls.designs, 'POST', { name: `${d.name} (copie)`.slice(0, 120), template: d.template, format: d.format, data: { champs: d.data.champs, photos } });
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
    const med = { slot: null, page: 1, requete: '', fichier: null };
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

    function choisirMedia(a) {
        if (!a.full) { toast('Ce fichier n’est pas une image.', 'error'); return; }
        const ancien = etat.photos[med.slot];
        etat.photos[med.slot] = { media: a.id, url: a.full, thumb: a.thumb, w: a.width, h: a.height, focus: ancien ? ancien.focus : [0.5, 0.3], zoom: ancien ? ancien.zoom : 1, libelle: a.title || a.alt || med.slot };
        dlg.close();
        marquerModifie();
        dessinerContenu();
        planifierRendu();
    }

    async function envoyerFichier(fichier, slot, titre = null) {
        const corps = new FormData();
        const nom = (titre || fichier.name || 'photo').replace(/\.[^.]+$/, '');
        corps.append('files[]', fichier, fichier.name || 'photo.jpg');
        corps.append('alts[0]', nom.length >= 3 ? nom : `Photo ${nom}`);
        const d = await api(boot.urls.upload, 'POST', corps);
        const a = d.data[0];
        const ancien = etat.photos[slot];
        etat.photos[slot] = { media: a.id, url: a.full, thumb: a.thumb, w: a.width, h: a.height, focus: ancien ? ancien.focus : [0.5, 0.3], zoom: ancien ? ancien.zoom : 1, libelle: a.title || a.alt || slot };
        marquerModifie();
        dessinerContenu();
        planifierRendu();
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
        try { await envoyerFichier(f, med.slot); dlg.close(); } catch (err) { $('stMediasEtat').textContent = err.message; }
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
        const im = modele().images.find((i) => i.id === pont.slot);
        const p = etat.photos[pont.slot];
        if (d.type === 'studio-uaz:pret') {
            if (!im || !p) { return; }
            try {
                const blob = await (await fetch(p.url, { credentials: 'same-origin' })).blob();
                pont.fenetre.postMessage({ type: 'studio-uaz:photo', jeton: d.jeton, blob, nom: `${slug(p.libelle)}.jpg`, emplacement: im.id, libelle: im.libelle }, location.origin);
            } catch (err) { toast('La photo n’a pas pu être envoyée à la retouche.', 'error'); }
        } else if (d.type === 'studio-uaz:retour' && d.blob instanceof Blob) {
            try {
                await envoyerFichier(new File([d.blob], String(d.nom || 'photo-retouchee.jpg'), { type: d.blob.type || 'image/jpeg' }), pont.slot, `${p ? p.libelle : 'Photo'} (retouchée)`);
                pont.fenetre.postMessage({ type: 'studio-uaz:recu', jeton: d.jeton }, location.origin);
                toast('Photo retouchée reçue.', 'success');
            } catch (err) { toast(err.message, 'error'); }
        }
    });

    /* ---------- Export ---------- */
    function nomFichier(format, ext) {
        const c = etat.champs;
        const titre = [c.titre1 || c.titre || c.prenom || c.texte || c.etiquette, c.titre2 || c.accent || c.nom].filter(Boolean).join(' ');
        const date = new Date().toISOString().slice(0, 10);

        return `UAZ_${modele().code}_${date}_${slug(titre)}_${format.replace(':', 'x')}.${ext}`;
    }

    async function fabriquer(format, type, echelle) {
        await chargerPhotos();
        const c = document.createElement('canvas');
        const res = M.rendre(c, modele(), etat.champs, format, { photos: env.photos, logos: env.logos, cache: new Map(), apercu: false, echelle });
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
            } else {
                const [genre, echelle] = sorte.split('-');
                const type = genre === 'jpg' ? 'image/jpeg' : 'image/png';
                const r = await fabriquer(etat.format, type, Number(echelle));
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
    $('stNom').addEventListener('input', (e) => { etat.nom = e.target.value; marquerModifie(); });
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

    function dessinerTout() {
        dessinerModeles();
        dessinerFormats();
        dessinerContenu();
        $('stNom').value = etat.nom;
        planifierRendu();
    }

    /* ---------- Démarrage ---------- */
    (async () => {
        majEtat('Chargement des polices…');
        await M.chargerPolices(boot.urls.fonts);
        env.logos.sceau = await M.chargerImage(boot.urls.seal);
        env.logos.theme = await M.chargerImage(boot.urls.themeLogo);
        nouveau('accueil');
        dessinerTout();
        majEtat('Prêt');
        window.StudioEditeur = { etat, env, exporter, fabriquer, choisirModele, ouvrirVisuel, sauvegarder, dessiner, planifierRendu };
    })();
})();
