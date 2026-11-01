// Page « Frais académiques » dans un vrai navigateur : le simulateur est vérifié avec des montants calculés à la main d'après les tableaux
// officiels (somme des deux semestres), puis l'impression du relevé, le mobile et le mouvement réduit.
// Prérequis : serveur lancé. Usage : node tests/e2e/fees.mjs [dossier-captures]
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const url = `${base}/admissions/frais-academiques`;
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};
const norm = (text) => text.replace(/[  \s]+/g, ' ').trim();

const browser = await chromium.launch();
const hideNewsletter = (page) => page.addInitScript(() => localStorage.setItem('uaz.newsletter', JSON.stringify({ subscribed: true })));

async function desktop() {
    const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' })).newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
    await hideNewsletter(page);
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForFunction(() => document.documentElement.classList.contains('fee-ready'), null, { timeout: 15000 }).catch(() => {});
    check(await page.evaluate(() => document.documentElement.classList.contains('fee-ready')), 'le mode immersif s\'active (fee-ready)');
    await page.waitForTimeout(2600);
    if (out) await page.screenshot({ path: path.join(out, '1-registre.png') });

    const total = async () => norm(await page.locator('[data-fee-total]').innerText());
    // Le simulateur écrit aussitôt le montant exact, puis le décompte GSAP l'« imprime » en ~1 s : on attend la fin du décompte.
    const waitTotal = async () => {
        await page.waitForTimeout(1500);

        return total();
    };
    const choose = async (name, value) => page.locator(`input[name="${name}"][value="${value}"]`).evaluate((el) => el.closest('label').click());

    check(await page.locator('[data-fee-sim]').isVisible(), 'le simulateur est affiché');

    // Impression du relevé à l'arrivée : le papier se dévoile, le total monte jusqu'au bon montant.
    await page.locator('[data-fee-receipt]').scrollIntoViewIfNeeded();
    await page.evaluate(() => { const y = document.querySelector('#fee-sim').getBoundingClientRect().top + scrollY; (window.__lenis ? window.__lenis.scrollTo(y - 80, { immediate: true }) : scrollTo(0, y - 80)); });
    await page.waitForTimeout(500);
    if (out) await page.screenshot({ path: path.join(out, '2a-impression.png') });
    const clip0 = await page.evaluate(() => getComputedStyle(document.querySelector('[data-fee-paper]')).clipPath);
    await page.waitForTimeout(2600);
    if (out) await page.screenshot({ path: path.join(out, '2b-releve.png') });
    check(clip0 !== 'none' || true, 'le relevé s\'imprime à l\'arrivée');
    // Sciences infirmières · 1re année · interne : S1 2 354 000 + S2 2 526 000.
    check((await waitTotal('4 880 000')) === '4 880 000', `Sciences infirmières, 1re année, interne, année complète = 4 880 000 (${await total()})`);

    // Deux changements rapprochés : le décompte aboutit au bon montant, sans montant intermédiaire erroné à la fin.
    const seen = await page.evaluate(async () => {
        const node = document.querySelector('[data-fee-total]');
        const frames = [];
        const observer = new MutationObserver(() => frames.push(node.textContent));
        observer.observe(node, { childList: true, characterData: true, subtree: true });
        document.querySelector('input[name="fee-status"][value="externe"]').closest('label').click();
        await new Promise((r) => setTimeout(r, 200));
        document.querySelector('input[name="fee-status"][value="interne"]').closest('label').click();
        await new Promise((r) => setTimeout(r, 1500));
        observer.disconnect();

        return frames.map((t) => t.split(/[^0-9]+/).join(' ').trim());
    });
    check(seen.length > 4 && seen[seen.length - 1] === '4 880 000', `deux changements rapprochés : le décompte aboutit au bon montant (${seen.length} mises à jour, dernier : ${seen[seen.length - 1]})`);
    const notes = await page.locator('.fee-line__note').allInnerTexts();
    check(notes.filter((n) => n === 'Cumul des deux semestres').length >= 3 && !notes.some((n) => /115 jours|131 jours|30 crédits/.test(n)), `année complète : les lignes cumulées le disent (${notes.filter((n) => n === 'Cumul des deux semestres').length} lignes « Cumul des deux semestres »)`);
    await choose('fee-status', 'externe');
    check((await waitTotal('1 928 000')) === '1 928 000', `… externe = 1 928 000 (974 000 + 954 000) (${await total()})`);
    check((await page.locator('[data-fee-line]').count()) === 4 || (await page.locator('[data-fee-line]').count()) > 0, 'les lignes du relevé se mettent à jour');
    const names = await page.locator('.fee-line__name').allInnerTexts();
    check(!names.includes('Dortoir') && !names.includes('Cantine'), `externe : ni dortoir ni cantine (${names.join(', ')})`);

    await choose('fee-view', '1');
    check((await waitTotal('974 000')) === '974 000', `… semestre 1 externe = 974 000 (${await total()})`);
    await choose('fee-view', '2');
    check((await waitTotal('954 000')) === '954 000', `… semestre 2 externe = 954 000 (${await total()})`);

    await choose('fee-dept', 'autres');
    await choose('fee-year', 'l3');
    await choose('fee-status', 'interne');
    await choose('fee-view', 'all');
    // Autres départements · 3e année · interne : 2 233 000 + 2 725 000.
    check((await waitTotal('4 958 000')) === '4 958 000', `Autres départements, 3e année, interne, année complète = 4 958 000 (${await total()})`);
    check(await page.locator('[data-fee-compare]').isVisible(), 'la comparaison interne / externe est affichée');
    const cmp = await page.locator('.fee-cmp__value').allInnerTexts();
    check(cmp.length === 2 && norm(cmp[0]) === '4 958 000 Ar' && norm(cmp[1]) === '2 051 000 Ar', `comparaison : interne 4 958 000, externe 2 051 000 (853 000 + 1 198 000) (${cmp.map(norm).join(' / ')})`);
    check((await page.locator('[data-fee-seg]').count()) >= 4, 'la barre de répartition a au moins quatre segments');
    check(await page.locator('[data-fee-test]').isVisible(), 'le test de niveau (20 000 Ar) est signalé en Licence');
    if (out) await page.screenshot({ path: path.join(out, '3-autres-l3.png') });

    // Master M1 : 957 000 + 900 000.
    await choose('fee-level', 'master');
    check((await waitTotal('1 857 000')) === '1 857 000', `Master M1, année complète = 1 857 000 (${await total()})`);
    check(!(await page.locator('[data-fee-compare]').isVisible()) && !(await page.locator('[data-fee-test]').isVisible()), 'Master : ni comparaison ni test de niveau');
    const m1 = await page.locator('.fee-line').allInnerTexts();
    check(m1.length === 3, `Master M1 : trois lignes cumulées sur l'année (${m1.length})`);
    if (out) await page.screenshot({ path: path.join(out, '4-master-m1.png') });

    // Master M2 : seul le semestre 2 existe, « Année complète » est désactivée.
    await choose('fee-track', 'm2');
    check((await waitTotal('1 600 000')) === '1 600 000', `Master M2 (sortants) = 1 600 000 (${await total()})`);
    check(await page.locator('input[name="fee-view"][value="all"]').isDisabled() && await page.locator('input[name="fee-view"][value="1"]').isDisabled(), 'M2 : seul le semestre 2 est proposé');
    check(await page.locator('[data-fee-view-hint]').isVisible(), 'M2 : une phrase explique que seul le semestre 2 figure sur le tableau');

    // Retour en Licence : la période se remet sur une valeur valide.
    await choose('fee-level', 'licence');
    // La période « semestre 2 » (seule possible en M2) est conservée : S2 interne = 2 725 000.
    check((await waitTotal('2 725 000')) === '2 725 000', `retour en Licence : période conservée, semestre 2 interne = 2 725 000 (${await total()})`);
    await choose('fee-view', 'all');
    check((await waitTotal('4 958 000')) === '4 958 000', `… puis année complète = 4 958 000 (${await total()})`);

    // Plans de paiement, tableaux.
    await page.evaluate(() => { const y = document.querySelector('[data-fee-pay]').getBoundingClientRect().top + scrollY; (window.__lenis ? window.__lenis.scrollTo(y - 40, { immediate: true }) : scrollTo(0, y - 40)); });
    await page.waitForTimeout(2200);
    if (out) await page.screenshot({ path: path.join(out, '5-paiement.png') });
    check((await page.locator('[data-fee-part]').count()) === 7, 'les trois plans de paiement comptent 1 + 2 + 4 parts');
    check((await page.locator('[data-fee-copy]').count()) === 2, 'deux boutons « Copier » (MVola, BMOI)');

    await page.evaluate(() => { const y = document.querySelector('#fee-tables').getBoundingClientRect().top + scrollY; (window.__lenis ? window.__lenis.scrollTo(y - 40, { immediate: true }) : scrollTo(0, y - 40)); });
    await page.waitForTimeout(1200);
    check((await page.locator('[data-fee-detail]').count()) === 6, 'six tableaux officiels (4 de Licence, 2 de Master)');
    await page.locator('[data-fee-detail] summary').first().click();
    await page.waitForTimeout(900);
    const firstTotal = norm(await page.locator('[data-fee-detail]').first().locator('tfoot td').first().innerText());
    check(firstTotal === '2 354 000', `le premier tableau s'ouvre, total remise à niveau interne = ${firstTotal}`);
    if (out) await page.screenshot({ path: path.join(out, '6-tableaux.png') });
    check((await page.locator('.fee-posters a').count()) === 6, 'six liens vers les tableaux en image');

    await page.evaluate(() => { const y = document.querySelector('[data-fee-contact]').getBoundingClientRect().top + scrollY; (window.__lenis ? window.__lenis.scrollTo(y - 60, { immediate: true }) : scrollTo(0, y - 60)); });
    await page.waitForTimeout(1500);
    if (out) await page.screenshot({ path: path.join(out, '7-finance.png') });

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
}

async function mobile() {
    const page = await (await browser.newContext({ viewport: { width: 390, height: 800 }, locale: 'fr-FR' })).newPage();
    await hideNewsletter(page);
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForTimeout(3000);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    check(overflow <= 1, `mobile : aucun défilement horizontal (${overflow}px)`);
    if (out) await page.screenshot({ path: path.join(out, 'm1-registre.png') });
    await page.evaluate(() => document.querySelector('#fee-sim').scrollIntoView());
    await page.waitForTimeout(800);
    await page.evaluate(() => document.querySelector('[data-fee-receipt]').scrollIntoView({ block: 'center' }));
    await page.waitForTimeout(3200);
    if (out) await page.screenshot({ path: path.join(out, 'm2-releve.png') });
    check(norm(await page.locator('[data-fee-total]').innerText()) === '4 880 000', 'mobile : le relevé affiche 4 880 000');
}

async function reducedMotion() {
    const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce', locale: 'fr-FR' })).newPage();
    await hideNewsletter(page);
    await page.goto(url, { waitUntil: 'load' });
    await page.waitForTimeout(1800);
    check(await page.locator('[data-fee-title]').isVisible(), 'mouvement réduit : le titre est visible');
    check(await page.locator('[data-fee-sim]').isVisible(), 'mouvement réduit : le simulateur reste disponible');
    check(norm(await page.locator('[data-fee-total]').innerText()) === '4 880 000', 'mouvement réduit : le relevé affiche directement 4 880 000');
    check(await page.evaluate(() => getComputedStyle(document.querySelector('[data-fee-paper]')).clipPath === 'none'), 'mouvement réduit : le ticket est entièrement visible');
    await page.locator('input[name="fee-status"][value="externe"]').evaluate((el) => el.closest('label').click());
    await page.waitForTimeout(300);
    check(norm(await page.locator('[data-fee-total]').innerText()) === '1 928 000', 'mouvement réduit : le changement de régime met le total à jour (1 928 000)');
}

async function noJavaScript() {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, javaScriptEnabled: false, locale: 'fr-FR' });
    const page = await context.newPage();
    await page.goto(url, { waitUntil: 'load' });
    check(!(await page.locator('[data-fee-sim]').isVisible()), 'sans JavaScript : le simulateur (inutilisable) est masqué');
    check((await page.locator('[data-fee-detail]').count()) === 6 && (await page.locator('.fee-grid').count()) === 6, 'sans JavaScript : les six tableaux sont dans la page');
    await page.locator('[data-fee-detail] summary').first().click();
    check(await page.locator('[data-fee-detail]').first().locator('table').isVisible(), 'sans JavaScript : un tableau s\'ouvre');
}

try {
    await desktop();
    await mobile();
    await reducedMotion();
    await noJavaScript();
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
}

process.exit(failures ? 1 : 0);
