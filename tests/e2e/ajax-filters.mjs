// Filtres de /formations sans rechargement complet : un marqueur posé sur window doit survivre aux clics, l'adresse et le
// nombre de résultats changent, précédent/suivant fonctionnent, et un lien de carte ouvre bien la fiche.
// Usage : node tests/e2e/ajax-filters.mjs
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
// Le navigateur signale parfois l'interruption d'une transition de page (View Transitions) : sans conséquence.
page.on('pageerror', (e) => !/Transition was aborted/.test(e.message) && errors.push(e.message));
await page.goto(`${base}/formations`, { waitUntil: 'networkidle' });
await page.waitForTimeout(800);
await page.evaluate(() => (window.__noReload = true));

const count = () => page.locator('[data-ajax-region] ul > li').count();
const all = await count();
check(all > 1, `toutes les formations affichées (${all})`);

await page.locator('[data-ajax-region] a', { hasText: 'Licence' }).first().click();
await page.waitForURL('**/formations?niveau=licence');
await page.waitForTimeout(700);
check(await page.evaluate(() => window.__noReload === true), "filtre Niveau : la page n'a pas été rechargée");
check(page.url().endsWith('/formations?niveau=licence'), "l'adresse est mise à jour");
check((await page.locator('[data-ajax-region] a[aria-current="true"]').first().innerText()).length > 0, 'le bouton actif est mis à jour');
check((await page.locator('[data-ajax-region] ul > li:visible').count()) >= 1, 'les cartes sont visibles (animation terminée)');

const faculty = page.locator('[data-ajax-region] a', { hasText: 'Arts' }).first();
if (await faculty.count()) {
    await faculty.click();
    await page.waitForURL('**/formations?niveau=licence&faculte=*');
    await page.waitForTimeout(700);
    check(await page.evaluate(() => window.__noReload === true), 'filtre Domaine combiné : pas de rechargement');
    check(page.url().includes('niveau=licence&faculte='), "les deux filtres sont dans l'adresse");
    await page.goBack();
    await page.waitForTimeout(700);
}

await page.goBack();
await page.waitForTimeout(700);
check(await page.evaluate(() => window.__noReload === true), 'précédent : pas de rechargement');
check(page.url().endsWith('/formations') && (await count()) === all, 'précédent : retour à la liste complète');

// Un lien de carte mène à la fiche (chargement normal).
await page.locator('[data-ajax-region] ul a').first().click();
await page.waitForURL(/\/formations\/.+/);
check(/\/formations\/.+/.test(page.url()), 'un lien de carte ouvre la fiche de la formation');

// Accès direct à une adresse filtrée : même résultat côté serveur.
await page.goto(`${base}/formations?niveau=licence`, { waitUntil: 'networkidle' });
check(page.url().includes('niveau=licence') && (await count()) >= 1, 'adresse filtrée ouverte directement : fonctionne');
check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' — ' + errors.join('; ') : ''}`);

await browser.close();
process.exit(failures ? 1 : 0);
