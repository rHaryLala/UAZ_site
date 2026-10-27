// Médiathèque du back-office : glisser-déposer de fichiers sur la page, ouverture du formulaire d'envoi pré-rempli, enregistrement,
// puis nettoyage. Prérequis : serveur lancé, compte admin de développement (admin@uaz.test / password).
// Usage : node tests/e2e/media-dragdrop.mjs
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const php = process.env.PHP ?? 'php';
const tinker = (code) => execFileSync(php, ['artisan', 'tinker', '--execute', code], { encoding: 'utf8' }).trim();
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

const { default: sharp } = await import('sharp');
const stamp = Date.now();
const name = `depot-test-${stamp}.jpg`;
const file = path.join(os.tmpdir(), name);
await sharp({ create: { width: 1200, height: 800, channels: 3, background: '#8A724B' } }).jpeg().toFile(file);

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'fr-FR' })).newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));

try {
    await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
    await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: 'Connexion' }).click();
    await page.waitForURL('**/admin', { timeout: 20000 });

    await page.goto(`${base}/admin/media-assets`, { waitUntil: 'networkidle' });
    check(await page.getByText('Glissez-déposez vos images ici').isVisible(), 'la zone de dépôt est affichée');

    // Dépôt simulé : un vrai DataTransfer avec le fichier, sur la fenêtre.
    const bytes = fs.readFileSync(file).toString('base64');
    await page.evaluate(
        async ({ bytes, name }) => {
            const blob = new Blob([Uint8Array.from(atob(bytes), (c) => c.charCodeAt(0))], { type: 'image/jpeg' });
            const dt = new DataTransfer();
            dt.items.add(new File([blob], name, { type: 'image/jpeg' }));
            window.dispatchEvent(new DragEvent('dragenter', { dataTransfer: dt, bubbles: true, cancelable: true }));
            window.dispatchEvent(new DragEvent('drop', { dataTransfer: dt, bubbles: true, cancelable: true }));
        },
        { bytes, name },
    );

    const modal = page.locator('.fi-modal-window').filter({ hasText: 'Envoyer plusieurs images' }).last();
    await modal.waitFor({ timeout: 20000 });
    check(true, 'le formulaire d\'envoi s\'ouvre après le dépôt');
    check(await modal.getByText(name, { exact: false }).count() > 0, 'le fichier déposé est déjà dans le formulaire');

    await modal.getByLabel(name).fill('Aplat bronze de test');
    await modal.getByRole('button', { name: /Enregistrer dans la médiathèque/ }).click();
    await page.waitForTimeout(4000);
    const found = tinker(`echo App\\Models\\MediaAsset::where('alt','Aplat bronze de test')->count();`).split('\n').pop();
    check(found === '1', 'le média est créé avec son texte alternatif');
    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    await page.screenshot({ path: path.join(os.tmpdir(), 'uaz-dragdrop-error.png'), fullPage: true });
    failures++;
} finally {
    await browser.close();
    tinker(`App\\Models\\MediaAsset::where('alt','Aplat bronze de test')->get()->each->delete();`);
    fs.rmSync(file, { force: true });
}

process.exit(failures ? 1 : 0);
