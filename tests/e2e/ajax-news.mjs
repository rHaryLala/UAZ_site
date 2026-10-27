// Catégories et pagination de /actualites sans rechargement complet (même principe que /formations).
// Usage : node tests/e2e/ajax-news.mjs
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
const errors = [];
page.on('pageerror', (e) => !/Transition was aborted/.test(e.message) && errors.push(e.message));
await page.goto(`${base}/actualites`, { waitUntil: 'networkidle' });
await page.waitForTimeout(800);
await page.evaluate(() => (window.__noReload = true));
const noReload = () => page.evaluate(() => window.__noReload === true);
const firstTitle = () => page.locator('[data-ajax-region] ul > li h2').first().innerText();

const first = await firstTitle();
const categories = page.locator('[data-ajax-region] nav[aria-label="Catégories d\'actualités"] a:not([aria-current])');
if (await categories.count()) {
    const category = categories.first();
    const categoryHref = await category.getAttribute('href');
    await category.click();
    await page.waitForURL(`**${new URL(categoryHref, base).search}`);
    await page.waitForTimeout(700);
    check(await noReload(), 'catégorie : la page n\'a pas été rechargée');
    check(page.url().includes('categorie='), 'catégorie : adresse mise à jour');
    check((await page.locator('[data-ajax-region] nav[aria-label="Catégories d\'actualités"] a[aria-current="true"]').innerText()) !== 'Toutes', 'catégorie : bouton actif mis à jour');

    await page.goBack();
    await page.waitForTimeout(700);
    check(await noReload() && !page.url().includes('categorie='), 'précédent : retour à toutes les actualités sans rechargement');
    check((await firstTitle()) === first, 'précédent : la liste d\'origine est restaurée');
} else {
    console.log('– filtre par catégorie non testé (une seule catégorie contient des actualités)');
}

const next = page.locator('[data-ajax-region] a[rel="next"]').first();
if (await next.count()) {
    await next.scrollIntoViewIfNeeded();
    await next.click();
    await page.waitForURL('**page=2');
    await page.waitForTimeout(900);
    check(await noReload(), 'pagination : pas de rechargement');
    check((await firstTitle()) !== first, 'pagination : nouvelles actualités affichées');
    check((await page.evaluate(() => document.querySelector('[data-ajax-region]').getBoundingClientRect().top)) > -50, 'pagination : retour en haut de la liste');
} else {
    console.log('– pagination non testée (une seule page)');
}

await page.locator('[data-ajax-region] ul a').first().click();
await page.waitForURL(/\/actualites\/.+/);
check(/\/actualites\/.+/.test(page.url()), 'un lien de carte ouvre l\'article');
check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' — ' + errors.join('; ') : ''}`);

await browser.close();
process.exit(failures ? 1 : 0);
