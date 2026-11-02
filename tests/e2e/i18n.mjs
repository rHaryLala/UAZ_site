// Langues du site dans un vrai navigateur : sélecteur, formulaire de contact Livewire (la langue survit à l'envoi),
// lettre d'information et recherche sous /en et /mg, textes affichés par JavaScript.
// Prérequis : serveur lancé. Usage : node tests/e2e/i18n.mjs [dossier-captures]
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};
// Le serveur de développement répond en 0,3 à 3 s : on attend un état plutôt qu'un délai fixe.
const until = async (fn, ms = 20000) => {
    const end = Date.now() + ms;
    while (Date.now() < end) {
        try { if (await fn()) return true; } catch { /* page en cours de chargement */ }
        await new Promise((r) => setTimeout(r, 250));
    }
    return false;
};

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' })).newPage();
const errors = [];
page.on('pageerror', (e) => !/ViewTransition|Transition was aborted/.test(e.message) && errors.push(e.message.slice(0, 140)));

try {
    for (const [code, label, contactTitle] of [['en', 'anglais', 'Contact us'], ['mg', 'malagasy', 'Mifandraisa aminay']]) {
        await page.goto(`${base}/${code}/contact`, { waitUntil: 'load', timeout: 60000 });
        check(await page.getAttribute('html', 'lang') === code, `${label} : <html lang="${code}">`);
        check((await page.locator('h1').first().innerText()).includes(contactTitle), `${label} : titre de la page « ${contactTitle} »`);

        // Formulaire Livewire : un envoi vide renvoie des erreurs dans la langue de la page (requête sur une adresse sans préfixe).
        await page.locator('main form[novalidate] button[type=submit]').click();
        const shown = await until(async () => (await page.locator('main .field-error').count()) > 0);
        check(shown, `${label} : le formulaire de contact répond (erreurs de validation)`);
        const message = shown ? (await page.locator('main .field-error').allInnerTexts()).find((text) => text.trim()) ?? '' : '';
        const french = /obligatoire|champ|doit/i.test(message);
        check(message.length > 3 && !french, `${label} : le message d'erreur est dans la langue de la page (« ${message.slice(0, 70)} »)`);
        check(!(await page.content()).includes('/en/en/') && !(await page.content()).includes('/mg/mg/'), `${label} : aucune adresse préfixée deux fois`);
    }

    // Lettre d'information en anglais : la fenêtre s'ouvre, une adresse invalide donne un message anglais.
    await page.goto(`${base}/en`, { waitUntil: 'load', timeout: 60000 });
    await page.locator('[data-newsletter-open]').first().click();
    await page.locator('dialog[open] input[type=email], [data-newsletter-dialog] input[type=email]').first().fill('pas-une-adresse');
    await page.locator('[data-newsletter-dialog] button[type=submit], dialog[open] button[type=submit]').first().click();
    const err = page.locator('[data-newsletter-error]').first();
    check(await until(async () => (await err.isVisible()) && (await err.innerText()).length > 3), 'anglais : la lettre d\'information affiche une erreur pour une adresse invalide');
    const newsletterError = (await err.innerText()).trim();
    check(!/adresse|invalide|obligatoire/i.test(newsletterError), `anglais : l'erreur de la lettre d'information est en anglais (« ${newsletterError.slice(0, 70)} »)`);

    // Recherche en direct (JavaScript) sous /mg : résultats avec liens préfixés.
    await page.goto(`${base}/mg`, { waitUntil: 'load', timeout: 60000 });
    await page.locator('a[aria-haspopup=dialog][aria-label]').first().click();
    await page.locator('#search-modal-q').fill('licence');
    check(await until(async () => (await page.locator('[data-search-result]').count()) > 0), 'malagasy : la recherche en direct affiche des résultats');
    const hrefs = await page.locator('[data-search-result]').evaluateAll((els) => els.map((e) => e.getAttribute('href')));
    check(hrefs.length > 0 && hrefs.every((h) => h.includes('/mg/')), `malagasy : les liens des résultats sont préfixés /mg (${hrefs.length})`);

    // Horaires de la bibliothèque (JavaScript) : texte d'état et heures dans la langue de la page.
    await page.goto(`${base}/en/vie-etudiante/bibliotheque`, { waitUntil: 'load', timeout: 60000 });
    const status = page.locator('[data-lib-status-text]').first();
    check(await until(async () => (await status.innerText()).trim().length > 3, 25000), 'anglais : l\'état de la bibliothèque s\'affiche');
    const statusText = (await status.innerText()).trim();
    check(/library|open|closed|resume|opens|Hours/i.test(statusText) && !/bibliothèque|ouverte|fermée/i.test(statusText), `anglais : l'état est en anglais (« ${statusText.slice(0, 70)} »)`);
    if (out) await page.screenshot({ path: path.join(out, 'i18n-bibliotheque-en.png') });

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    if (out) await page.screenshot({ path: path.join(out, 'i18n-erreur.png') });
    failures++;
} finally {
    await browser.close();
}

process.exit(failures ? 1 : 0);
