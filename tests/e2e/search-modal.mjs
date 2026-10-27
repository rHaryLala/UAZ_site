// Fenêtre de recherche de l'en-tête dans un vrai navigateur : ouverture par la loupe et par Ctrl+K, résultats en direct,
// navigation au clavier, fermeture (Échap), retour du focus, et même comportement sur une page Livewire.
// Prérequis : serveur lancé avec des données. Usage : node tests/e2e/search-modal.mjs [chemin-de-sortie-captures]
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const outDir = process.argv[2];
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

const browser = await chromium.launch();

for (const [name, viewport, route] of [
    ['bureau', { width: 1440, height: 900 }, '/'],
    ['mobile', { width: 390, height: 844 }, '/'],
    ['page Livewire', { width: 1440, height: 900 }, '/contact'],
]) {
    const page = await (await browser.newContext({ viewport })).newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(base + route, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Alpine?.version, null, { timeout: 5000 }).catch(() => {});

    const modal = page.locator('.search-modal');
    const input = page.getByRole('searchbox', { name: 'Rechercher sur le site' }).last();
    const loupe = page.getByRole('link', { name: 'Rechercher sur le site' }).first();

    check((await modal.getAttribute('data-open')) !== 'true', `${name} : fenêtre fermée au chargement`);
    await loupe.click();
    await page.waitForTimeout(500);
    check((await modal.getAttribute('data-open')) === 'true', `${name} : la loupe ouvre la fenêtre (pas de changement de page)`);
    check(new URL(page.url()).pathname === route, `${name} : l'adresse n'a pas changé`);
    check(await input.evaluate((el) => el === document.activeElement), `${name} : le champ est actif`);

    await input.fill('licence');
    await page.waitForSelector('[data-search-result]', { timeout: 6000 }).catch(() => {});
    const count = await page.locator('[data-search-result]').count();
    check(count >= 2, `${name} : résultats affichés en direct (${count})`);
    check((await page.locator('.search-modal [role=dialog] h3').count()) >= 1, `${name} : résultats groupés par catégorie`);

    if (outDir) await page.screenshot({ path: path.join(outDir, `recherche-${name.replace(/\s+/g, '-')}.png`) });

    await page.keyboard.press('ArrowDown');
    check(await page.evaluate(() => document.activeElement?.hasAttribute('data-search-result')), `${name} : ↓ place le focus sur le premier résultat`);
    await page.keyboard.press('ArrowUp');
    check(await input.evaluate((el) => el === document.activeElement), `${name} : ↑ revient au champ`);

    await input.fill('zzzzqqqq');
    await page.waitForFunction(() => document.querySelector('.search-modal')?.textContent.includes('Aucun résultat'), null, { timeout: 6000 }).catch(() => {});
    check((await modal.innerText()).includes('Aucun résultat'), `${name} : message clair sans résultat`);

    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);
    check((await modal.getAttribute('data-open')) !== 'true', `${name} : Échap ferme la fenêtre`);
    check(await loupe.evaluate((el) => el === document.activeElement), `${name} : le focus revient à la loupe`);

    await page.keyboard.press('Control+k');
    await page.waitForTimeout(500);
    check((await modal.getAttribute('data-open')) === 'true', `${name} : Ctrl+K ouvre la fenêtre`);

    // Entrée : page de résultats complète
    await input.fill('licence');
    await page.keyboard.press('Enter');
    await page.waitForURL('**/recherche?q=licence', { timeout: 8000 }).catch(() => {});
    check(page.url().includes('/recherche?q=licence'), `${name} : Entrée mène à la page de résultats`);
    check(errors.length === 0, `${name} : aucune erreur JavaScript${errors.length ? ' — ' + errors.join('; ') : ''}`);
    await page.context().close();
}

await browser.close();
process.exit(failures ? 1 : 0);
