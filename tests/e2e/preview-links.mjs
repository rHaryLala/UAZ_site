// L'image d'aperçu d'une vignette mène à la page (comme le titre) : /galerie (albums) et /actualites (à la une et vignettes).
// Albums d'essai : tests/e2e/fixtures/gallery-albums.php. Prérequis : serveur lancé. Usage : node tests/e2e/preview-links.mjs
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const fixture = path.join(path.dirname(fileURLToPath(import.meta.url)), 'fixtures', 'gallery-albums.php');
const albums = (action) => execFileSync(process.env.PHP_BIN ?? 'php', [fixture, action], { encoding: 'utf8' });
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

albums('up');
const browser = await chromium.launch();
try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' });
    await context.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));
    const page = await context.newPage();

    // Clique au centre de l'image visible, comme le ferait un visiteur, puis vérifie l'adresse atteinte.
    const clickImage = async (url, selector, expected, label) => {
        await page.goto(base + url, { waitUntil: 'load', timeout: 90000 });
        await wait(2200);
        const media = page.locator(selector).first();
        await media.scrollIntoViewIfNeeded();
        await wait(1800);
        const target = await media.evaluate((el) => {
            const r = el.getBoundingClientRect();
            const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
            return { link: hit?.closest('a')?.getAttribute('href') ?? null };
        });
        check(!!target.link && expected.test(target.link), `${label} : le centre de l'image est un lien (${target.link})`);
        const box = await media.boundingBox();
        await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
        await page.waitForURL(expected, { timeout: 15000 }).then(() => check(true, `${label} : un clic sur l'image ouvre la page`)).catch(() => check(false, `${label} : un clic sur l'image ouvre la page (adresse : ${page.url()})`));
    };

    await clickImage('/galerie', '.gal-frame__mat', /\/galerie\/[a-z0-9-]+$/, 'galerie');
    await clickImage('/actualites', '.nws-card__media', /\/actualites\/[a-z0-9-]+$/, 'actualités (vignette)');
    await clickImage('/actualites', '.nws-lead__media', /\/actualites\/[a-z0-9-]+$/, 'actualités (à la une)');

    // Accessibilité : un seul lien par vignette dans l'ordre de tabulation (le titre) ; le doublon de l'image est caché.
    await page.goto(`${base}/galerie`, { waitUntil: 'load' });
    await wait(1500);
    const dup = await page.locator('.gal-media-link').first().evaluate((a) => [a.getAttribute('tabindex'), a.getAttribute('aria-hidden')]);
    check(dup.join() === '-1,true', 'le lien de l\'image est hors tabulation et caché aux lecteurs d\'écran');
    await context.close();
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
    albums('down');
}

process.exit(failures ? 1 : 0);
