// Modèles complets dans le panneau « Ajouter » : un clic pose tous les blocs du modèle à la suite, au premier niveau, sans l'en-tête ;
// photo en fond et mosaïque s'affichent et s'éditent. Prérequis : serveur lancé, compte admin@uaz.test. Usage : node tests/e2e/newsletter-template-blocks.mjs [dossier-captures]
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
const fixture = path.join(path.dirname(fileURLToPath(import.meta.url)), 'fixtures', 'newsletter.php');
const php = (...args) => execFileSync(process.env.PHP_BIN ?? 'php', args, { encoding: 'utf8' });
let failures = 0;
const check = (ok, label) => { console.log(`${ok ? '✓' : '✗'} ${label}`); if (!ok) failures++; };
const until = async (fn, ms = 10000) => {
    const end = Date.now() + ms;
    while (Date.now() < end) { try { if (await fn()) return true; } catch { /* chargement */ } await new Promise((r) => setTimeout(r, 120)); }
    return false;
};

const id = Number(php(fixture, 'up').trim().split('\n').pop());
const browser = await chromium.launch();
const errors = [];
try {
    const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
    page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
    await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
    await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: /Connexion|Se connecter/ }).click();
    await page.waitForURL('**/admin');
    await page.goto(`${base}/admin/newsletter-editor/${id}`, { waitUntil: 'load' });
    const canvas = page.frameLocator('[data-nl-frame]');
    const types = () => canvas.locator('td[data-nl-depth="0"][data-nl-block]').evaluateAll((els) => els.map((el) => el.dataset.nlType));
    check(await until(async () => (await types()).length === 3), 'la newsletter de départ a 3 blocs');

    await page.locator('[data-nl-tab="add"]').click();
    check(await page.locator('.nl-group', { hasText: 'Modèles complets' }).count() === 1, 'le panneau propose la rubrique « Modèles complets »');
    check(await page.locator('.nl-group', { hasText: 'Modèles complets' }).locator('.nl-card').count() === 6, 'six modèles sont proposés');

    // Sélection du premier bloc puis insertion du modèle « À la une » : il se pose juste en dessous, au premier niveau.
    await canvas.locator('td[data-nl-depth="0"][data-nl-block]').first().click();
    await page.locator('[data-nl-tab="add"]').click();
    await page.locator('.nl-group', { hasText: 'Modèles complets' }).locator('.nl-card', { hasText: 'À la une' }).first().click();
    check(await until(async () => (await types()).length === 3 + 9), 'le modèle « À la une » ajoute ses 9 blocs d\'un coup');
    const list = await types();
    check(list[0] === 'heading' && list[1] === 'hero' && list.includes('mosaic'), 'ils sont posés à la suite du bloc sélectionné (photo en fond puis mosaïque)');
    check(!list.includes('header'), 'l\'en-tête n\'est pas dupliqué');
    check(await until(async () => (await canvas.locator('.nl-hero').count()) === 2 && (await canvas.locator('img.nl-tile').count()) >= 3), 'photo en fond et mosaïque sont dessinées avec des photos');
    if (out) await page.screenshot({ path: path.join(out, 'template-inserted.png') });

    // Annuler retire tout le modèle d'un coup.
    await page.keyboard.press('Control+z');
    check(await until(async () => (await types()).length === 3), 'annuler retire tout le modèle d\'un coup');
    check(errors.length === 0, 'aucune erreur JavaScript' + (errors.length ? ' : ' + errors.join(' | ') : ''));
} finally {
    await browser.close();
    php(fixture, 'down');
}
process.exit(failures ? 1 : 0);
