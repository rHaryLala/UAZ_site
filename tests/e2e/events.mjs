// Page « Événements » dans un vrai navigateur : titre intact, compte à rebours vivant, cadran de l'année (repères à la bonne place), fil du
// programme qui se remplit, cartes dévoilées, curseur, archives en noir et blanc, événement en cours, mobile, mouvement réduit, anglais.
// Crée des événements d'essai « E2E … » (tests/e2e/fixtures/events.php) puis les supprime.
// Prérequis : serveur lancé, base de développement avec des images dans la médiathèque. Usage : node tests/e2e/events.mjs [dossier-captures]
// Variable PHP_BIN : exécutable PHP (par défaut « php »).
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
const fixture = path.join(path.dirname(fileURLToPath(import.meta.url)), 'fixtures', 'events.php');
const events = (action) => execFileSync(process.env.PHP_BIN ?? 'php', [fixture, action], { encoding: 'utf8' });

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
const open = async (options, url = '/evenements') => {
    const context = await browser.newContext({ locale: 'fr-FR', ...options });
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
const unit = (page, name) => page.locator(`[data-unit="${name}"]`).first().innerText().then((t) => Number(t.trim()));

try {
    events('up');

    // ── Ordinateur ──
    const page = await open({ viewport: { width: 1440, height: 900 } });
    check(await until(async () => page.evaluate(() => document.documentElement.classList.contains('evt-ready'))), 'le mode immersif s\'active (evt-ready)');
    check(await until(async () => (await page.locator('[data-evt-title] .char').count()) >= 8), 'le titre se découpe en lettres');
    check(await page.locator('[data-evt-title] .word').evaluateAll((els) => els.every((el) => el.getBoundingClientRect().height < 1.4 * parseFloat(getComputedStyle(el).fontSize))), 'aucun mot du titre n\'est coupé en deux lignes');

    // Compte à rebours : il avance seconde après seconde et reste cohérent avec la date du prochain événement.
    const s1 = await unit(page, 's');
    await wait(2300);
    check((await unit(page, 's')) !== s1, 'le compte à rebours avance chaque seconde');
    const target = await page.locator('[data-evt-countdown]').getAttribute('data-evt-countdown');
    const left = (new Date(target).getTime() - Date.now()) / 1000;
    const shown = (await unit(page, 'd')) * 86400 + (await unit(page, 'h')) * 3600 + (await unit(page, 'm')) * 60 + (await unit(page, 's'));
    check(Math.abs(left - shown) < 3, `les chiffres correspondent au temps restant (${Math.round(left)} s ≈ ${shown} s)`);

    // Cadran : une fois les animations finies, aujourd'hui est à la bonne place sur le cercle.
    await wait(3500);
    check(await page.locator('[data-evt-ring]').evaluate((el) => { const o = parseFloat(getComputedStyle(el).strokeDashoffset); return Number.isNaN(o) || o < 3 || getComputedStyle(el).strokeDasharray === 'none'; }), 'le cercle du cadran est tracé en entier');
    const placed = await page.evaluate(() => {
        const svg = document.querySelector('[data-evt-dial] svg').getBoundingClientRect();
        const cx = svg.left + svg.width / 2;
        const cy = svg.top + svg.height / 2;
        const mark = document.querySelector('[data-evt-today] path').getBoundingClientRect();
        const angle = (Math.atan2(mark.left + mark.width / 2 - cx, -(mark.top + mark.height / 2 - cy)) * 180) / Math.PI;
        // L'angle du dessin dépend de la rotation du cadran au défilement : ici la page est en haut, rotation nulle.
        return { measured: (angle + 360) % 360, expected: Number(document.querySelector('[data-evt-today]').dataset.evtTurn) };
    });
    const gap = Math.min(Math.abs(placed.measured - placed.expected), 360 - Math.abs(placed.measured - placed.expected));
    check(gap < 3, `aujourd'hui est à sa place sur le cadran (${placed.measured.toFixed(1)}° mesurés, ${placed.expected}° attendus)`);
    check(await page.locator('[data-evt-turn]').count() >= 3, 'les événements à venir sont posés sur le cadran');
    if (out) await page.screenshot({ path: path.join(out, 'evenements-hero.png') });

    // Programme : le fil se remplit, les points s'allument, la carte se dévoile.
    await scrollTo(page, '[data-evt-line]', -150);
    await wait(1800);
    check(await page.locator('[data-evt-item]').count() === 5, 'cinq rendez-vous à venir sont listés');
    check(await until(async () => page.locator('[data-evt-node]').first().evaluate((el) => el.classList.contains('is-lit')), 4000), 'le premier point du fil s\'allume');
    check(await page.locator('[data-evt-fill]').evaluate((el) => new DOMMatrix(getComputedStyle(el).transform).d) > 0.02, 'le fil se remplit avec le défilement');
    check(await until(async () => page.locator('[data-evt-card]').first().evaluate((el) => Number(getComputedStyle(el).opacity) > 0.98), 5000), 'la première carte est apparue');
    check(await until(async () => page.locator('[data-evt-media]').first().evaluate((el) => getComputedStyle(el).clipPath === 'none'), 5000), 'la photo de la carte est dévoilée (rideau levé)');

    // Curseur « Voir ».
    const box = await page.locator('[data-evt-link]').first().boundingBox();
    await page.mouse.move(box.x + 60, box.y + 20, { steps: 6 });
    check(await until(async () => Number(await page.locator('[data-evt-cursor]').evaluate((el) => getComputedStyle(el).opacity)) > 0.9, 3000), 'le curseur « Voir » apparaît au-dessus d\'une carte');
    if (out) await page.screenshot({ path: path.join(out, 'evenements-programme.png') });
    await page.mouse.move(5, 5);

    // Archives : en noir et blanc, elles retrouvent leurs couleurs au survol.
    await scrollTo(page, '[data-evt-past]', 200);
    await wait(1800);
    check(await page.locator('[data-evt-stamp]').count() === 7, 'sept événements passés sont archivés');
    check(await page.locator('.evt-stamp__img').first().evaluate((el) => getComputedStyle(el).filter.includes('grayscale')), 'archives : les photos sont en noir et blanc');
    await page.locator('[data-evt-stamp]').first().hover();
    check(await until(async () => page.locator('.evt-stamp__img').first().evaluate((el) => getComputedStyle(el).filter === 'none'), 3000), 'archives : la photo retrouve ses couleurs au survol');
    if (out) await page.screenshot({ path: path.join(out, 'evenements-archives.png') });
    await page.context().close();

    // ── Événement en cours ──
    events('ongoing');
    const live = await open({ viewport: { width: 1440, height: 900 } });
    await wait(2500);
    check(await live.locator('.evt-live').isVisible() && (await live.locator('.evt-live').innerText()).toLowerCase().includes('en cours'), 'événement en cours : « En cours » remplace le compte à rebours');
    check(await live.locator('[data-evt-countdown]').count() === 0, 'événement en cours : pas de compte à rebours');
    await live.context().close();
    events('up');

    // ── Mobile ──
    const mobile = await open({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    await until(async () => mobile.evaluate(() => document.documentElement.classList.contains('evt-ready')));
    check((await mobile.evaluate(() => document.documentElement.scrollWidth)) <= 390, 'mobile : aucun défilement horizontal');
    check(!(await mobile.evaluate(() => document.documentElement.classList.contains('is-fine')) || await mobile.locator('.evt-upcoming').evaluate((el) => el.classList.contains('is-fine'))), 'mobile : pas de curseur');
    await scrollTo(mobile, '[data-evt-line]', -100);
    await wait(2000);
    check(await mobile.locator('[data-evt-card]').first().evaluate((el) => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= 390 && Number(getComputedStyle(el).opacity) > 0.9; }), 'mobile : les cartes tiennent dans l\'écran');
    if (out) await mobile.screenshot({ path: path.join(out, 'evenements-mobile.png') });
    await mobile.context().close();

    // ── Mouvement réduit ──
    const calm = await open({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    await wait(1200);
    check(await calm.locator('[data-evt-title]').evaluate((el) => Number(getComputedStyle(el).opacity) === 1 && el.querySelectorAll('.char').length === 0), 'mouvement réduit : le titre est visible, non découpé');
    const c1 = await unit(calm, 's');
    await wait(2300);
    check((await unit(calm, 's')) !== c1, 'mouvement réduit : le compte à rebours avance quand même');
    check(await calm.locator('[data-evt-card]').evaluateAll((els) => els.length === 5 && els.every((el) => Number(getComputedStyle(el).opacity) === 1)), 'mouvement réduit : toutes les cartes sont visibles');
    check(await calm.locator('[data-evt-turn]').evaluateAll((els) => els.every((el) => /matrix|rotate/.test(getComputedStyle(el).transform) || getComputedStyle(el).transform !== 'none')), 'mouvement réduit : le cadran est complet');
    check((await calm.locator('[data-evt-fill]').evaluate((el) => getComputedStyle(el).transform)) === 'none', 'mouvement réduit : le fil est entièrement rempli');
    await calm.context().close();

    // ── Anglais ──
    const en = await open({ viewport: { width: 1440, height: 900 } }, '/en/evenements');
    check(await en.getAttribute('html', 'lang') === 'en', 'anglais : <html lang="en">');
    await wait(2500);
    check((await en.locator('.evt-next__eyebrow').innerText()).trim().toLowerCase() === 'next up', 'anglais : « Next up »');
    check((await en.locator('.evt-card__when').first().innerText()).trim().toLowerCase() === 'in 2 days', 'anglais : « In 2 days »');
    await en.context().close();

    // ── Page d'un événement ──
    const show = await open({ viewport: { width: 1440, height: 900 } }, '/evenements/e2e-prochain');
    check(await until(async () => show.evaluate(() => document.documentElement.classList.contains('evt-ready'))), 'événement : le mode immersif s\'active');
    check(await until(async () => (await show.locator('[data-evs-title] .word').count()) >= 2, 5000), 'événement : le titre se découpe en mots');
    await wait(2800);
    check(await show.locator('[data-evs-ticket]').evaluate((el) => Number(getComputedStyle(el).opacity) > 0.95), 'événement : le billet est posé');
    const sec = await unit(show, 's');
    await wait(2300);
    check((await unit(show, 's')) !== sec, 'événement : le compte à rebours avance chaque seconde');
    const ticketBox = await show.locator('[data-evs-ticket]').boundingBox();
    const tilt0 = await show.locator('[data-evs-ticket]').evaluate((el) => getComputedStyle(el).transform);
    await show.mouse.move(ticketBox.x + ticketBox.width * 0.9, ticketBox.y + 10, { steps: 8 });
    await wait(1000);
    check(tilt0 !== await show.locator('[data-evs-ticket]').evaluate((el) => getComputedStyle(el).transform), 'événement : le billet s\'incline avec le pointeur');
    const ics = await show.request.get(new URL(await show.locator('.evs-ticket__cal a[download]').getAttribute('href'), base).href);
    check(ics.status() === 200 && (ics.headers()['content-type'] ?? '').startsWith('text/calendar') && (await ics.text()).includes('SUMMARY:E2E Prochain'), 'événement : le fichier agenda (.ics) se télécharge');
    if (out) await show.screenshot({ path: path.join(out, 'evenement-billet.png') });
    await scrollTo(show, '[data-evs-when]', -150);
    check(await until(async () => show.locator('[data-evs-seg]').evaluate((el) => new DOMMatrix(getComputedStyle(el).transform).a > 0.98), 5000), 'événement : la plage horaire se remplit');
    check((await show.locator('.evs-track__hours li').allInnerTexts()).length === 5, 'événement : les repères de la journée sont affichés');
    check(await show.locator('.nws-prose .prose > p').first().evaluate((el) => getComputedStyle(el, '::first-letter').float === 'none'), 'événement : pas de lettrine dans le descriptif');
    await scrollTo(show, '[data-evs-others]', -100);
    await wait(1800);
    check(await show.locator('[data-evs-others] [data-evt-stamp]').evaluateAll((els) => els.length === 3 && els.every((el) => Number(getComputedStyle(el).opacity) > 0.95)), 'événement : trois autres rendez-vous apparaissent');
    check(await show.locator('[data-evs-others] .evt-stamp__img').first().evaluate((el) => getComputedStyle(el).filter === 'none'), 'événement : les autres rendez-vous sont en couleurs');
    await show.goto(base + '/evenements/e2e-plusieurs-jours', { waitUntil: 'load' });
    await scrollTo(show, '[data-evs-when]', -150);
    check(await until(async () => show.locator('[data-evs-day]').evaluateAll((els) => els.length === 4 && els.every((el) => Number(getComputedStyle(el).opacity) > 0.95)), 5000), 'événement de plusieurs jours : quatre jours apparaissent');
    await show.goto(base + '/evenements/e2e-passe-1', { waitUntil: 'load' });
    await wait(2500);
    check((await show.locator('.evs-tag').innerText()).toLowerCase().includes('terminé') && await show.locator('[data-evt-countdown]').count() === 0, 'événement passé : « terminé », sans compte à rebours');
    await show.context().close();

    const showMobile = await open({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true }, '/evenements/e2e-prochain');
    await scrollAll(showMobile);
    await wait(1200);
    check((await showMobile.evaluate(() => document.documentElement.scrollWidth)) <= 390, 'événement, mobile : aucun défilement horizontal');
    check(await showMobile.locator('[data-evs-ticket]').evaluate((el) => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= 390; }), 'événement, mobile : le billet tient dans l\'écran');
    await showMobile.context().close();

    const showCalm = await open({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' }, '/evenements/e2e-prochain');
    await wait(1200);
    check(await showCalm.locator('[data-evs-title]').evaluate((el) => Number(getComputedStyle(el).opacity) === 1 && el.querySelectorAll('.word').length === 0), 'événement, mouvement réduit : le titre est visible, non découpé');
    check(await showCalm.locator('[data-evs-ticket]').evaluate((el) => Number(getComputedStyle(el).opacity) === 1 && getComputedStyle(el).transform === 'none'), 'événement, mouvement réduit : le billet est en place');
    check(await showCalm.locator('[data-evs-seg]').evaluate((el) => getComputedStyle(el).transform === 'none'), 'événement, mouvement réduit : la plage horaire est dessinée');
    const calmSec = await unit(showCalm, 's');
    await wait(2300);
    check((await unit(showCalm, 's')) !== calmSec, 'événement, mouvement réduit : le compte à rebours avance quand même');
    await showCalm.context().close();

    const showEn = await open({ viewport: { width: 1440, height: 900 } }, '/en/evenements/e2e-prochain');
    await wait(2500);
    check((await showEn.locator('.evs-tag').innerText()).toLowerCase().includes('in 2 days'), 'événement, anglais : « In 2 days »');
    await showEn.context().close();

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
    try { events('down'); } catch (error) { console.log(`✗ nettoyage des événements d'essai impossible : ${error.message.split('\n')[0]}`); failures++; }
}

process.exit(failures ? 1 : 0);
