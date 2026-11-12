// Page « Cafétéria » dans un vrai navigateur : chaque scène à sa position de défilement (captures), puis les interactions
// (citation épinglée, triptyque, bandeau, mobile, mouvement réduit). Prérequis : serveur lancé.
// Usage : node tests/e2e/cafeteria.mjs [dossier-captures]
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const url = `${base}/vie-etudiante/cafeteria`;
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

const browser = await chromium.launch();
const hideNewsletter = (page) => page.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));

async function desktop() {
    const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' })).newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
    await hideNewsletter(page);
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForFunction(() => document.documentElement.classList.contains('caf-ready'), null, { timeout: 15000 }).catch(() => {});
    check(await page.evaluate(() => document.documentElement.classList.contains('caf-ready')), 'le mode immersif s\'active (caf-ready)');
    await page.waitForTimeout(2600);
    if (out) await page.screenshot({ path: path.join(out, '1-assiette.png') });

    const top = (selector) => page.evaluate((s) => document.querySelector(s).getBoundingClientRect().top + window.scrollY, selector);
    const shot = async (name, y, wait = 1200) => {
        await page.evaluate((top) => (window.__lenis ? window.__lenis.scrollTo(top, { immediate: true }) : window.scrollTo(0, top)), y);
        await page.waitForTimeout(wait);
        if (out) await page.screenshot({ path: path.join(out, name) });
    };

    // Citation épinglée : trois temps, le fond change de couleur.
    const nourishY = await top('[data-caf-nourish-stage]');
    const bg = () => page.evaluate(() => getComputedStyle(document.querySelector('[data-caf-nourish]')).backgroundColor);
    const op = () => page.evaluate(() => [...document.querySelectorAll('.caf-phrase__text')].map((e) => +getComputedStyle(e).opacity));
    await shot('2a-citation-debut.png', nourishY + 80);
    const o0 = await op(); const b0 = await bg();
    await shot('2b-citation-milieu.png', nourishY + 1500);
    const o1 = await op(); const b1 = await bg();
    await shot('2c-citation-fin.png', nourishY + 2500);
    const o2 = await op(); const b2 = await bg();
    check(o0[0] > o0[2] && o1[1] > o0[1] && o2[2] > 0.9, `citation : les phrases s'allument l'une après l'autre (${o0.map((x) => x.toFixed(1))} → ${o1.map((x) => x.toFixed(1))} → ${o2.map((x) => x.toFixed(1))})`);
    check(b0 !== b1 && b1 !== b2, `citation : la couleur du fond change (${b0} → ${b1} → ${b2})`);

    await shot('3-assiette-mots.png', await top('[data-caf-principles]') - 40, 1800);
    check((await page.locator('[data-caf-tile]').count()) === 6, 'six mots du texte, entourés au fil du défilement');

    // Triptyque : le premier volet est ouvert ; un clic ouvre le deuxième.
    await shot('4a-triptyque.png', await top('[data-caf-triptych]') + 60, 1500);
    check(await page.evaluate(() => document.querySelector('[data-caf-triptych]').classList.contains('is-live')), 'triptyque : mode interactif actif (grand écran)');
    const widths = () => page.evaluate(() => [...document.querySelectorAll('[data-caf-panel]')].map((p) => Math.round(p.getBoundingClientRect().width)));
    const w0 = await widths();
    check(w0[0] > w0[1] * 2, `triptyque : le volet 1 est ouvert (${w0})`);
    await page.locator('[data-caf-panel]').nth(1).click();
    await page.waitForTimeout(1400);
    const w1 = await widths();
    check(w1[1] > w1[0] * 2 && (await page.locator('[data-caf-open]').nth(1).getAttribute('aria-expanded')) === 'true', `triptyque : un clic ouvre le volet 2 (${w1})`);
    if (out) await page.screenshot({ path: path.join(out, '4b-triptyque-volet2.png') });
    await page.locator('[data-caf-open]').nth(2).focus();
    await page.waitForTimeout(1200);
    check((await page.locator('[data-caf-open]').nth(2).getAttribute('aria-expanded')) === 'true', 'triptyque : le clavier (focus) ouvre le volet 3');

    // Bandeau : défile et s'incline avec le défilement.
    const marqueeY = await top('[data-caf-marquee]');
    await shot('5-ambiance.png', marqueeY - 250, 900);
    await shot('6-invitation.png', await top('[data-caf-invite-text]') - 350, 1800);
    await shot('6b-invitation-fin.png', await top('[data-caf-invite-text]') - 120, 1800);

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
}

async function mobile() {
    const page = await (await browser.newContext({ viewport: { width: 390, height: 800 }, locale: 'fr-FR' })).newPage();
    await hideNewsletter(page);
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForTimeout(2800);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    check(overflow <= 1, `mobile : aucun défilement horizontal (${overflow}px)`);
    check(!(await page.evaluate(() => document.querySelector('[data-caf-triptych]').classList.contains('is-live'))), 'mobile : le triptyque reste empilé');
    if (out) await page.screenshot({ path: path.join(out, 'm1-assiette.png') });
    await page.evaluate(() => document.querySelector('[data-caf-triptych]').scrollIntoView());
    await page.waitForTimeout(1500);
    if (out) await page.screenshot({ path: path.join(out, 'm2-triptyque.png') });
}

async function reducedMotion() {
    const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce', locale: 'fr-FR' })).newPage();
    await hideNewsletter(page);
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForTimeout(1500);
    check(await page.locator('[data-caf-title]').isVisible(), 'mouvement réduit : le titre est visible');
    check(!(await page.evaluate(() => document.querySelector('[data-caf-nourish]').classList.contains('is-live'))), 'mouvement réduit : pas de scène épinglée');
    check((await page.evaluate(() => getComputedStyle(document.querySelector('.caf-phrase__text')).opacity)) === '1', 'mouvement réduit : la citation est entièrement lisible');
    await page.waitForFunction(() => document.querySelector('[data-caf-triptych]')?.classList.contains('is-live'), null, { timeout: 8000 });
    await page.locator('[data-caf-panel]').nth(2).click();
    await page.waitForTimeout(300);
    check(await page.locator('[data-caf-text]').nth(2).isVisible(), 'mouvement réduit : le triptyque reste utilisable');
}

// Menu de la semaine : tableau par semaine saisi dans le back-office. Ne s'affiche que s'il existe une semaine en cours ou à venir : sinon le contrôle est ignoré.
async function menu() {
    const open = async (context) => {
        const page = await context.newPage();
        await hideNewsletter(page);
        await page.goto(`${url}#menu`, { waitUntil: 'load' });
        await page.waitForTimeout(1200);
        return page;
    };
    const visibleColumns = (page, panel) => page.evaluate((p) => [...document.querySelectorAll(`[data-caf-week-panel="${p}"] thead [data-caf-day]`)].filter((e) => e.getBoundingClientRect().width > 0).length, panel);

    const desktopPage = await open(await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' }));
    if ((await desktopPage.locator('#menu').count()) === 0) {
        console.log('~ menu : aucune semaine en cours ou à venir, contrôles ignorés');
        return;
    }

    const first = await desktopPage.locator('[data-caf-week-panel]').first().getAttribute('data-caf-week-panel');
    check(await desktopPage.evaluate(() => document.querySelector('#menu').classList.contains('is-live')), 'menu : le script s\'active (is-live)');
    check((await desktopPage.locator(`[data-caf-week-panel="${first}"] tbody tr`).count()) === 3 && (await desktopPage.locator(`[data-caf-week-panel="${first}"] thead th[scope="col"]`).count()) === 7, 'menu : trois repas (matin, midi, soir) × sept jours');
    check((await visibleColumns(desktopPage, first)) === 7, 'menu : les sept jours sont visibles sur grand écran');

    const tabs = desktopPage.locator('[data-caf-week]');
    if ((await tabs.count()) > 1) {
        check((await tabs.first().getAttribute('aria-selected')) === 'true' && (await desktopPage.locator('[data-caf-week-panel]').nth(1).isHidden()), 'menu : seule la première semaine est affichée');
        await tabs.nth(1).click();
        check((await tabs.nth(1).getAttribute('aria-selected')) === 'true' && (await desktopPage.locator('[data-caf-week-panel]').nth(1).isVisible()) && (await desktopPage.locator('[data-caf-week-panel]').first().isHidden()), 'menu : un clic sur l\'onglet change de semaine');
        await tabs.nth(1).focus();
        await desktopPage.keyboard.press('ArrowLeft');
        check((await tabs.first().getAttribute('aria-selected')) === 'true' && (await tabs.first().evaluate((e) => e === document.activeElement)), 'menu : les flèches du clavier changent de semaine');
    }
    if (out) await desktopPage.locator('#menu').screenshot({ path: path.join(out, '4c-menu.png') });
    await desktopPage.context().close();

    // Mobile : un seul jour à la fois, au choix.
    const mobilePage = await open(await browser.newContext({ viewport: { width: 390, height: 844 }, locale: 'fr-FR' }));
    check((await visibleColumns(mobilePage, first)) === 1, 'menu mobile : un seul jour est affiché');
    const before = await mobilePage.locator(`[data-caf-week-panel="${first}"] thead th.caf-table__day:visible`).innerText();
    await mobilePage.locator(`[data-caf-week-panel="${first}"] [data-caf-chip]`).nth(6).click();
    const after = await mobilePage.locator(`[data-caf-week-panel="${first}"] thead th.caf-table__day:visible`).innerText();
    check(before !== after && (await visibleColumns(mobilePage, first)) === 1, `menu mobile : le bouton du jour change la colonne (${before.split('\n')[0]} → ${after.split('\n')[0]})`);
    check((await mobilePage.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)) <= 1, 'menu mobile : aucun défilement horizontal');
    if (out) await mobilePage.locator('#menu').screenshot({ path: path.join(out, 'm3-menu.png') });
    await mobilePage.context().close();

    // Sans JavaScript : toutes les semaines et tous les jours restent affichés (le tableau défile à l'horizontale).
    const plain = await open(await browser.newContext({ viewport: { width: 1440, height: 900 }, javaScriptEnabled: false }));
    const panels = await plain.locator('[data-caf-week-panel]').count();
    check((await plain.locator('#menu.is-live').count()) === 0 && (await plain.locator('[data-caf-week-panel]:visible').count()) === panels, `menu sans JavaScript : les ${panels} semaine(s) sont affichées`);
    await plain.context().close();
}

try {
    await desktop();
    await mobile();
    await reducedMotion();
    await menu();
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
}

process.exit(failures ? 1 : 0);
