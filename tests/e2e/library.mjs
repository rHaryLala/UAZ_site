// Page « Bibliothèque » dans un vrai navigateur : chaque scène à sa position de défilement (captures), puis les interactions
// (rayonnage, onglets d'horaires, état « ouvert »). Prérequis : serveur lancé. Usage : node tests/e2e/library.mjs [dossier-captures]
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const url = `${base}/vie-etudiante/bibliotheque`;
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

const browser = await chromium.launch();

async function desktop() {
    const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR', timezoneId: 'Europe/Paris' })).newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
    // La fenêtre de la lettre d'information ne doit pas masquer la page pendant le test.
    await page.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));

    await page.goto(url, { waitUntil: 'load' });
    await page.waitForFunction(() => document.querySelector('[data-lib-intro]')?.classList.contains('lib-live'), null, { timeout: 15000 }).catch(() => {});
    check(await page.evaluate(() => document.querySelector('[data-lib-intro]').classList.contains('lib-live')), 'le mode immersif s\'active (lib-live)');
    await page.waitForTimeout(1800);
    if (out) await page.screenshot({ path: path.join(out, '1-entree.png') });

    const shot = async (name, y, wait = 900) => {
        await page.evaluate((top) => (window.__lenis ? window.__lenis.scrollTo(top, { immediate: true }) : window.scrollTo(0, top)), y);
        await page.waitForTimeout(wait);
        if (out) await page.screenshot({ path: path.join(out, name) });
    };
    const top = (selector) => page.evaluate((s) => document.querySelector(s).getBoundingClientRect().top + window.scrollY, selector);

    const introY = await top('[data-lib-stage]');
    const time = () => page.evaluate(() => document.querySelector('[data-lib-video]').currentTime);
    check(await page.evaluate(() => document.querySelector('[data-lib-video]').readyState >= 1), 'vidéo : chargée par le script (métadonnées disponibles)');
    const t0 = await time();
    await shot('2-debut.png', introY + 900, 1800);
    const t1 = await time();
    await shot('3-milieu.png', introY + 1800, 1800);
    const t2 = await time();
    await shot('4-bienvenue.png', introY + 3450, 2000);
    const t3 = await time();
    check(t0 < t1 && t1 < t2 && t2 < t3, `vidéo : plus on défile, plus elle avance (${t0.toFixed(1)} s → ${t1.toFixed(1)} s → ${t2.toFixed(1)} s → ${t3.toFixed(1)} s)`);
    check(t3 > 18 && t3 <= 22.05, `vidéo : la fin de la visite approche 22 s (${t3.toFixed(1)} s)`);
    await shot('3b-retour.png', introY + 900, 1800);
    check((await time()) < t2, 'vidéo : en remontant, elle recule');
    const quoteY = await top('[data-lib-quote]');
    await shot('5-citation.png', quoteY - 250);
    await shot('5b-citation-lue.png', quoteY + 120);
    const panY = await top('[data-lib-pan-sticky]');
    await shot('6-panorama-a.png', panY - 40, 1300);
    await shot('6-panorama-b.png', panY + 1100, 1300);
    await shot('6-panorama-c.png', panY + 2200, 1300);
    const shelfY = await top('[data-lib-shelf]');
    await shot('7-rayonnage.png', shelfY + 80, 1500);

    // Rayonnage : choisir « Ebooks » (3e livre).
    const book = page.locator('[data-lib-book="1"]');
    await book.click();
    await page.waitForTimeout(1000);
    check((await book.getAttribute('aria-pressed')) === 'true', 'rayonnage : le livre cliqué est sélectionné');
    check(await page.locator('[data-lib-panel="1"]').isVisible(), 'rayonnage : sa fiche s\'affiche');
    check(!(await page.locator('[data-lib-panel="0"]').isVisible()), 'rayonnage : la fiche précédente se masque');
    check((await page.locator('[data-lib-panel="1"] a').getAttribute('href')).includes('ebookcentral'), 'rayonnage : la fiche Ebooks propose le lien vers les ebooks');
    await page.keyboard.press('ArrowRight');
    await page.waitForTimeout(500);
    check((await page.locator('[data-lib-book="2"]').getAttribute('aria-pressed')) === 'true', 'rayonnage : la flèche droite passe au livre suivant');
    if (out) await page.screenshot({ path: path.join(out, '7b-rayonnage-choisi.png') });

    // Horaires : état calculé à l'heure de Madagascar + onglets.
    const hoursY = await top('[data-lib-hours]');
    await shot('8-horaires.png', hoursY, 1200);
    const state = await page.locator('[data-lib-status]').getAttribute('data-state');
    const text = await page.locator('[data-lib-status-text]').innerText();
    check(['open', 'closed', 'unknown'].includes(state) && /heure de Madagascar/.test(text), `horaires : état affiché (${state}) « ${text} »`);
    check((await page.locator('tr.is-today').count()) >= 1 || state === 'unknown', 'horaires : la ligne du jour est mise en valeur');
    await page.getByRole('tab', { name: 'Prêt et prolongation' }).click();
    await page.waitForTimeout(500);
    check(await page.locator('[data-lib-table="loan"]').isVisible() && !(await page.locator('[data-lib-table="open"]').isVisible()), 'horaires : l\'onglet « Prêt et prolongation » remplace le tableau');
    if (out) await page.screenshot({ path: path.join(out, '8b-horaires-pret.png') });

    const contactY = await top('[data-lib-contact]');
    await shot('9-bienvenue-final.png', contactY - 100, 2200);

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
}

async function mobile() {
    const page = await (await browser.newContext({ viewport: { width: 390, height: 800 }, locale: 'fr-FR' })).newPage();
    await page.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForTimeout(2000);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    check(overflow <= 1, `mobile : aucun défilement horizontal (${overflow}px)`);
    check(!(await page.evaluate(() => document.querySelector('[data-lib-pan]').classList.contains('is-live'))), 'mobile : le panorama reste en pile (pas de scène horizontale)');
    if (out) await page.screenshot({ path: path.join(out, 'm1-entree.png') });
    await page.evaluate(() => document.querySelector('[data-lib-pan]').scrollIntoView());
    await page.waitForTimeout(1200);
    if (out) await page.screenshot({ path: path.join(out, 'm2-lieu.png') });
    await page.evaluate(() => document.querySelector('[data-lib-shelf]').scrollIntoView());
    await page.waitForTimeout(1500);
    if (out) await page.screenshot({ path: path.join(out, 'm3-rayonnage.png') });
}

async function reducedMotion() {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce', locale: 'fr-FR' });
    const page = await context.newPage();
    await page.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForTimeout(1200);
    check(!(await page.evaluate(() => document.querySelector('[data-lib-intro]').classList.contains('lib-live'))), 'mouvement réduit : pas de scène épinglée');
    check(await page.locator('[data-lib-title]').isVisible(), 'mouvement réduit : le titre est visible');
    check(await page.locator('[data-lib-quote]').isVisible() && (await page.locator('[data-lib-quote]').evaluate((el) => getComputedStyle(el).opacity)) === '1', 'mouvement réduit : la citation est lisible');
    check(await page.locator('[data-lib-card]').first().isVisible(), 'mouvement réduit : les cartes du lieu sont visibles');
    await page.waitForFunction(() => document.querySelector('[data-lib-shelf]')?.classList.contains('is-live'), null, { timeout: 10000 });
    await page.locator('[data-lib-book="1"]').click();
    await page.waitForTimeout(200);
    check(await page.locator('[data-lib-panel="1"]').isVisible() && !(await page.locator('[data-lib-panel="0"]').isVisible()), 'mouvement réduit : le rayonnage reste utilisable');
}

try {
    await desktop();
    await mobile();
    await reducedMotion();
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
}

process.exit(failures ? 1 : 0);
