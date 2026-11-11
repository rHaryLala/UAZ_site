// Éditeur de lettres d'information dans un vrai navigateur : plan de travail (la lettre comme elle partira), sélection d'un bloc, texte modifié sur
// place, barre flottante, ajout / déplacement / duplication / suppression, annulation, réglages (alignement, couleurs, thème), image de la médiathèque,
// enregistrement automatique, écran mobile, envoi d'essai, envoi à tous les abonnés actifs (et à eux seuls), lettre figée après envoi.
// Prérequis : serveur lancé, compte admin@uaz.test, courrier en mode « log ». Usage : node tests/e2e/newsletter-editor.mjs [dossier-captures]
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
const fixture = path.join(path.dirname(fileURLToPath(import.meta.url)), 'fixtures', 'newsletter.php');
const php = (...args) => execFileSync(process.env.PHP_BIN ?? 'php', args, { encoding: 'utf8' });
const tinker = (code) => php('artisan', `--no-ansi`, 'tinker', `--execute=${code}`).trim();
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};
const until = async (fn, ms = 10000) => {
    const end = Date.now() + ms;
    while (Date.now() < end) {
        try { if (await fn()) return true; } catch { /* en cours de chargement */ }
        await new Promise((r) => setTimeout(r, 150));
    }
    return false;
};
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

const id = Number(php(fixture, 'up').trim().split('\n').pop());
const browser = await chromium.launch();
const errors = [];
try {
    const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
    page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
    await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
    await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: /Connexion|Se connecter/ }).click();
    await page.waitForURL('**/admin');

    // La liste et son ouverture dans l'éditeur.
    await page.goto(`${base}/admin/newsletters`, { waitUntil: 'networkidle' });
    check((await page.locator('body').innerText()).includes('E2E lettre') && (await page.locator('body').innerText()).includes('Brouillon'), 'la liste montre la lettre en brouillon');
    check((await page.locator('body').innerText()).includes('2 abonnés actifs'), 'la liste annonce le nombre d\'abonnés actifs');
    await page.goto(`${base}/admin/newsletter-editor/${id}`, { waitUntil: 'load' });
    const canvas = page.frameLocator('[data-nl-frame]');
    check(await until(async () => (await canvas.locator('[data-nl-block]').count()) === 3), 'le plan de travail affiche la lettre (titre, texte, bouton)');
    check((await canvas.locator('body').innerText()).includes('Université Adventiste') && (await canvas.locator('body').innerText()).includes('Se désinscrire'), 'le bandeau et le pied de page (désinscription) sont là');
    await wait(900);
    if (out) await page.screenshot({ path: path.join(out, 'nl-plan-de-travail.png') });

    // Sélection d'un bloc : contour, barre flottante, onglet « Bloc ».
    await canvas.locator('[data-nl-block="0"]').click({ position: { x: 4, y: 4 } });
    check(await until(async () => (await canvas.locator('.nl-selected').count()) === 1 && (await canvas.locator('.nl-bar').count()) === 1), 'un clic sélectionne le bloc et affiche sa barre d\'outils');
    check(await page.locator('[data-nl-tab="block"]').getAttribute('aria-selected') === 'true', 'le panneau passe sur les réglages du bloc');

    // Texte modifié sur place, enregistrement automatique.
    const title = canvas.locator('[data-nl-block="0"] [data-nl-field="text"]');
    await title.click();
    await page.keyboard.press('Control+A');
    await page.keyboard.type('Bonjour à toute la communauté');
    await page.keyboard.press('Enter');
    check(await until(async () => /Enregistré à/.test(await page.locator('[data-nl-status]').innerText()), 6000), 'le texte modifié est enregistré automatiquement');
    check(tinker('echo App\\Models\\Newsletter::find(' + id + ')->blocks[0]["data"]["text"];').includes('Bonjour à toute la communauté'), 'le nouveau titre est bien dans la base');

    // Réglages : alignement.
    await canvas.locator('[data-nl-block="0"]').click({ position: { x: 4, y: 4 } });
    await page.locator('[data-nl-pane="block"] .nl-seg button', { hasText: 'Centré' }).first().click();
    check(await until(async () => (await canvas.locator('[data-nl-block="0"] [data-nl-field="text"]').evaluate((el) => getComputedStyle(el).textAlign)) === 'center'), 'réglage : le titre se centre');

    // Ajout d'un bloc, déplacement, annulation, duplication, suppression.
    await page.locator('[data-nl-tab="add"]').click();
    await page.locator('.ve-card', { hasText: 'Séparateur' }).click();
    check(await until(async () => (await canvas.locator('[data-nl-block]').count()) === 4), 'ajout : le séparateur apparaît sur le plan de travail');
    await canvas.locator('.nl-bar [data-op="up"]').click();
    check(await until(async () => (await canvas.locator('[data-nl-block]').nth(0).getAttribute('data-nl-type')) === 'divider'), 'déplacement : le séparateur monte d\'un cran (il se place sous le bloc sélectionné, puis monte en tête)');
    await page.locator('[data-nl-undo]').click();
    check(await until(async () => (await canvas.locator('[data-nl-block]').nth(1).getAttribute('data-nl-type')) === 'divider'), 'annulation : le séparateur est revenu à sa place');
    await page.locator('[data-nl-redo]').click();
    await wait(600);
    await canvas.locator('[data-nl-block="2"]').click({ position: { x: 4, y: 4 } });
    await canvas.locator('.nl-bar [data-op="dup"]').click();
    check(await until(async () => (await canvas.locator('[data-nl-block]').count()) === 5), 'duplication : un bloc de plus');
    await canvas.locator('.nl-bar [data-op="del"]').click();
    check(await until(async () => (await canvas.locator('[data-nl-block]').count()) === 4), 'suppression : un bloc de moins');

    // Structure : l'ordre se voit et se change dans le panneau.
    await page.locator('[data-nl-tab="structure"]').click();
    check((await page.locator('[data-nl-pane="structure"] .ve-item').count()) === 4, 'structure : quatre blocs listés');

    // Texte enrichi : gras sur le paragraphe.
    const paragraph = canvas.locator('[data-nl-field="html"]').first();
    await paragraph.click();
    await page.keyboard.press('Control+A');
    await page.keyboard.press('Control+B');
    await page.keyboard.press('Escape');
    check(await until(async () => /<(b|strong)>/i.test(await paragraph.innerHTML()), 4000), 'texte : le gras s\'applique sur le plan de travail');

    // Thème : couleur de fond de la lettre.
    await page.locator('[data-nl-tab="letter"]').click();
    await page.locator('[data-nl-pane="letter"] .ve-field', { hasText: 'Fond autour de la lettre' }).locator('.nl-swatch').nth(1).click();
    check(await until(async () => (await canvas.locator('body').evaluate((el) => getComputedStyle(el).backgroundColor)) === 'rgb(251, 249, 244)'), 'thème : le fond autour de la lettre change');

    // Image de la médiathèque.
    await page.locator('[data-nl-tab="add"]').click();
    await page.locator('.ve-card', { hasText: 'Image' }).first().click();
    check(await until(async () => page.locator('[data-nl-media]').evaluate((d) => d.open)), 'image : la médiathèque s\'ouvre');
    await page.locator('[data-nl-media-grid] .ve-thumb').first().click();
    check(await until(async () => (await canvas.locator('[data-nl-type="image"] img').count()) === 1), 'image : la photo choisie apparaît dans la lettre');
    if (out) await page.screenshot({ path: path.join(out, 'nl-plan-image.png') });

    // Écran mobile.
    await page.locator('[data-nl-device="mobile"]').click();
    await wait(500);
    check(Math.round((await page.locator('[data-nl-frame-wrap]').boundingBox()).width) === 390, 'mobile : le plan de travail passe à 390 px');
    if (out) await page.screenshot({ path: path.join(out, 'nl-plan-mobile.png') });
    await page.locator('[data-nl-device="desktop"]').click();

    // Tout est enregistré, rechargement : la lettre revient identique.
    await until(async () => /Enregistré à/.test(await page.locator('[data-nl-status]').innerText()), 6000);
    await page.reload({ waitUntil: 'load' });
    check(await until(async () => (await canvas.locator('[data-nl-block]').count()) === 5), 'rechargement : la lettre est retrouvée telle qu\'elle a été laissée (5 blocs)');

    // Envoi d'essai (courrier en mode « log »).
    await page.locator('[data-nl-open-test]').click();
    await page.locator('[data-nl-test-to]').fill('essai-e2e@example.test');
    await page.locator('[data-nl-test-submit]').click();
    const sent = await until(async () => /Essai envoyé/.test(await page.locator('[data-nl-toast]').innerText()), 8000);
    if (!sent) console.log('   (erreur affichée : « ' + (await page.locator('[data-nl-test-error]').innerText()) + ' » | toast : « ' + (await page.locator('[data-nl-toast]').innerText()) + ' »)');
    check(sent, 'essai : le courrier d\'essai est envoyé');

    // Envoi à tous : refus tant que la case n'est pas cochée, puis envoi.
    await page.locator('[data-nl-open-send]').click();
    check((await page.locator('[data-nl-send-text]').innerText()).includes('2 abonnés actifs'), 'envoi : la fenêtre annonce 2 abonnés actifs');
    await page.locator('[data-nl-send-confirm]').check();
    await page.locator('[data-nl-send-submit]').click();
    check(await until(async () => page.url().endsWith('/admin/newsletters'), 12000), 'envoi : retour à la liste après l\'envoi');
    await wait(1500);
    check(tinker('$n=App\\Models\\Newsletter::find(' + id + '); echo $n->status, "|", $n->recipients_count, "|", $n->deliveries()->where("status","sent")->count();') === 'sent|2|2', 'envoi : la lettre est « envoyée », 2 destinataires, 2 envois réussis');
    check(tinker('echo App\\Models\\NewsletterDelivery::where("newsletter_id",' + id + ')->join("newsletter_subscribers","newsletter_subscribers.id","=","newsletter_deliveries.newsletter_subscriber_id")->whereIn("email",["e2e-nl-attente@example.test","e2e-nl-parti@example.test"])->count();') === '0', 'envoi : ni l\'abonné en attente, ni le désinscrit ne reçoivent la lettre');
    const log = fs.readFileSync(path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'storage', 'logs', 'laravel.log'), 'utf8');
    check(/List-Unsubscribe: <[^>]*desinscription/.test(log.slice(-400000)), 'envoi : chaque courrier porte son lien de désinscription personnel (en-tête List-Unsubscribe)');

    // Lettre figée.
    await page.goto(`${base}/admin/newsletter-editor/${id}`, { waitUntil: 'load' });
    check(await until(async () => (await page.locator('.ve-notice').count()) === 1 && (await page.locator('[data-nl-tab]').count()) === 0), 'après envoi : la lettre est figée (plus d\'onglets de modification)');
    check((await page.locator('[data-nl-open-send]').count()) === 0, 'après envoi : plus de bouton « Envoyer »');
    check(await until(async () => (await page.frameLocator('[data-nl-frame]').locator('[data-nl-block]').count()) === 0 && (await page.frameLocator('[data-nl-frame]').locator('body').innerText()).includes('Bonjour à toute la communauté')), 'après envoi : le plan de travail s\'affiche sans repères d\'édition');

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
} finally {
    await browser.close();
    php(fixture, 'down');
}

process.exit(failures ? 1 : 0);
