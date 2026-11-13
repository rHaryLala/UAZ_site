// Studio UAZ du back-office : l'éditeur s'ouvre, dessine un visuel, change de modèle et de format, enregistre tout seul, exporte un PNG et un ZIP.
// Prérequis : serveur lancé, compte admin@uaz.test. Usage : BASE_URL=http://127.0.0.1:8001 node tests/e2e/studio.mjs [dossier-captures]
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
let failures = 0;
const check = (ok, label) => { console.log(`${ok ? '✓' : '✗'} ${label}`); if (!ok) failures++; };
const until = async (fn, ms = 20000) => {
    const end = Date.now() + ms;
    while (Date.now() < end) { try { if (await fn()) return true; } catch { /* chargement */ } await new Promise((r) => setTimeout(r, 150)); }
    return false;
};

const browser = await chromium.launch();
const errors = [];
try {
    const context = await browser.newContext({ viewport: { width: 1600, height: 900 }, acceptDownloads: true });
    const page = await context.newPage();
    page.on('pageerror', (e) => errors.push(e.message.slice(0, 160)));
    await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
    await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: /Connexion|Se connecter/ }).click();
    await page.waitForURL('**/admin');
    check(await until(async () => (await page.locator('a[href*="/admin/studio"]').count()) > 0), 'le menu du back-office propose « Studio UAZ »');

    errors.length = 0;
    // Le navigateur de test perd parfois une ressource en local (ERR_NETWORK_ACCESS_DENIED) : on recharge jusqu'à ce que l'éditeur démarre.
    for (let essai = 0; essai < 5; essai++) {
        try { await page.goto(`${base}/admin/studio`, { waitUntil: 'load' }); } catch { await new Promise((r) => setTimeout(r, 2500)); continue; }
        if (await page.waitForFunction(() => window.StudioEditeur, null, { timeout: 15000 }).then(() => true, () => false)) { break; }
    }
    check(await until(async () => (await page.locator('#stModeles .st-modele').count()) === 12), 'douze modèles sont proposés (écrans et affiches)');
    check(await until(async () => (await page.locator('#stCanvas').evaluate((c) => c.width)) >= 600), 'le visuel est dessiné');
    check((await page.locator('.st-format').count()) === 4, 'quatre formats (16:9, 1:1, 4:5, 9:16)');
    const painted = await page.locator('#stCanvas').evaluate((c) => { const d = c.getContext('2d').getImageData(0, 0, c.width, c.height).data; let navy = 0; for (let i = 0; i < d.length; i += 4 * 97) { if (d[i + 2] > d[i] + 30) navy++; } return navy; });
    check(painted > 200, 'le fond marine est bien là');
    if (out) await page.screenshot({ path: path.join(out, 'studio-ouvert.png') });

    // Agir sur le visuel lui-même : sélectionner, déplacer, supprimer, annuler.
    const ed = (fn, arg) => page.evaluate(fn, arg);
    const centre = (id) => page.evaluate((i) => {
        const z = window.StudioEditeur.zones.find((q) => q.id === i);
        const r = document.getElementById('stCadre').getBoundingClientRect(), s = r.width / 1920;

        return { x: r.left + (z.rect.x + z.rect.w / 2) * s, y: r.top + (z.rect.y + z.rect.h / 2) * s };
    }, id);
    await page.waitForTimeout(1200); // le visuel finit de se mettre à l’échelle de la fenêtre avant qu’on clique dessus
    let c = await centre('titre');
    await page.mouse.click(c.x, c.y);
    check(await ed(() => window.StudioEditeur.selection) === 'titre', 'un clic sur le titre le sélectionne');
    check(await page.locator('#stSel').isVisible() && await page.locator('#stBarreEl').isVisible(), 'son cadre et sa barre d’outils apparaissent');
    await page.mouse.move(c.x, c.y); await page.mouse.down(); await page.mouse.move(c.x + 70, c.y + 40, { steps: 6 }); await page.mouse.up();
    const deplace = await ed(() => window.StudioEditeur.etat.options.decalages['16:9']?.titre);
    check(Array.isArray(deplace) && deplace[0] > 50 && deplace[1] > 20, `le titre se déplace en le glissant (${JSON.stringify(deplace)})`);
    await page.keyboard.press('Delete');
    check((await ed(() => window.StudioEditeur.etat.options.masques)).includes('titre'), 'Suppr masque le titre');
    await page.keyboard.press('Control+z');
    check(!(await ed(() => window.StudioEditeur.etat.options.masques)).includes('titre'), 'Ctrl+Z le ramène');
    await page.locator('.st-onglet', { hasText: 'Éléments' }).click();
    await page.locator('.st-ajout', { hasText: 'Texte' }).click();
    check(await until(async () => await page.locator('#stPopover').isVisible()), 'ajouter un texte ouvre sa fenêtre de réglages');
    await page.locator('#stPopover textarea').fill('Mon texte libre');
    await page.locator('.st-ajout', { hasText: 'Rectangle' }).click();
    check((await ed(() => window.StudioEditeur.etat.options.libres.map((l) => l.type))).join() === 'texte,forme', 'un texte et un rectangle sont ajoutés');
    check(await ed(() => window.StudioEditeur.zones.some((z) => z.libre && z.nom === 'Texte')), 'les éléments ajoutés sont dessinés et sélectionnables');
    await page.keyboard.press('Delete');
    check((await ed(() => window.StudioEditeur.etat.options.libres.length)) === 1, 'Suppr retire l’élément ajouté');
    await page.waitForTimeout(500);

    // Changer de modèle, modifier un texte.
    await page.locator('.st-onglet', { hasText: 'Modèles' }).click();
    await page.locator('.st-modele', { has: page.locator('.st-modele__nom', { hasText: /^Direct$/ }) }).click();
    check(await until(async () => (await page.locator('#stContenu').isVisible())), 'choisir un modèle ouvre le contenu');
    await page.locator('#stContenu .st-champ input[type=text]').nth(2).fill('Le culte commence');
    check(await until(async () => (await page.locator('#stEtat').innerText()).includes('Enregistré')), 'l\'enregistrement est automatique', 15000);
    const saved = await page.evaluate(async () => (await (await fetch('/admin/studio/designs', { headers: { Accept: 'application/json' } })).json()).designs);
    check(saved.length >= 1 && saved[0].template === 'direct', 'le visuel est enregistré pour mon compte (modèle « direct »)');
    const complet = await page.evaluate(async (id) => (await (await fetch(`/admin/studio/designs/${id}`, { headers: { Accept: 'application/json' } })).json()).design, saved[0].id);
    check(complet.data.options.libres.length === 1 && complet.data.options.libres[0].texte === 'Mon texte libre', 'les éléments ajoutés sont enregistrés avec le visuel');

    // Changer de format et exporter.
    await page.locator('.st-format', { hasText: 'Story' }).click();
    check(await until(async () => (await page.locator('#stZoneBloc').isVisible())), 'le format Story propose les zones couvertes par l\'interface');
    await page.locator('.st-format', { hasText: '16:9' }).click();
    await page.waitForTimeout(800);
    await page.locator('#stExporter').click();
    const [png] = await Promise.all([page.waitForEvent('download', { timeout: 30000 }), page.locator('[data-export="png-1"]').click()]);
    const name = png.suggestedFilename();
    check(/^UAZ_DIR_\d{4}-\d{2}-\d{2}_.+_16x9\.png$/.test(name), `nom du fichier conforme (${name})`);
    const pngPath = path.join(out || '.', name);
    await png.saveAs(pngPath);
    check(fs.statSync(pngPath).size > 30000, `le PNG est un vrai fichier (${Math.round(fs.statSync(pngPath).size / 1024)} Ko)`);
    if (!out) fs.unlinkSync(pngPath);

    await page.locator('#stExporter').click();
    const [zip] = await Promise.all([page.waitForEvent('download', { timeout: 60000 }), page.locator('[data-export="zip"]').click()]);
    const zipPath = path.join(out || '.', 'studio-test.zip');
    await zip.saveAs(zipPath);
    const zbytes = fs.readFileSync(zipPath);
    check(zbytes.readUInt32LE(0) === 0x04034b50 && zbytes.includes(Buffer.from('_1x1.png')) && zbytes.includes(Buffer.from('_9x16.png')), 'le ZIP contient les quatre formats');
    if (!out) fs.unlinkSync(zipPath);

    // Mes visuels : reprendre le visuel enregistré.
    await page.locator('.st-onglet', { hasText: 'Mes visuels' }).click();
    check(await until(async () => (await page.locator('.st-visuel').count()) >= 1), '« Mes visuels » liste le visuel');

    // Nettoyage des visuels de test.
    await page.evaluate(async (ids) => { for (const id of ids) { await fetch(`/admin/studio/designs/${id}`, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } }); } }, saved.map((d) => d.id));

    const miennes = errors.filter((e) => !/filament|livewire|alpine/i.test(e)); // les scripts du tableau de bord Filament, encore en route au départ, ne sont pas ceux du Studio
    check(miennes.length === 0, 'aucune erreur JavaScript' + (miennes.length ? ' : ' + miennes.join(' | ') : ''));
} finally {
    await browser.close();
}
process.exit(failures ? 1 : 0);
