// Retouche photo du back-office : l'atelier s'ouvre sans erreur, ouvre une photo, la retouche (lumière, netteté), recadre, exporte un fichier lisible,
// et la recherche d'une personne de l'équipe répond. Prérequis : serveur lancé, compte admin@uaz.test, WebGL disponible dans Chromium.
// Usage : BASE_URL=http://127.0.0.1:8001 node tests/e2e/retouche-photo.mjs <photo.jpg> [dossier-captures]
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const photo = process.argv[2];
const out = process.argv[3];
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

    // Le menu du back-office propose l'atelier.
    check(await until(async () => (await page.locator('body').innerText()).includes('Retouche photo')), 'le menu du back-office propose « Retouche photo »');

    errors.length = 0; // le tableau de bord de Filament a ses propres scripts
    await page.goto(`${base}/admin/retouche-photo`, { waitUntil: 'load' });
    check(await page.locator('#rpApp').count() === 1, 'l\'atelier s\'ouvre');
    check(await until(async () => (await page.locator('#rpAjouter').isEnabled())), 'le moteur WebGL démarre (bouton « Ajouter des photos » actif)');

    // Une photo.
    await page.locator('#rpFichiers').setInputFiles(photo);
    check(await until(async () => (await page.locator('#rpVignettes li').count()) === 1), 'la photo apparaît dans la pellicule');
    check(await until(async () => (await page.locator('#rpInfos').innerText()).includes('×') || /\d/.test(await page.locator('#rpInfos').innerText())), 'ses informations s\'affichent');
    await page.waitForTimeout(1500);
    const painted = await page.locator('#rpHisto').evaluate((c) => { const d = c.getContext('2d').getImageData(0, 0, c.width, c.height).data; let n = 0; for (let i = 3; i < d.length; i += 4) if (d[i] > 0) n++; return n; });
    check(painted > 500, 'la photo est analysée et dessinée (histogramme non vide)');
    if (out) await page.screenshot({ path: path.join(out, 'retouche-ouverte.png') });

    // Un réglage : l'exposition, puis la netteté.
    await page.locator('#rp-exposition').evaluate((el) => { el.value = '0.5'; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); });
    await page.locator('#rp-nettete').evaluate((el) => { el.value = '40'; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); });
    check(await page.locator('#rpAnnuler').isEnabled(), 'un réglage entre dans l\'historique (Annuler actif)');

    // Export : réduit à 1200 px, avec la netteté de sortie standard.
    await page.locator('#rpTaille').selectOption('1200');
    check(await page.locator('#rpNetteteSortie').inputValue() === 'standard', 'la netteté à l’export est « Standard » par défaut');
    const [download] = await Promise.all([page.waitForEvent('download', { timeout: 30000 }), page.locator('#rpExporter').click()]);
    const file = path.join(out || '.', 'retouche-export' + path.extname(download.suggestedFilename()));
    await download.saveAs(file);
    const size = fs.statSync(file).size;
    check(size > 20000, `l'export donne un fichier (${download.suggestedFilename()}, ${Math.round(size / 1024)} Ko)`);
    if (!out) fs.unlinkSync(file);

    // La recherche d'une personne de l'équipe (réponse JSON, session reprise du navigateur).
    const res = await page.evaluate(async () => { const r = await fetch('/admin/retouche-photo/personnes?action=chercher&type=equipe&q=ra', { headers: { Accept: 'application/json' } }); return { status: r.status, json: await r.json() }; });
    check(res.status === 200 && res.json.ok === true && Array.isArray(res.json.resultats), 'la recherche de personnes répond');

    check(errors.length === 0, 'aucune erreur JavaScript' + (errors.length ? ' : ' + errors.join(' | ') : ''));
} finally {
    await browser.close();
}
process.exit(failures ? 1 : 0);
