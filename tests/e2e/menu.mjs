// Vérifie le menu plein écran : ouverture au clic, liens visibles, focus piégé, fermeture par Échap,
// retour du focus sur le bouton. Usage : node tests/e2e/menu.mjs [chemin=/design-system] [dossier-captures]
import path from 'node:path';
import { chromium } from '@playwright/test';

const [route = '/design-system', outDir] = process.argv.slice(2);
const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const browser = await chromium.launch();
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

for (const [name, viewport] of [['mobile', { width: 390, height: 844 }], ['bureau', { width: 1440, height: 900 }]]) {
    const page = await (await browser.newContext({ viewport })).newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(base + route, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Alpine?.version, null, { timeout: 5000 }).catch(() => {});

    const menu = page.locator('#site-menu');
    const button = page.getByRole('button', { name: /^Menu$/ });

    check((await menu.getAttribute('data-open')) !== 'true', `${name} : menu fermé au chargement`);
    await button.click();
    await page.waitForTimeout(1400);
    check((await menu.getAttribute('data-open')) === 'true', `${name} : menu ouvert après clic`);
    check(await menu.getByRole('link', { name: 'Formations' }).first().isVisible(), `${name} : liens visibles`);
    check(await menu.getByRole('link', { name: 'Téléchargements' }).first().isVisible(), `${name} : ressources et autres pages visibles`);
    check((await button.getAttribute('aria-expanded')) === 'true', `${name} : aria-expanded = true`);
    check(await page.evaluate(() => document.documentElement.classList.contains('menu-open')), `${name} : défilement de la page figé`);

    // Tab ne doit jamais sortir du menu (focus piégé).
    for (let i = 0; i < 25; i++) await page.keyboard.press('Tab');
    check(await page.evaluate(() => document.getElementById('site-menu').contains(document.activeElement)), `${name} : focus piégé dans le menu`);

    if (outDir) await page.screenshot({ path: path.join(outDir, `menu-${name}.png`) });

    await page.keyboard.press('Escape');
    await page.waitForTimeout(1000);
    check((await menu.getAttribute('data-open')) !== 'true', `${name} : Échap ferme le menu`);
    check(await button.evaluate((el) => el === document.activeElement), `${name} : focus rendu au bouton Menu`);
    check(errors.length === 0, `${name} : aucune erreur JavaScript${errors.length ? ' — ' + errors.join('; ') : ''}`);
}

await browser.close();
process.exit(failures ? 1 : 0);
