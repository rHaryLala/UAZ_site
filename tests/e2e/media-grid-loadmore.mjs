// Médiathèque en grille : chargement progressif « façon WordPress » (au défilement ou au clic sur « Charger plus »).
// Prérequis : serveur lancé, compte admin de développement. Usage : node tests/e2e/media-grid-loadmore.mjs
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
let failures = 0;
const check = (ok, label) => { console.log(`${ok ? '✓' : '✗'} ${label}`); if (!ok) failures++; };

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' })).newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
const cards = () => page.locator('.fi-ta-content-grid > *').count();

try {
    await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
    await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: 'Connexion' }).click();
    await page.waitForURL('**/admin', { timeout: 20000 });

    await page.goto(`${base}/admin/media-assets`, { waitUntil: 'networkidle' });
    if (await page.getByRole('button', { name: 'Vue grille' }).count()) {
        await page.getByRole('button', { name: 'Vue grille' }).click();
        await page.waitForLoadState('networkidle');
    }
    await page.waitForSelector('.fi-media-more', { timeout: 15000 });
    await page.waitForFunction(() => document.querySelectorAll('.fi-ta-content-grid > *').length > 0, null, { timeout: 15000 });
    const first = await cards();
    check(first === 36, `la grille affiche d'abord 36 fichiers (${first})`);
    check((await page.locator('.fi-pagination').first().isVisible().catch(() => false)) === false, 'plus de numéros de page en grille');

    await page.locator('.fi-media-more').scrollIntoViewIfNeeded();
    await page.waitForFunction((n) => document.querySelectorAll('.fi-ta-content-grid > *').length > n, first, { timeout: 15000 });
    const second = await cards();
    check(second > first, `en descendant, la suite se charge toute seule (${second})`);

    const text = await page.locator('.fi-media-more p').innerText();
    check(/\d+ sur \d+ fichier/.test(text), `compteur affiché : « ${text} »`);
    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
}
process.exit(failures ? 1 : 0);
