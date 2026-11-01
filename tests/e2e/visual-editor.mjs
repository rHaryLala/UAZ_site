// Éditeur visuel dans un vrai navigateur : modification de texte en place, texte riche, ajout / duplication / suppression / déplacement de blocs,
// annulation, réglages, médiathèque, tailles d'écran, brouillon, aperçu et publication. Il travaille sur une page de test créée puis supprimée :
// aucun contenu réel n'est touché. Prérequis : serveur lancé, compte admin de développement. Usage : node tests/e2e/visual-editor.mjs [dossier-captures]
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const out = process.argv[2];
if (out) fs.mkdirSync(out, { recursive: true });
const php = process.env.PHP ?? 'php';
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

/** Exécute du PHP (fichier temporaire : pas de souci de guillemets) et renvoie ce qu'il affiche après « =>OUT ». */
function artisan(code) {
    const file = path.join(os.tmpdir(), `ve-${Date.now()}-${Math.random().toString(36).slice(2)}.php`);
    fs.writeFileSync(file, `<?php\n${code}\n`);
    try {
        const output = execFileSync(php, ['artisan', 'tinker', '--execute', `require '${file.replace(/\\/g, '/')}';`], { encoding: 'utf8' });

        return output.includes('=>OUT') ? output.split('=>OUT').pop().trim() : output.trim();
    } finally {
        fs.rmSync(file, { force: true });
    }
}

const pageId = Number(
    artisan(`
$page = App\\Models\\Page::create([
    'title' => 'Page de test éditeur', 'slug' => 'test-editeur-visuel', 'summary' => 'Résumé de test.', 'status' => 'published', 'published_at' => now()->subDay(),
    'blocks' => [
        ['type' => 'rich_text', 'data' => ['heading' => 'Titre de test', 'content' => '<p>Premier paragraphe.</p>', 'width' => 'normal']],
        ['type' => 'quote', 'data' => ['text' => 'Une citation de test.', 'author' => 'Auteur test', 'role' => '']],
        ['type' => 'key_figures', 'data' => ['title' => 'Chiffres de test', 'items' => [['value' => 42, 'suffix' => '+', 'label' => 'Étudiants']]]],
    ],
]);
echo '=>OUT' . $page->id;
`),
);
const draft = () => JSON.parse(artisan(`$p = App\\Models\\Page::find(${pageId}); echo '=>OUT' . json_encode(['draft' => $p->draft, 'blocks' => $p->blocks, 'title' => $p->title]);`));

/** Attend (jusqu'à 10 s) qu'une condition devienne vraie, puis la renvoie. */
async function until(fn, timeout = 10000) {
    const start = Date.now();
    let value = await fn();
    while (!value && Date.now() - start < timeout) {
        await new Promise((r) => setTimeout(r, 150));
        value = await fn();
    }

    return value;
}

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1500, height: 900 }, locale: 'fr-FR' });
const page = await context.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message.slice(0, 160)));
page.on('dialog', (d) => d.accept());

try {
    await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
    await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: 'Connexion' }).click();
    await page.waitForURL('**/admin', { timeout: 20000 });

    // Accès depuis le formulaire classique
    await page.goto(`${base}/admin/content-pages/pages/${pageId}/edit`, { waitUntil: 'networkidle' });
    check((await page.getByRole('link', { name: /Éditeur visuel/ }).count()) >= 1, 'le bouton « Éditeur visuel » est dans le formulaire de modification');
    await page.getByRole('link', { name: /Éditeur visuel/ }).first().click();
    await page.waitForURL('**/admin/visual-editor/**', { timeout: 20000 });

    const frame = page.frameLocator('[data-ve-frame]');
    const inner = () => page.frames().find((f) => f.url().includes('/canvas'));
    await frame.locator('[data-ve-block]').first().waitFor({ timeout: 20000 });
    check((await frame.locator('[data-ve-block]').count()) === 3, 'la page s\'affiche dans l\'éditeur avec ses 3 blocs');
    check((await page.locator('.ve-item').count()) === 3, 'l\'onglet Structure liste les 3 blocs');
    check((await page.locator('[data-ve-status]').innerText()).includes('aucune modification'), `état : ${await page.locator('[data-ve-status]').innerText()}`);
    if (out) await page.screenshot({ path: path.join(out, '1-editeur.png') });

    // Texte modifié en place
    const heading = frame.locator('.ve-text', { hasText: 'Titre de test' });
    await heading.click();
    await page.keyboard.press('Control+A');
    await page.keyboard.type('Titre modifié en place');
    await page.waitForFunction(() => document.querySelector('[data-ve-status]').textContent.includes('Brouillon enregistré'), null, { timeout: 12000 });
    let state = draft();
    check(state.draft?.blocks?.[0]?.data?.heading === 'Titre modifié en place', 'un titre modifié sur la page est enregistré dans le brouillon');
    check(state.blocks[0].data.heading === 'Titre de test', 'la page publiée n\'est pas modifiée tant qu\'on ne publie pas');
    check((await page.locator('[data-ve-discard]').isVisible()), 'le bouton « Abandonner le brouillon » apparaît');

    // Citation et titre de page modifiables en place aussi
    await frame.locator('.ve-text', { hasText: 'Une citation de test.' }).click();
    await page.keyboard.press('Control+A');
    await page.keyboard.type('Une citation réécrite.');
    await frame.locator('.ve-text', { hasText: 'Page de test éditeur' }).click();
    await page.keyboard.press('Control+A');
    await page.keyboard.type('Titre de page modifié');
    await page.waitForFunction(() => document.querySelector('[data-ve-status]').textContent.includes('Brouillon enregistré'), null, { timeout: 12000 });
    state = draft();
    check(state.draft.blocks[1].data.text === 'Une citation réécrite.' && state.draft.title === 'Titre de page modifié', 'la citation et le titre de la page se modifient aussi en place');
    check((await page.locator('[data-ve-title]').innerText()) === 'Titre de page modifié', 'le titre de la barre du haut suit');

    // Texte riche : mise en forme
    const prose = frame.locator('[data-ve-rich]');
    await prose.click();
    await page.keyboard.press('Control+End');
    await page.keyboard.type(' Phrase ajoutée.');
    await page.keyboard.press('Control+A');
    await frame.locator('.ve-toolbar__btn', { hasText: /^B$/ }).click();
    await page.waitForFunction(() => document.querySelector('[data-ve-status]').textContent.includes('Brouillon enregistré'), null, { timeout: 12000 });
    state = draft();
    check(/Phrase ajoutée\./.test(state.draft.blocks[0].data.content) && /<(strong|b)>/.test(state.draft.blocks[0].data.content), 'le texte riche est modifié et mis en gras');
    if (out) await page.screenshot({ path: path.join(out, '2-texte-riche.png') });

    // Ajouter un bloc
    await page.locator('[data-ve-tab="add"]').click();
    await page.locator('.ve-card', { hasText: 'Appel à l\'action' }).click();
    await frame.locator('[data-ve-block][data-ve-type="cta"]').waitFor({ timeout: 8000 });
    check((await frame.locator('[data-ve-block]').count()) === 4 && (await frame.locator('[data-ve-block]').nth(1).getAttribute('data-ve-type')) === 'cta', 'un bloc « Appel à l\'action » est inséré juste après le bloc sélectionné');
    check(await page.locator('[data-ve-tab="settings"]').evaluate((el) => el.classList.contains('is-on')), 'le panneau passe aux réglages du nouveau bloc');

    // Réglage dans le panneau → la page se met à jour
    await page.locator('.ve-field input[type="text"]').first().fill('Rejoignez la communauté');
    await frame.locator('.ve-text', { hasText: 'Rejoignez la communauté' }).waitFor({ timeout: 8000 });
    check(true, 'un réglage saisi dans le panneau apparaît sur la page');
    if (out) await page.screenshot({ path: path.join(out, '3-bloc-ajoute.png') });

    // Dupliquer, déplacer, supprimer, annuler
    await page.locator('.ve-actions button', { hasText: 'Dupliquer' }).click();
    check(await until(async () => (await frame.locator('[data-ve-block]').count()) === 5), 'un bloc se duplique');
    const count = () => frame.locator('[data-ve-block]').count();
    await page.locator('.ve-actions button', { hasText: /Monter/ }).click();
    check(await until(async () => (await page.locator('.ve-item.is-on').evaluate((el) => [...el.parentElement.children].indexOf(el))) === 1), 'un bloc se monte d\'un cran (la sélection suit)');
    await page.locator('.ve-actions button', { hasText: 'Supprimer' }).click();
    check(await until(async () => (await count()) === 4), 'un bloc se supprime');
    check((await page.locator('[data-ve-toast]').innerText()).includes('Annuler'), 'un message propose d\'annuler la suppression');
    await page.locator('[data-ve-undo]').click();
    check(await until(async () => (await count()) === 5), 'Annuler rétablit le bloc supprimé');
    await page.locator('[data-ve-undo]').click();
    check(await until(async () => (await count()) === 4), 'Annuler une seconde fois revient en arrière (le bloc ajouté, 4 blocs)');
    await page.locator('[data-ve-redo]').click();
    check(await until(async () => (await count()) === 5), 'Rétablir avance de nouveau (5 blocs)');

    // Barre d'outils du bloc dans la page
    await until(async () => (await count()) === 5);
    await frame.locator('[data-ve-block]').nth(1).click({ position: { x: 5, y: 5 } });
    check((await frame.locator('.ve-chrome').count()) === 1 && (await frame.locator('.ve-chrome__label').innerText()).length > 2, 'un clic sur un bloc le sélectionne et affiche sa barre d\'outils');
    await frame.locator('.ve-chrome__btn[title="Descendre"]').click();
    await page.waitForTimeout(1200);
    if (out) await page.screenshot({ path: path.join(out, '4-selection.png') });

    // Glisser-déposer dans la structure
    await page.locator('[data-ve-tab="structure"]').click();
    const items = page.locator('.ve-item');
    const before = await items.first().locator('strong').innerText();
    await items.first().dragTo(items.nth(2));
    check(await until(async () => (await items.nth(2).locator('strong').innerText()) === before), 'glisser un bloc dans la structure change son ordre');

    // Images : le sélecteur de médiathèque
    await page.locator('[data-ve-tab="add"]').click();
    await page.locator('.ve-card', { hasText: 'Image' }).first().click();
    await frame.locator('[data-ve-block][data-ve-type="image"]').waitFor({ timeout: 8000 }).catch(() => {});
    await page.locator('.ve-media-field button', { hasText: 'Choisir' }).click();
    await page.locator('.ve-thumb').first().waitFor({ timeout: 10000 });
    check((await page.locator('.ve-thumb').count()) >= 6, `la médiathèque s'ouvre avec ses images (${await page.locator('.ve-thumb').count()})`);
    if (out) await page.screenshot({ path: path.join(out, '5-mediatheque.png') });
    await page.locator('.ve-thumb').first().click();
    await frame.locator('[data-ve-type="image"] img').first().waitFor({ timeout: 10000 });
    check(true, 'l\'image choisie s\'affiche sur la page');

    // Tailles d'écran
    await page.locator('[data-ve-device="mobile"]').click();
    await page.waitForTimeout(600);
    const width = await page.locator('[data-ve-frame]').evaluate((el) => Math.round(el.getBoundingClientRect().width));
    check(width === 390, `la vue téléphone réduit la page à 390 px (${width})`);
    if (out) await page.screenshot({ path: path.join(out, '6-mobile.png') });
    await page.locator('[data-ve-device="desktop"]').click();
    await page.waitForTimeout(600);

    // Brouillon conservé après rechargement
    await page.waitForFunction(() => document.querySelector('[data-ve-status]').textContent.includes('Brouillon enregistré'), null, { timeout: 12000 });
    const savedCount = draft().draft.blocks.length;
    await page.reload({ waitUntil: 'load' });
    await frame.locator('[data-ve-block]').first().waitFor({ timeout: 20000 });
    check((await frame.locator('[data-ve-block]').count()) === savedCount && (await page.locator('[data-ve-status]').innerText()).includes('Brouillon'), `le brouillon est retrouvé après rechargement (${savedCount} blocs)`);

    // Aperçu fidèle (nouvel onglet)
    const [preview] = await Promise.all([context.waitForEvent('page'), page.locator('[data-ve-preview]').click()]);
    await preview.waitForLoadState('load');
    check((await preview.locator('h1').first().innerText()).includes('Titre de page modifié'), 'l\'aperçu montre le brouillon avec la vraie mise en page du site');
    await preview.close();
    const live = await context.newPage();
    await live.goto(`${base}/test-editeur-visuel`, { waitUntil: 'load' });
    check((await live.locator('h1').first().innerText()).includes('Page de test éditeur'), 'le site public montre toujours la version publiée');
    await live.close();

    // Publication
    await page.locator('[data-ve-publish]').click();
    await page.waitForFunction(() => document.querySelector('[data-ve-status]').textContent.includes('Publié'), null, { timeout: 12000 });
    state = draft();
    check(state.draft === null && state.title === 'Titre de page modifié' && state.blocks.length === savedCount, 'la publication applique le brouillon à la page et le vide');
    const live2 = await context.newPage();
    await live2.goto(`${base}/test-editeur-visuel`, { waitUntil: 'load' });
    check((await live2.locator('h1').first().innerText()).includes('Titre de page modifié') && (await live2.locator('[data-ve-block]').count()) === 0, 'le site public affiche la page publiée, sans aucun repère d\'édition');
    await live2.close();

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    if (out) await page.screenshot({ path: path.join(out, 'erreur.png') }).catch(() => {});
    failures++;
} finally {
    await browser.close();
    artisan(`App\\Models\\Page::find(${pageId})?->delete(); echo '=>OUT ok';`);
}

process.exit(failures ? 1 : 0);
