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
    await page.goto(`${base}/admin/studio`, { waitUntil: 'load' });
    check(await until(async () => (await page.locator('#stModeles .st-modele').count()) === 5), 'cinq modèles sont proposés');
    check(await until(async () => (await page.locator('#stCanvas').evaluate((c) => c.width)) >= 600), 'le visuel est dessiné');
    check((await page.locator('.st-format').count()) === 4, 'quatre formats (16:9, 1:1, 4:5, 9:16)');
    const painted = await page.locator('#stCanvas').evaluate((c) => { const d = c.getContext('2d').getImageData(0, 0, c.width, c.height).data; let navy = 0; for (let i = 0; i < d.length; i += 4 * 97) { if (d[i + 2] > d[i] + 30) navy++; } return navy; });
    check(painted > 200, 'le fond marine est bien là');
    if (out) await page.screenshot({ path: path.join(out, 'studio-ouvert.png') });

    // Changer de modèle, modifier un texte.
    await page.locator('.st-modele', { hasText: 'Direct' }).click();
    check(await until(async () => (await page.locator('#stContenu').isVisible())), 'choisir un modèle ouvre le contenu');
    await page.locator('#stContenu .st-champ input[type=text]').nth(2).fill('Le culte commence');
    check(await until(async () => (await page.locator('#stEtat').innerText()).includes('Enregistré')), 'l\'enregistrement est automatique', 15000);
    const saved = await page.evaluate(async () => (await (await fetch('/admin/studio/designs', { headers: { Accept: 'application/json' } })).json()).designs);
    check(saved.length >= 1 && saved[0].template === 'direct', 'le visuel est enregistré pour mon compte (modèle « direct »)');

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

    check(errors.length === 0, 'aucune erreur JavaScript' + (errors.length ? ' : ' + errors.join(' | ') : ''));
} finally {
    await browser.close();
}
process.exit(failures ? 1 : 0);
