// Back-office en mode SPA : la navigation entre écrans ne recharge pas le document (un marqueur posé sur window survit), le retour
// arrière fonctionne, et les composants personnalisés (zone de dépôt de la médiathèque) se montent après une navigation.
// Prérequis : serveur lancé, compte admin de développement. Usage : node tests/e2e/admin-spa.mjs
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' })).newPage();
const errors = [];
// Le serveur PHP intégré (mono-processus sous Windows) laisse parfois tomber un fichier JS de Filament : ces erreurs de chargement de
// ressource (« is not defined », « Failed to fetch dynamically imported module ») sont ignorées, pas les autres.
page.on('pageerror', (e) => !/is not defined|Failed to fetch dynamically imported module/.test(e.message) && errors.push(e.message.slice(0, 140)));

try {
    await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
    await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: 'Connexion' }).click();
    await page.waitForURL('**/admin', { timeout: 20000 });
    await page.waitForLoadState('networkidle');

    await page.evaluate(() => (window.__spa = true));
    const same = () => page.evaluate(() => window.__spa === true);
    const here = () => page.url().replace(base, '');

    check((await page.locator('a[wire\\:navigate], a[wire\\:navigate\\.hover]').count()) > 5, 'les liens du menu utilisent la navigation SPA (wire:navigate)');

    await page.getByRole('link', { name: 'Pages', exact: true }).click();
    await page.waitForURL('**/content-pages/pages', { timeout: 15000 });
    await page.waitForLoadState('networkidle');
    check(await same(), 'menu → Pages : le document n\'est pas rechargé');

    await page.locator('a[href$="/edit"]').first().click();
    await page.waitForURL('**/edit', { timeout: 15000 });
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);
    check(await same(), 'Pages → Modifier : le document n\'est pas rechargé');
    check((await page.locator('h1').first().innerText()).startsWith('Modifier'), 'la page de modification est bien affichée et stylée');

    await page.getByRole('link', { name: 'Médiathèque' }).click();
    await page.waitForURL('**/media-assets', { timeout: 15000 });
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800);
    check(await same(), 'menu → Médiathèque : le document n\'est pas rechargé');
    check((await page.getByText('Glissez-déposez vos images ici').count()) === 1, 'la zone de dépôt est présente après la navigation');

    await page.goBack();
    await page.waitForTimeout(1500);
    check((await same()) && /\/edit$/.test(here()), 'retour arrière : écran précédent restauré sans rechargement');
    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
}

process.exit(failures ? 1 : 0);
