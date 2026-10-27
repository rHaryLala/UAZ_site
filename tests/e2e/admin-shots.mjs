// Captures du back-office pour docs/GUIDE-REDACTEUR.md (compte de développement : admin@uaz.test).
// Usage : node tests/e2e/admin-shots.mjs [dossier=docs/screenshots]   (BASE_URL, ADMIN_EMAIL, ADMIN_PASSWORD pour changer)
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

const out = process.argv[2] ?? 'docs/screenshots';
const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
fs.mkdirSync(out, { recursive: true });

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1360, height: 860 }, locale: 'fr-FR' });
const page = await context.newPage();

async function shot(name, { fullPage = false, clip } = {}) {
    await page.waitForTimeout(900);
    await page.screenshot({ path: path.join(out, `${name}.png`), fullPage, clip });
    console.log(`✓ ${name}.png`);
}

// Connexion
await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
await shot('01-connexion');
await page.waitForFunction(() => window.Livewire, null, { timeout: 8000 });
await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
await page.getByRole('button', { name: /Connexion|Se connecter/ }).click();
await page.waitForURL('**/admin', { timeout: 15000 });
await page.waitForLoadState('networkidle');
await shot('02-tableau-de-bord');

// Actualités : liste, formulaire de création
await page.goto(`${base}/admin/news-posts`, { waitUntil: 'networkidle' });
await shot('03-actualites-liste');
await page.goto(`${base}/admin/news-posts/create`, { waitUntil: 'networkidle' });
await shot('04-actualite-formulaire', { fullPage: true });

// Médiathèque et envoi multiple
await page.goto(`${base}/admin/media-assets`, { waitUntil: 'networkidle' });
await shot('05-mediatheque');
await page.getByRole('button', { name: 'Envoi multiple' }).click();
await page.waitForTimeout(800);
await shot('06-envoi-multiple');
await page.keyboard.press('Escape');

// Pages et blocs
await page.goto(`${base}/admin/pages`, { waitUntil: 'networkidle' });
await shot('07-pages-liste');
await page.goto(`${base}/admin/pages/1/edit`);
await page.waitForLoadState('networkidle');
await shot('08-page-edition', { fullPage: true });

// Menus, réglages, messages
await page.goto(`${base}/admin/menus`, { waitUntil: 'networkidle' });
await shot('09-menus');
await page.goto(`${base}/admin/manage-site-settings`, { waitUntil: 'networkidle' });
await shot('10-reglages');
await page.goto(`${base}/admin/contact-messages`, { waitUntil: 'networkidle' });
await shot('11-messages');

await browser.close();
