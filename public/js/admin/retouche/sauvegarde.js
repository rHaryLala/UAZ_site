/* =============================================================================
   Retouche photo — sauvegarde automatique — src/retouche-photo.php

   L'atelier se retrouve tel qu'on l'a quitte, sur cet appareil comme sur un
   autre apres connexion : photos ouvertes, reglages, historique, masques,
   detourage et corrections au pinceau, images de fond, photo affichee,
   options d'export, outils.

   Deux etages :
   - le navigateur (IndexedDB), ou l'atelier lit et ecrit : rapide, et le
     travail continue sans connexion. Une base par compte
     (« retouche-photo-<compte> ») : un autre compte ouvert dans le meme
     navigateur ne voit pas ces photos ;
   - le serveur de l'universite (app/retouche-photo.api.php), qui en garde une
     copie pour le compte (demande du 13/10/2026 : retrouver son travail sur
     tout appareil). Chaque ecriture du navigateur entre dans une file
     d'envoi (magasin « envois », gardee meme si la page se ferme) ; au
     demarrage, la synchronisation recoit ce que le serveur a de plus recent
     et envoie ce qu'il n'a pas. Le plus recent l'emporte (majLe) ; une
     suppression ou un « Tout fermer » fait sur un appareil vaut pour tous.

   Magasins :
   - fichiers : la photo d'origine (Blob), ecrite une fois a l'ouverture ;
   - photos   : nom, reglages, historique des reglages, vignette, etats ;
   - masques  : corrections au pinceau du detourage (donnees, Uint8Array) et
                pinceau de chaque masque (calques, { id: Uint8Array }), ecrits
                quand ils changent ;
   - fonds    : images de fond choisies (Blob) ;
   - detourages : le detourage de chaque photo (cartes de la personne, des
                cheveux, de la peau, des vetements, compressees : environ
                250 Ko), pour ne pas le refaire en revenant (une vingtaine de
                secondes) ;
   - atelier  : ordre des photos, photo affichee, options d'export, outils
                (pinceau, masque en cours, volets ouverts) ;
   - envois   : la file d'envoi au serveur (ecrire, supprimer, vider).
   ========================================================================== */
(function () {
	'use strict';

	const VERSION = 3;   // 2 : magasin detourages ; 3 : magasin envois (13/10/2026)
	const MAGASINS = { fichiers: 'id', photos: 'id', masques: 'id', fonds: 'id', detourages: 'id', atelier: 'cle' };
	const DONNEES = Object.keys(MAGASINS);
	// les petits d'abord : sur un autre appareil, l'atelier et les vignettes arrivent avant les photos d'origine
	const ORDRE = ['atelier', 'photos', 'masques', 'detourages', 'fonds', 'fichiers'];
	const MORCEAU = 512 * 1024;   // par envoi : la limite de PHP en production est de 2 Mo

	const compresse = typeof CompressionStream !== 'undefined' && typeof DecompressionStream !== 'undefined';
	const gz = (blob) => new Response(blob.stream().pipeThrough(new CompressionStream('gzip'))).blob();
	const degz = (blob) => new Response(blob.stream().pipeThrough(new DecompressionStream('gzip'))).arrayBuffer();

	function promesse(requete) {
		return new Promise((ok, ko) => { requete.onsuccess = () => ok(requete.result); requete.onerror = () => ko(requete.error); });
	}

	/* Identifiant d'une photo, unique d'un appareil a l'autre (les photos de
	   deux ordinateurs se retrouvent sur le serveur sans se confondre). */
	let dernierId = 0;
	function nouvelId() {
		dernierId = Math.max(Date.now() * 100 + Math.floor(Math.random() * 100), dernierId + 1);
		return dernierId;
	}

	/* --- Enregistrement <-> JSON et donnees binaires ------------------------- */
	/* Les Blob et Uint8Array d'un enregistrement deviennent des « parts »
	   envoyees a part ; le JSON garde leur place : { $part, $type }. Les
	   Uint8Array (pinceau) partent compresses : ils sont presque uniformes. */
	async function decouper(rec) {
		const parts = [];
		const marcher = (v) => {
			if (v instanceof Blob) {
				const nom = 'p' + parts.length;
				parts.push({ nom, blob: v });
				return { $part: nom, $type: 'blob', mime: v.type || '', nomFichier: v.name || null };
			}
			if (v instanceof Uint8Array) {
				const nom = 'p' + parts.length;
				parts.push({ nom, u8: v });
				return { $part: nom, $type: 'u8', gzip: compresse };
			}
			if (Array.isArray(v)) { return v.map(marcher); }
			if (v && typeof v === 'object') { const o = {}; Object.keys(v).forEach((k) => { o[k] = marcher(v[k]); }); return o; }
			return v;
		};
		const json = marcher(rec);
		for (const p of parts) {
			if (p.u8) { p.blob = compresse ? await gz(new Blob([p.u8])) : new Blob([p.u8]); }
		}
		return { json, parts };
	}
	async function recoudre(json, lirePart) {
		const marcher = async (v) => {
			if (v && typeof v === 'object' && !Array.isArray(v) && typeof v.$part === 'string') {
				const blob = await lirePart(v.$part);
				if (v.$type === 'u8') { return new Uint8Array(v.gzip ? await degz(blob) : await blob.arrayBuffer()); }
				const b = new Blob([blob], { type: v.mime || '' });
				return v.nomFichier ? new File([b], v.nomFichier, { type: v.mime || '' }) : b;
			}
			if (Array.isArray(v)) { const o = []; for (const x of v) { o.push(await marcher(x)); } return o; }
			if (v && typeof v === 'object') { const o = {}; for (const k of Object.keys(v)) { o[k] = await marcher(v[k]); } return o; }
			return v;
		};
		return marcher(json);
	}

	/** Ouvre la base du compte ; null si IndexedDB est indisponible.
	    options.surBloquee : un autre onglet garde l'ancienne version de la base
	    ouverte (la mise a jour attend qu'il se ferme) ; options.serveur :
	    l'adresse de app/retouche-photo.api.php ; options.surEnvoi({ etat,
	    attente, message }) : etat de la copie du serveur (envoi, ok, horsligne,
	    session, plein). */
	async function ouvrir(compte, options) {
		const o = typeof options === 'function' ? { surBloquee: options } : (options || {});
		if (!window.indexedDB) { return null; }
		const ouverture = indexedDB.open('retouche-photo-' + (compte || 'anonyme'), VERSION);
		ouverture.onupgradeneeded = () => {
			const db = ouverture.result;
			Object.keys(MAGASINS).forEach((nom) => {
				if (!db.objectStoreNames.contains(nom)) { db.createObjectStore(nom, { keyPath: MAGASINS[nom] }); }
			});
			if (!db.objectStoreNames.contains('envois')) { db.createObjectStore('envois', { keyPath: 'cle' }); }
		};
		ouverture.onblocked = () => { if (typeof o.surBloquee === 'function') { o.surBloquee(); } };
		let db;
		try { db = await promesse(ouverture); } catch (e) { console.warn('Sauvegarde indisponible', e); return null; }
		// une version plus recente ouverte ailleurs : cet onglet lui laisse la place
		db.onversionchange = () => { db.close(); };
		// demande au navigateur de ne pas effacer cette base pour faire de la place
		try { if (navigator.storage && navigator.storage.persist) { navigator.storage.persist(); } } catch (e) { /* facultatif */ }

		const url = o.serveur || null;
		const cleDe = (m, e) => e[MAGASINS[m]];
		const signaler = (etat, attente, message) => { if (typeof o.surEnvoi === 'function') { o.surEnvoi({ etat, attente: attente || 0, message: message || '' }); } };

		/* Une ecriture groupee : { magasin: [enregistrements] } et suppressions.
		   Faite par l'atelier, elle date chaque enregistrement (majLe) et entre
		   dans la file d'envoi ; venue du serveur (opts.depuisServeur), non. */
		function ecrire(ajouts, suppressions, opts) {
			const local = !(opts && opts.depuisServeur);
			const noms = new Set(Object.keys(ajouts || {}).concat(Object.keys(suppressions || {})));
			if (!noms.size) { return Promise.resolve(); }
			if (local && url) { noms.add('envois'); }
			const maintenant = Date.now();
			return new Promise((ok, ko) => {
				const tx = db.transaction(Array.from(noms), 'readwrite');
				Object.keys(ajouts || {}).forEach((m) => ajouts[m].forEach((e) => {
					if (local) { e.majLe = maintenant; }
					tx.objectStore(m).put(e);
					if (local && url) { tx.objectStore('envois').put({ cle: m + '|' + cleDe(m, e), magasin: m, id: cleDe(m, e), op: 'ecrire', le: e.majLe }); }
				}));
				Object.keys(suppressions || {}).forEach((m) => suppressions[m].forEach((id) => {
					tx.objectStore(m).delete(id);
					if (local && url) { tx.objectStore('envois').put({ cle: m + '|' + id, magasin: m, id, op: 'supprimer', le: maintenant }); }
				}));
				tx.oncomplete = () => { ok(); if (local) { pomper(); } };
				tx.onerror = () => ko(tx.error);
				tx.onabort = () => ko(tx.error || new Error('abandon'));
			});
		}
		function lire(magasin) {
			return promesse(db.transaction(magasin, 'readonly').objectStore(magasin).getAll());
		}

		/* --- Le serveur ------------------------------------------------------ */
		async function api(action, a) {
			a = a || {};
			const q = new URLSearchParams(Object.assign({ action }, a.query || {}));
			const init = { method: a.json || a.corps ? 'POST' : 'GET', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } };
			if (a.json) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(a.json); }
			else if (a.corps) { init.headers['Content-Type'] = 'application/octet-stream'; init.body = a.corps; }
			let r;
			try { r = await fetch(url + (url.indexOf('?') < 0 ? '?' : '&') + q, init); } catch (e) { throw Object.assign(new Error('Pas de connexion au serveur.'), { reseau: true }); }
			if (a.brut && r.ok) { return r.blob(); }
			let d = null;
			try { d = await r.json(); } catch (e) { d = null; }
			if (r.status === 401 || (d && d.session_expiree)) { throw Object.assign(new Error((d && d.message) || 'Session expirée.'), { session: true }); }
			if (r.status === 507 || (d && d.plein)) { throw Object.assign(new Error((d && d.message) || 'Plus de place sur le serveur.'), { plein: true }); }
			if (!r.ok || !d || d.ok === false) { throw Object.assign(new Error((d && d.message) || ('Erreur ' + r.status + ' du serveur.')), { statut: r.status, reseau: r.status >= 500 || r.status === 0 }); }
			return d;
		}

		/* Un enregistrement vers le serveur : l'en-tete, puis chaque donnee par morceaux. */
		async function envoyer(magasin, rec) {
			const { json, parts } = await decouper(rec);
			const tailles = {};
			parts.forEach((p) => { tailles[p.nom] = { taille: p.blob.size }; });
			const d = await api('debut', { json: { magasin, cle: String(cleDe(magasin, rec)), majLe: rec.majLe || 0, enregistrement: json, parts: tailles } });
			if (d.fini || d.plusRecent) { return; }
			for (const p of parts) {
				let pos = 0;
				while (pos < p.blob.size) {
					const r = await api('morceau', { query: { jeton: d.jeton, part: p.nom, pos }, corps: p.blob.slice(pos, pos + MORCEAU) });
					if (!(r.recu > pos)) { throw Object.assign(new Error('Envoi bloqué.'), { reseau: true }); }
					pos = r.recu;
				}
			}
			await api('fin', { query: { jeton: d.jeton }, json: {} });
		}
		/* Un enregistrement depuis le serveur. */
		async function recevoir(magasin, cle) {
			const d = await api('lire', { query: { magasin, cle } });
			const rec = await recoudre(d.enregistrement, (part) => api('part', { query: { magasin, cle, part }, brut: true }));
			rec.majLe = d.majLe;
			return rec;
		}

		/* La file d'envoi, vidée dans l'ordre : « Tout fermer », suppressions,
		   puis les enregistrements (les petits d'abord). Sans connexion, elle
		   reprend 30 s plus tard, ou des que le reseau revient. */
		const rang = (e) => (e.op === 'vider' ? 0 : (e.op === 'supprimer' ? 1 : 2 + Math.max(0, ORDRE.indexOf(e.magasin))));
		let enCours = false, relance = 0;
		function retirer(op) {
			return new Promise((ok) => {
				const tx = db.transaction('envois', 'readwrite'), s = tx.objectStore('envois');
				const g = s.get(op.cle);
				g.onsuccess = () => { const c = g.result; if (c && c.le === op.le && c.op === op.op) { s.delete(op.cle); } };
				tx.oncomplete = () => ok(); tx.onerror = () => ok(); tx.onabort = () => ok();
			});
		}
		async function pomper() {
			if (!url || enCours) { return; }
			enCours = true;
			clearTimeout(relance);
			try {
				for (;;) {
					const file = (await lire('envois')).sort((a, b) => rang(a) - rang(b) || a.le - b.le);
					signaler(file.length ? 'envoi' : 'ok', file.length);
					if (!file.length) { break; }
					const op = file[0];
					try {
						if (op.op === 'vider') { await api('vider', { json: { le: op.le } }); }
						else if (op.op === 'supprimer') { await api('supprimer', { json: { elements: [{ magasin: op.magasin, cle: String(op.id) }], le: op.le } }); }
						else {
							const rec = await promesse(db.transaction(op.magasin, 'readonly').objectStore(op.magasin).get(op.id));
							if (rec) { await envoyer(op.magasin, rec); }
						}
					} catch (e) {
						// refus du serveur pour cet enregistrement seul : on passe au suivant
						if (e.statut >= 400 && e.statut < 500 && e.statut !== 409 && !e.session) { console.warn('Envoi refusé', op, e.message); }
						else { throw e; }
					}
					await retirer(op);
				}
			} catch (e) {
				const attente = (await lire('envois').catch(() => [])).length;
				if (e.session) { signaler('session', attente, e.message); }
				else if (e.plein) { signaler('plein', attente, e.message); }
				else { signaler('horsligne', attente, e.message); relance = setTimeout(pomper, 30000); }
			} finally {
				enCours = false;
			}
		}
		if (url) { window.addEventListener('online', () => pomper()); }

		/* Les photos d'avant la sauvegarde sur le serveur portaient les numeros
		   1, 2, 3... de chaque appareil : renumerotees une fois, elles ne se
		   confondent pas avec celles d'un autre ordinateur. */
		async function renumeroter() {
			const anciennes = (await lire('photos')).filter((p) => typeof p.id === 'number' && p.id < 1e10);
			if (!anciennes.length) { return; }
			const nouveau = new Map(anciennes.map((p) => [p.id, nouvelId()]));
			const ajouts = {}, retraits = {};
			for (const m of ['fichiers', 'photos', 'masques', 'detourages']) {
				for (const e of await lire(m)) {
					if (!nouveau.has(e.id)) { continue; }
					(retraits[m] = retraits[m] || []).push(e.id);
					(ajouts[m] = ajouts[m] || []).push(Object.assign({}, e, { id: nouveau.get(e.id) }));
				}
			}
			const at = (await lire('atelier')).find((e) => e.cle === 'atelier');
			if (at) {
				at.ordre = (at.ordre || []).map((id) => nouveau.get(id) || id);
				if (nouveau.has(at.active)) { at.active = nouveau.get(at.active); }
				ajouts.atelier = [at];
			}
			await ecrire({}, retraits, { depuisServeur: true });
			await ecrire(ajouts, {});
		}

		/* Au demarrage : le serveur et ce navigateur mis d'accord. signalerEtat :
		   texte d'avancement (« Récupération de vos photos… »). */
		async function synchroniser(signalerEtat) {
			if (!url) { return { recus: 0 }; }
			// trois essais : un reseau qui hesite au chargement ne laisse pas l'atelier vide
			let liste = null;
			for (let essai = 0; !liste; essai++) {
				try { liste = await api('liste'); } catch (e) {
					if (!e.reseau || essai >= 2) { throw e; }
					await new Promise((r) => setTimeout(r, 1500 * (essai + 1)));
				}
			}
			await renumeroter();
			const locaux = {};
			for (const m of DONNEES) { locaux[m] = new Map((await lire(m)).map((e) => [String(cleDe(m, e)), e])); }
			const envois = new Map((await lire('envois')).map((e) => [e.cle, e]));
			const vidage = envois.get('*');
			const supprimes = liste.supprimes || {}, videLe = liste.videLe || 0;

			// 1. « Tout fermer » ou suppressions faits sur un autre appareil
			const retraits = {};
			DONNEES.forEach((m) => locaux[m].forEach((e, cle) => {
				const majLe = e.majLe || 0, trace = supprimes[m + '/' + cle] || 0;
				if (majLe > 0 && (majLe <= videLe || (trace && majLe <= trace))) {
					(retraits[m] = retraits[m] || []).push(cleDe(m, e));
					locaux[m].delete(cle);
				}
			}));
			if (Object.keys(retraits).length) { await ecrire({}, retraits, { depuisServeur: true }); }

			// 2. ce que le serveur a de plus recent
			const surServeur = new Map(liste.enregistrements.map((e) => [e.magasin + '|' + e.cle, e]));
			const aRecevoir = liste.enregistrements.filter((s) => {
				if (vidage && s.majLe <= vidage.le) { return false; }
				const attente = envois.get(s.magasin + '|' + s.cle);
				if (attente && attente.op === 'supprimer' && attente.le >= s.majLe) { return false; }
				const loc = locaux[s.magasin] && locaux[s.magasin].get(s.cle);
				return !loc || (loc.majLe || 0) < s.majLe;
			}).sort((a, b) => ORDRE.indexOf(a.magasin) - ORDRE.indexOf(b.magasin));
			const photosARecevoir = aRecevoir.filter((s) => s.magasin === 'fichiers').length;
			let n = 0, k = 0;
			for (const s of aRecevoir) {
				n++;
				if (s.magasin === 'fichiers') { k++; }
				if (signalerEtat) {
					signalerEtat(s.magasin === 'fichiers'
						? 'Récupération de vos photos depuis le serveur : ' + k + ' sur ' + photosARecevoir + '…'
						: 'Récupération de vos retouches depuis le serveur (' + n + ' sur ' + aRecevoir.length + ')…');
				}
				const rec = await recevoir(s.magasin, s.cle);
				await ecrire({ [s.magasin]: [rec] }, {}, { depuisServeur: true });
				locaux[s.magasin].set(s.cle, rec);
			}

			// 3. ce que le serveur n'a pas : envoye (les anciens enregistrements, sans date, sont dates)
			const aDater = {}, aEnvoyer = [];
			DONNEES.forEach((m) => locaux[m].forEach((e, cle) => {
				const s = surServeur.get(m + '|' + cle);
				if (s && (e.majLe || 0) <= s.majLe) { return; }
				if (envois.has(m + '|' + cle)) { return; }
				if (!e.majLe) { (aDater[m] = aDater[m] || []).push(e); }
				else { aEnvoyer.push({ cle: m + '|' + cle, magasin: m, id: cleDe(m, e), op: 'ecrire', le: e.majLe }); }
			}));
			if (Object.keys(aDater).length) { await ecrire(aDater, {}); }
			if (aEnvoyer.length) {
				await new Promise((ok) => {
					const tx = db.transaction('envois', 'readwrite');
					aEnvoyer.forEach((e) => tx.objectStore('envois').put(e));
					tx.oncomplete = () => ok(); tx.onerror = () => ok();
				});
			}
			pomper();
			return { recus: aRecevoir.length, retires: Object.values(retraits).reduce((n, l) => n + l.length, 0) };
		}

		return {
			ecrire, synchroniser, pomper,
			get serveur() { return !!url; },
			async toutLire() {
				const [fichiers, photos, masques, fonds, detourages, atelier] = await Promise.all(['fichiers', 'photos', 'masques', 'fonds', 'detourages', 'atelier'].map(lire));
				const parId = (liste) => new Map(liste.map((e) => [e.id, e]));
				return { fichiers: parId(fichiers), photos: parId(photos), masques: parId(masques), fonds, detourages: parId(detourages), atelier: atelier.find((e) => e.cle === 'atelier') || null };
			},
			supprimerPhoto(id) { return ecrire({}, { fichiers: [id], photos: [id], masques: [id], detourages: [id] }); },
			/* « Tout fermer » : ce navigateur, et le serveur (pour tous les appareils). */
			vider() {
				const le = Date.now();
				return new Promise((ok, ko) => {
					const tx = db.transaction(DONNEES.concat(['envois']), 'readwrite');
					DONNEES.forEach((m) => tx.objectStore(m).clear());
					const ev = tx.objectStore('envois');
					ev.clear();
					if (url) { ev.put({ cle: '*', magasin: '*', id: '*', op: 'vider', le }); }
					tx.oncomplete = () => { ok(); pomper(); };
					tx.onerror = () => ko(tx.error);
				});
			},
		};
	}

	/* --- Detourage : cartes de la personne en 8 bits, compressees --------------- */
	const CARTES = ['alpha', 'peau', 'visage', 'cheveux', 'peauCorps', 'vetements'];

	/** Le detourage seg (cartes de travail w x h) en enregistrement a ecrire. */
	async function emballerDetourage(id, seg, w, h) {
		const n = w * h, u = new Uint8Array(n * CARTES.length);
		CARTES.forEach((cle, j) => {
			const c = seg[cle];
			if (!c) { return; }
			for (let i = 0; i < n; i++) { const v = c[i]; u[j * n + i] = v <= 0 ? 0 : (v >= 1 ? 255 : v * 255 + 0.5); }
		});
		const brut = new Blob([u]);
		const donnees = compresse ? await gz(brut) : brut;
		return {
			id, w, h, version: 1, gzip: compresse, donnees,
			fin: !!seg.fin, personne: seg.personne || null, teteBoite: seg.teteBoite || null, visageBoite: seg.visageBoite || null,
		};
	}

	/** Le detourage enregistre, rendu aux cartes de travail ; null s'il ne va pas a cette taille. */
	async function deballerDetourage(rec, w, h) {
		if (!rec || rec.version !== 1 || rec.w !== w || rec.h !== h) { return null; }
		const u = new Uint8Array(rec.gzip ? await degz(rec.donnees) : await rec.donnees.arrayBuffer());
		const n = w * h;
		if (u.length !== n * CARTES.length) { return null; }
		const seg = { fin: rec.fin, personne: rec.personne, teteBoite: rec.teteBoite, visageBoite: rec.visageBoite };
		CARTES.forEach((cle, j) => {
			const c = new Float32Array(n);
			for (let i = 0; i < n; i++) { c[i] = u[j * n + i] / 255; }
			seg[cle] = c;
		});
		seg.tete = new Float32Array(n);
		for (let i = 0; i < n; i++) { seg.tete[i] = Math.min(1, seg.cheveux[i] + seg.visage[i]); }
		return seg;
	}

	window.RetoucheSauvegarde = { ouvrir, emballerDetourage, deballerDetourage, nouvelId };
})();
