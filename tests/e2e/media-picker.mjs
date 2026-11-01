// Sélecteur de médias « façon WordPress » dans un vrai navigateur : ouverture depuis un formulaire, grille, recherche, détails,
// choix, aperçu dans le champ, envoi d'un fichier avec texte alternatif, retrait. Prérequis : serveur lancé, compte admin de développement.
// Usage : node tests/e2e/media-picker.mjs [dossier-captures]
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
const php = process.env.PHP ?? 'php';
const tinker = (code) => execFileSync(php, ['artisan', 'tinker', '--execute', code], { encoding: 'utf8' }).trim();
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

const { default: sharp } = await import('sharp');
const stamp = Date.now();
const file = path.join(os.tmpdir(), `selecteur-${stamp}.png`);
await sharp({ create: { width: 900, height: 600, channels: 3, background: '#2a6f5b' } }).png().toFile(file);
const alt = `Aplat vert de test ${stamp}`;

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' })).newPage();
const errors = [];
page.on('pageerror', (e) => !/is not defined|Failed to fetch dynamically/.test(e.message) && errors.push(e.message.slice(0, 140)));

try {
    await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
    await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: 'Connexion' }).click();
    await page.waitForURL('**/admin', { timeout: 20000 });

    await page.goto(`${base}/admin/news-posts/create`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    const field = page.locator('.uaz-mp').first();
    check((await field.getByRole('button', { name: 'Choisir une image' }).count()) === 1, 'le champ « Image à la une » propose « Choisir une image »');

    await field.getByRole('button', { name: 'Choisir une image' }).click();
    const modal = page.locator('.uaz-mp-modal:visible');
    await modal.waitFor({ timeout: 8000 });
    await page.waitForSelector('.uaz-mp-tile', { timeout: 10000 });
    const tiles = await page.locator('.uaz-mp-tile').count();
    check(tiles >= 6, `la médiathèque s'affiche en grille (${tiles} vignettes)`);
    const loaded = await page.evaluate(() => [...document.querySelectorAll('.uaz-mp-tile img')].filter((i) => i.complete && i.naturalWidth > 0).length);
    check(loaded >= 12, `les vignettes visibles sont chargées (${loaded}/${tiles}, les autres se chargent au défilement)`);
    if (out) await page.screenshot({ path: path.join(out, 'selecteur-grille.png') });
    // « Charger plus » au défilement : les vignettes gardent leur forme carrée (pas de bandes écrasées).
    await page.locator('.uaz-mp-modal:visible .uaz-mp-grid').evaluate((el) => { el.scrollTop = el.scrollHeight; });
    // On attend la suite (réponse du serveur) plutôt qu'un délai fixe : le serveur de développement répond en 0,3 à 2,5 s.
    await page.waitForFunction((n) => document.querySelectorAll('.uaz-mp-modal .uaz-mp-tile').length > n, tiles, { timeout: 12000 }).catch(() => {});
    const more = await page.locator('.uaz-mp-modal:visible .uaz-mp-tile').count();
    const flat = await page.evaluate(() => [...document.querySelectorAll('.uaz-mp-modal .uaz-mp-tile')].filter((t) => t.offsetParent && t.getBoundingClientRect().height < 100).length);
    check(more > tiles && flat === 0, `après défilement (${more} vignettes), aucune vignette écrasée (${flat})`);
    await page.locator('.uaz-mp-modal:visible .uaz-mp-grid').evaluate((el) => { el.scrollTop = 0; });

    await page.locator('.uaz-mp-modal:visible .uaz-mp-input').first().fill('Bibliothèque');
    await page.waitForFunction(() => { const n = document.querySelectorAll('.uaz-mp-modal .uaz-mp-tile').length; return n > 0 && n < 36; }, null, { timeout: 10000 }).catch(() => {});
    const filtered = await page.locator('.uaz-mp-modal:visible .uaz-mp-tile').count();
    check(filtered >= 1 && filtered < tiles, `la recherche filtre la grille (${filtered})`);

    await page.locator('.uaz-mp-modal:visible .uaz-mp-tile').first().click();
    check(await page.locator('.uaz-mp-side h3').isVisible(), 'les détails du fichier s\'affichent à droite');
    await modal.getByRole('button', { name: 'Choisir cette image' }).click();
    await page.waitForTimeout(500);
    check((await page.locator('.uaz-mp-modal:visible').count()) === 0, 'la fenêtre se ferme après le choix');
    check((await field.locator('.uaz-mp__thumb').count()) === 1, 'l\'aperçu de l\'image choisie apparaît dans le champ');
    const stateSet = await page.evaluate(() => window.Livewire.all().some((c) => c.$wire?.data?.cover_id));
    check(stateSet, 'la valeur est bien transmise au formulaire (cover_id)');

    // Envoi d'un fichier depuis l'onglet « Envoyer des fichiers »
    await field.getByRole('button', { name: 'Changer' }).click();
    await modal.waitFor();
    await modal.getByRole('tab', { name: 'Envoyer des fichiers' }).click();
    await modal.locator('input[type=file]').setInputFiles(file);
    const send = modal.getByRole('button', { name: /Envoyer dans la médiathèque/ });
    check(await send.isDisabled(), 'l\'envoi est bloqué tant que le texte alternatif manque');
    await modal.locator('.uaz-mp-pending input[type=text]').fill(alt);
    await send.click();
    await page.waitForSelector('.uaz-mp-tile.is-selected', { timeout: 20000 });
    check(true, 'le fichier est envoyé, ajouté à la grille et sélectionné');
    await modal.getByRole('button', { name: 'Choisir cette image' }).click();
    await page.waitForTimeout(400);
    check((await field.locator('.uaz-mp__meta').innerText()).includes(`selecteur-${stamp}`), 'le nouveau fichier est le média choisi');

    await field.getByRole('button', { name: 'Retirer' }).click();
    check((await field.locator('.uaz-mp__thumb').count()) === 0, '« Retirer » vide le champ');
    // Album photo : sélection multiple de photos qui s'ajoutent à la liste de l'album.
    await page.goto(`${base}/admin/photo-albums/create`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1200);
    const multi = page.locator('.uaz-mp').filter({ hasText: 'Choisir des images' }).first();
    await multi.getByRole('button', { name: /Choisir des images/ }).click();
    await page.waitForSelector('.uaz-mp-modal:visible .uaz-mp-tile', { timeout: 10000 });
    const album = page.locator('.uaz-mp-modal:visible');
    await album.locator('.uaz-mp-tile').nth(0).click();
    await album.locator('.uaz-mp-tile').nth(1).click();
    check((await album.getByText('2 fichier(s) sélectionné(s)').count()) === 1, 'album : plusieurs photos peuvent être cochées');
    await album.getByRole('button', { name: 'Ajouter la sélection' }).click();
    await page.waitForTimeout(2500);
    const added = await page.locator('.fi-fo-repeater-item').count();
    check(added >= 2, `album : les photos choisies s'ajoutent à l'album (${added})`);
    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    if (out) await page.screenshot({ path: path.join(out, 'selecteur-erreur.png') });
    failures++;
} finally {
    await browser.close();
    tinker(`App\\Models\\MediaAsset::where('alt','${alt}')->get()->each->delete();`);
    fs.rmSync(file, { force: true });
}

process.exit(failures ? 1 : 0);
