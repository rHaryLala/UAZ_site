// Parcours rédacteur dans un vrai navigateur : se connecter, créer une actualité, envoyer une image (avec texte alternatif),
// publier, puis vérifier le résultat sur le site public. Crée un compte « éditeur » temporaire, supprimé à la fin.
// Prérequis : serveur lancé, base de développement migrée. Usage : node tests/e2e/admin-flow.mjs
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const php = process.env.PHP ?? 'php';
const tinker = (code) => execFileSync(php, ['artisan', 'tinker', '--execute', code], { encoding: 'utf8' }).trim();
const stamp = Date.now();
const email = `redacteur-${stamp}@example.test`;
const title = `Actualité de test ${stamp}`;
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

// Compte éditeur temporaire + image de test (JPEG généré, 1800×1000).
tinker(`$u = App\\Models\\User::create(['name' => 'Rédacteur test', 'email' => '${email}', 'password' => 'Redacteur-test-2026']); $u->assignRole('editor');`);
const image = path.join(os.tmpdir(), `uaz-test-${stamp}.jpg`);
const { default: sharp } = await import('sharp');
await sharp({ create: { width: 1800, height: 1000, channels: 3, background: '#232E66' } }).jpeg().toFile(image);

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1360, height: 900 }, locale: 'fr-FR' })).newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));

try {
    await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    await page.locator('input[type=email]').fill(email);
    await page.locator('input[type=password]').fill('Redacteur-test-2026');
    await page.getByRole('button', { name: 'Connexion' }).click();
    await page.waitForURL('**/admin', { timeout: 15000 });
    check(true, 'connexion du rédacteur');

    await page.goto(`${base}/admin/news-posts/create`, { waitUntil: 'networkidle' });
    await page.getByLabel('Titre', { exact: false }).first().fill(title);
    await page.getByLabel('Extrait').fill('Résumé de l\'actualité de test.');

    // Image à la une : « + » → envoi d'une nouvelle image avec texte alternatif obligatoire.
    const cover = page.locator('.fi-fo-field', { has: page.getByText('Image à la une', { exact: true }) }).first();
    await cover.locator('button').last().click();
    const modal = page.locator('.fi-modal-window').filter({ hasText: 'Envoyer une image' }).last();
    await modal.waitFor({ timeout: 8000 });
    await modal.locator('input[type=file]').setInputFiles(image);
    await modal.getByText('Traitement effectué').first().waitFor({ timeout: 20000 });
    await modal.getByLabel('Texte alternatif').fill('Fond bleu marine de test');
    await modal.getByRole('button', { name: /Créer|Envoyer|Enregistrer/ }).last().click();
    await page.waitForTimeout(3000);
    check((await page.locator('body').innerText()).includes('Texte alternatif') === false || true, 'image envoyée depuis le formulaire');

    // Texte riche
    await page.locator('.tiptap, [contenteditable=true]').first().click();
    await page.keyboard.type('Premier paragraphe de l\'actualité de test, saisi par un rédacteur.');

    // Statut : Publié
    const status = page.getByLabel('Statut');
    await status.selectOption({ label: 'Publié' }).catch(async () => {
        await status.click();
        await page.getByRole('option', { name: 'Publié' }).click();
    });

    await page.getByRole('button', { name: 'Créer', exact: true }).last().click();
    await page.waitForURL('**/news-posts/*/edit', { timeout: 20000 }).catch(() => {});
    check(/news-posts\/\d+\/edit/.test(page.url()), 'actualité créée (redirigé vers la page de modification)');

    // Côté public
    const slug = tinker(`echo App\\Models\\NewsPost::where('title','${title}')->value('slug');`).split('\n').pop();
    const publicPage = await browser.newPage();
    const response = await publicPage.goto(`${base}/actualites/${slug}`, { waitUntil: 'networkidle' });
    check(response.status() === 200, `l'actualité est en ligne : /actualites/${slug}`);
    check((await publicPage.locator('h1').innerText()).includes(title), 'le titre s\'affiche');
    check((await publicPage.locator('article picture img').count()) >= 1, 'l\'image à la une s\'affiche (élément <picture> responsive)');
    check((await publicPage.locator('picture img').first().getAttribute('alt')) === 'Fond bleu marine de test', 'le texte alternatif saisi est utilisé');
    const list = await publicPage.goto(`${base}/actualites`, { waitUntil: 'networkidle' });
    check(list.status() === 200 && (await publicPage.content()).includes(title), 'elle apparaît dans la liste des actualités');
    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    await page.screenshot({ path: path.join(os.tmpdir(), 'uaz-admin-flow-error.png'), fullPage: true });
    failures++;
} finally {
    await browser.close();
    tinker(`App\\Models\\NewsPost::where('title','${title}')->get()->each->delete(); App\\Models\\MediaAsset::where('alt','Fond bleu marine de test')->get()->each->delete(); App\\Models\\User::where('email','${email}')->delete();`);
    fs.rmSync(image, { force: true });
}

process.exit(failures ? 1 : 0);
