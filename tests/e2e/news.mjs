// Actualités dans un vrai navigateur : titre de journal intact, ruban, article à la une et cartes dévoilés, curseur, filtre par rubrique et
// page suivante sans rechargement, page d'un article (barre de lecture, lettrine, intertitres, citation, photo, partage, article voisin),
// mobile, mouvement réduit, anglais.
// Crée trois articles d'essai « E2E … » dans une rubrique « E2E Rubrique » (tests/e2e/fixtures/news.php) puis les supprime ; les autres articles
// viennent de la base de développement (au moins neuf, pour avoir une page suivante). Usage : node tests/e2e/news.mjs [dossier-captures]
// Variable PHP_BIN : exécutable PHP (par défaut « php »).
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
const fixture = path.join(path.dirname(fileURLToPath(import.meta.url)), 'fixtures', 'news.php');
const news = (action) => execFileSync(process.env.PHP_BIN ?? 'php', [fixture, action], { encoding: 'utf8' });

let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};
const until = async (fn, ms = 20000) => {
    const end = Date.now() + ms;
    while (Date.now() < end) {
        try { if (await fn()) return true; } catch { /* page en cours de chargement */ }
        await new Promise((r) => setTimeout(r, 200));
    }
    return false;
};
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

const browser = await chromium.launch();
const errors = [];
const open = async (options, url = '/actualites') => {
    const context = await browser.newContext({ locale: 'fr-FR', permissions: ['clipboard-read', 'clipboard-write'], ...options });
    await context.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));
    const page = await context.newPage();
    page.on('pageerror', (e) => !/ViewTransition|Transition was aborted/.test(e.message) && errors.push(e.message.slice(0, 140)));
    await page.goto(base + url, { waitUntil: 'load', timeout: 90000 });

    return page;
};
const scrollTo = async (page, selector, offset = 0) => {
    const top = await page.locator(selector).first().evaluate((el) => el.getBoundingClientRect().top + window.scrollY);
    for (let y = await page.evaluate(() => scrollY); y < top + offset; y += 400) {
        await page.evaluate((v) => window.scrollTo(0, v), y);
        await wait(50);
    }
    await page.evaluate((v) => window.scrollTo(0, v), top + offset);
};
const scrollAll = async (page) => {
    const height = await page.evaluate(() => document.documentElement.scrollHeight);
    for (let y = 0; y < height; y += 350) {
        await page.evaluate((v) => window.scrollTo(0, v), y);
        await wait(40);
    }
};

try {
    news('up');

    // ── Liste, ordinateur ──
    const page = await open({ viewport: { width: 1440, height: 900 } });
    await page.evaluate(() => { window.__marker = 'same page'; });
    check(await until(async () => page.evaluate(() => document.documentElement.classList.contains('nws-ready'))), 'le mode immersif s\'active (nws-ready)');
    check(await until(async () => (await page.locator('[data-nws-title] .char').count()) >= 10), 'le titre se découpe en lettres');
    check(await page.locator('[data-nws-title] .word').evaluateAll((els) => els.every((el) => el.getBoundingClientRect().height < 1.4 * parseFloat(getComputedStyle(el).fontSize))), 'aucun mot du titre n\'est coupé en deux lignes');
    const before = await page.locator('[data-nws-ticker-track]').evaluate((el) => getComputedStyle(el).transform);
    await wait(1500);
    check(before !== await page.locator('[data-nws-ticker-track]').evaluate((el) => getComputedStyle(el).transform), 'le ruban des derniers titres défile');
    if (out) await page.screenshot({ path: path.join(out, 'actualites-une.png') });

    await scrollTo(page, '[data-nws-wall]', 100);
    await wait(2200);
    check(await page.locator('[data-nws-lead]').count() === 1, 'un seul article « à la une »');
    check((await page.locator('.nws-lead__title').innerText()).includes('E2E Alpha'), 'la une est l\'article le plus récent');
    check(await until(async () => page.locator('[data-nws-lead] [data-nws-media]').evaluate((el) => getComputedStyle(el).clipPath === 'none'), 5000), 'la photo de la une est dévoilée (rideau levé)');
    check(await page.locator('[data-nws-lead]').evaluate((el) => getComputedStyle(el).getPropertyValue('--line').trim() === '1'), 'le filet de la une est tracé');
    const box = await page.locator('[data-nws-lead] [data-nws-link]').boundingBox();
    await page.mouse.move(box.x + 20, box.y + 10, { steps: 6 });
    check(await until(async () => Number(await page.locator('[data-nws-cursor]').first().evaluate((el) => getComputedStyle(el).opacity)) > 0.9, 3000), 'le curseur « Lire » apparaît au-dessus de la une');
    check(await until(async () => page.locator('.nws-lead__title .nws-mark').evaluate((el) => getComputedStyle(el).backgroundSize.startsWith('100%')), 3000), 'le titre est surligné au survol');
    if (out) await page.screenshot({ path: path.join(out, 'actualites-fil.png') });
    await page.mouse.move(5, 5);

    await scrollAll(page);
    await wait(1500);
    check(await page.locator('[data-nws-cell] [data-nws-card]').evaluateAll((els) => els.length >= 6 && els.every((el) => Number(getComputedStyle(el).getPropertyValue('--line')) === 1)), 'toutes les cartes du fil se sont dévoilées');

    // Filtre par rubrique : sans rechargement de la page.
    await scrollTo(page, '.nws-tabs', -150);
    check(await page.locator('.nws-tab').count() >= 3, 'les rubriques sont proposées');
    await page.locator('.nws-tab', { hasText: 'E2E Rubrique' }).click();
    check(await until(async () => page.url().includes('categorie=e2e-rubrique'), 6000), 'filtre : l\'adresse change');
    check(await until(async () => (await page.locator('[data-nws-cell]').count()) === 2, 6000), 'filtre : trois articles dans la rubrique (une à la une + deux dans le fil)');
    check(await page.evaluate(() => window.__marker) === 'same page', 'filtre : la page n\'est pas rechargée');
    await scrollAll(page);
    await wait(1800);
    check(await page.locator('[data-nws-card]').evaluateAll((els) => els.length === 3 && els.every((el) => el.dataset.nwsDone === '1' && Number(getComputedStyle(el).getPropertyValue('--line')) === 1)), 'filtre : les nouvelles cartes se dévoilent aussi');
    await page.locator('.nws-tab', { hasText: 'Toutes' }).click();
    check(await until(async () => (await page.locator('[data-nws-cell]').count()) === 8, 6000), 'filtre « Toutes » : retour à la liste complète');

    // Page suivante : la une disparaît.
    await scrollAll(page);
    const next = page.locator('.nws-pages a[rel="next"]').first();
    await next.scrollIntoViewIfNeeded();
    await next.click();
    check(await until(async () => (await page.locator('[data-nws-lead]').count()) === 0, 6000), 'page 2 : plus d\'article à la une');
    check(await page.evaluate(() => window.__marker) === 'same page', 'page 2 : la page n\'est pas rechargée');
    await page.context().close();

    // ── Article, ordinateur ──
    const article = await open({ viewport: { width: 1440, height: 900 } }, '/actualites/e2e-beta');
    check(await until(async () => article.evaluate(() => document.documentElement.classList.contains('nws-ready'))), 'article : le mode immersif s\'active');
    check(await article.locator('[data-nws-cover]').count() === 1, 'article : la photo d\'ouverture est présente');
    check(await until(async () => (await article.locator('[data-nws-title] .word').count()) >= 1, 5000), 'article : le titre se découpe en mots');
    await wait(2500);
    if (out) await article.screenshot({ path: path.join(out, 'article-ouverture.png') });
    check((await article.locator('[data-nws-progress] i').evaluate((el) => new DOMMatrix(getComputedStyle(el).transform).a)) < 0.05, 'la barre de lecture est vide au début');
    check(await article.locator('.nws-prose .prose > p').first().evaluate((el) => getComputedStyle(el, '::first-letter').float === 'left'), 'le premier paragraphe porte une lettrine');
    await scrollTo(article, '.nws-prose .prose > h2', -200);
    await wait(2200);
    check(await article.locator('.nws-prose .prose > h2').first().evaluate((el) => getComputedStyle(el).getPropertyValue('--rule').trim() === '1'), 'l\'intertitre trace son filet');
    check((await article.locator('[data-nws-progress] i').evaluate((el) => new DOMMatrix(getComputedStyle(el).transform).a)) > 0.05, 'la barre de lecture avance avec le défilement');
    await scrollTo(article, '.nws-prose .prose figure', -300);
    check(await until(async () => article.locator('.nws-prose .prose figure').evaluate((el) => getComputedStyle(el).clipPath === 'none'), 5000), 'la photo du texte est dévoilée (rideau levé)');
    check(await article.locator('.nws-prose .prose blockquote').evaluate((el) => getComputedStyle(el).fontStyle === 'italic'), 'la citation est mise en valeur');
    // Le rail de partage reste à portée.
    const shareTop = await article.locator('.nws-share').evaluate((el) => el.getBoundingClientRect().top);
    check(shareTop > 60 && shareTop < 400, `le rail de partage reste à portée en lisant (${Math.round(shareTop)} px du haut)`);
    await article.locator('[data-nws-copy]').click();
    check(await until(async () => (await article.locator('[data-nws-copy]').innerText()).trim() === 'Copié', 3000), 'copier le lien : le bouton confirme');
    check((await article.evaluate(() => navigator.clipboard.readText())).endsWith('/actualites/e2e-beta'), 'copier le lien : l\'adresse est dans le presse-papiers');
    await scrollTo(article, '[data-nws-pager]', -200);
    await wait(1500);
    if (out) await article.screenshot({ path: path.join(out, 'article-suite.png') });
    check(await article.locator('[data-nws-pager-link]').count() === 2, 'article : l\'article précédent et le suivant sont proposés');
    const older = article.locator('.nws-pager__link.is-older');
    await older.click();
    check(await until(async () => article.url().endsWith('/actualites/e2e-gamma'), 8000), 'article : le lien « précédent » mène à l\'article plus ancien');
    await article.context().close();

    // ── Mobile ──
    const mobile = await open({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    await until(async () => mobile.evaluate(() => document.documentElement.classList.contains('nws-ready')));
    await scrollAll(mobile);
    await wait(1200);
    check((await mobile.evaluate(() => document.documentElement.scrollWidth)) <= 390, 'mobile : aucun défilement horizontal (liste)');
    check(!(await mobile.locator('[data-nws-wall]').evaluate((el) => el.classList.contains('is-fine'))), 'mobile : pas de curseur');
    if (out) { await mobile.evaluate(() => scrollTo(0, 0)); await mobile.waitForTimeout(600); await mobile.screenshot({ path: path.join(out, 'actualites-mobile.png') }); }
    await mobile.goto(base + '/actualites/e2e-beta', { waitUntil: 'load' });
    await scrollAll(mobile);
    await wait(1200);
    check((await mobile.evaluate(() => document.documentElement.scrollWidth)) <= 390, 'mobile : aucun défilement horizontal (article)');
    check(await mobile.locator('[data-nws-copy]').isVisible(), 'mobile : le partage est sous le texte');
    await mobile.context().close();

    // ── Mouvement réduit ──
    const calm = await open({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    await wait(1200);
    check(await calm.locator('[data-nws-title]').evaluate((el) => Number(getComputedStyle(el).opacity) === 1 && el.querySelectorAll('.char').length === 0), 'mouvement réduit : le titre est visible, non découpé');
    check(await calm.locator('[data-nws-card]').evaluateAll((els) => els.length >= 6 && els.every((el) => getComputedStyle(el).getPropertyValue('--line') === '' && getComputedStyle(el.querySelector('[data-nws-media]')).clipPath === 'none')), 'mouvement réduit : toutes les cartes sont visibles');
    await calm.goto(base + '/actualites/e2e-beta', { waitUntil: 'load' });
    await wait(800);
    check(!(await calm.locator('[data-nws-progress]').isVisible()), 'mouvement réduit : pas de barre de lecture');
    check(await calm.locator('.nws-prose .prose > h2').first().evaluate((el) => getComputedStyle(el).getPropertyValue('--rule').trim() === '1'), 'mouvement réduit : le filet des intertitres est tracé');
    await calm.locator('[data-nws-copy]').click();
    check(await until(async () => (await calm.locator('[data-nws-copy]').innerText()).trim() === 'Copié', 3000), 'mouvement réduit : copier le lien fonctionne');
    await calm.context().close();

    // ── Anglais ──
    const en = await open({ viewport: { width: 1440, height: 900 } }, '/en/actualites');
    check(await en.getAttribute('html', 'lang') === 'en', 'anglais : <html lang="en">');
    await wait(2500);
    check((await en.locator('.nws-lead__flag').innerText()).trim().toLowerCase() === 'top story', 'anglais : « Top story »');
    await en.goto(base + '/en/actualites/e2e-beta', { waitUntil: 'load' });
    await wait(2500);
    check((await en.locator('.nws-open__meta').innerText()).toLowerCase().includes('min read'), 'anglais : « min read »');
    await en.context().close();

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
    try { news('down'); } catch (error) { console.log(`✗ nettoyage des actualités d'essai impossible : ${error.message.split('\n')[0]}`); failures++; }
}

process.exit(failures ? 1 : 0);
