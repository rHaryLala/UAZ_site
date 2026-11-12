// Éditeur de newsletters dans un vrai navigateur : plan de travail (la newsletter comme elle partira), sélection d'un bloc (même imbriqué), texte
// modifié sur place, barre flottante, ajout instantané, blocs glissés dans une colonne, emplacements « Ajouter ici », colonnes (2 puis 3), bandeau,
// bande d'espace colorée, déplacement, duplication, suppression, annulation, structure en arbre, thème, modèles, image de la médiathèque,
// enregistrement automatique, écran mobile, envoi d'essai, envoi à tous les abonnés actifs (et à eux seuls) depuis l'adresse dédiée, newsletter figée.
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
        await new Promise((r) => setTimeout(r, 120));
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
    check((await page.locator('body').innerText()).includes('E2E newsletter') && (await page.locator('body').innerText()).includes('Brouillon'), 'la liste montre la newsletter en brouillon');
    check((await page.locator('body').innerText()).includes('2 abonnés actifs'), 'la liste annonce le nombre d\'abonnés actifs');
    await page.goto(`${base}/admin/newsletter-editor/${id}`, { waitUntil: 'load' });
    const canvas = page.frameLocator('[data-nl-frame]');
    const top = canvas.locator('td[data-nl-depth="0"][data-nl-block]'); // blocs de premier niveau
    const types = async () => canvas.locator('td[data-nl-depth="0"][data-nl-block]').evaluateAll((els) => els.map((el) => el.dataset.nlType));
    const dbBlocks = () => JSON.parse(tinker('echo json_encode(App\\Models\\Newsletter::find(' + id + ')->blocks);'));
    const saved = () => until(async () => /Enregistré à/.test(await page.locator('[data-nl-status]').innerText()), 8000);
    const addTab = () => page.locator('[data-nl-tab="add"]').click();
    const card = (name) => page.locator('.nl-card', { hasText: name }).first();

    check(await until(async () => (await top.count()) === 3), 'le plan de travail affiche la newsletter (titre, texte, bouton)');
    check((await canvas.locator('body').innerText()).includes('Université Adventiste') && (await canvas.locator('body').innerText()).includes('Se désinscrire'), 'le bandeau et le pied de page (désinscription) sont là');
    await wait(2500); // les blocs du panneau « Ajouter » se préparent d'avance
    if (out) await page.screenshot({ path: path.join(out, 'nl-plan-de-travail.png') });

    // Sélection d'un bloc : contour, barre flottante, onglet « Bloc ».
    await top.nth(0).click({ position: { x: 4, y: 4 } });
    check(await until(async () => (await canvas.locator('.nl-selected').count()) === 1 && (await canvas.locator('.nl-bar').count()) === 1), 'un clic sélectionne le bloc et affiche sa barre d\'outils');
    check((await page.locator('[data-nl-tab="block"]').getAttribute('aria-selected')) === 'true', 'le panneau passe sur les réglages du bloc');

    // Texte modifié sur place, enregistrement automatique.
    const title = top.nth(0).locator('[data-nl-field="text"]');
    await title.click();
    await page.keyboard.press('Control+A');
    await page.keyboard.type('Bonjour à toute la communauté');
    await page.keyboard.press('Enter');
    check(await saved(), 'le texte modifié est enregistré automatiquement');
    check(dbBlocks()[0].data.text.includes('Bonjour à toute la communauté'), 'le nouveau titre est bien dans la base');

    // Réglages : alignement.
    await top.nth(0).click({ position: { x: 4, y: 4 } });
    await page.locator('[data-nl-pane="block"] .nl-seg button', { hasText: 'Centré' }).first().click();
    check(await until(async () => (await top.nth(0).locator('[data-nl-field="text"]').evaluate((el) => getComputedStyle(el).textAlign)) === 'center'), 'réglage : le titre se centre');

    // Ajout : instantané (le bloc est préparé d'avance, il n'attend pas le serveur).
    await addTab();
    const t0 = Date.now();
    await card('Séparateur').click();
    await canvas.locator('[data-nl-type="divider"]').first().waitFor({ timeout: 5000 });
    const delay = Date.now() - t0;
    check(delay < 400, `ajout : le séparateur apparaît tout de suite (${delay} ms)`);
    check((await types()).join() === 'heading,divider,text,button', 'ajout : le bloc se place sous le bloc sélectionné');
    await canvas.locator('.nl-bar [data-op="up"]').click();
    check(await until(async () => (await types())[0] === 'divider'), 'déplacement : le séparateur monte d\'un cran');
    await page.locator('[data-nl-undo]').click();
    check(await until(async () => (await types())[1] === 'divider'), 'annulation : le séparateur est revenu à sa place');
    await page.locator('[data-nl-redo]').click();
    await canvas.locator('[data-nl-type="divider"]').first().click({ position: { x: 4, y: 4 } });
    await canvas.locator('.nl-bar [data-op="del"]').click();
    check(await until(async () => (await top.count()) === 3), 'suppression : le séparateur disparaît');

    // Espace : on peut en changer la couleur (bande de couleur).
    await addTab();
    await card('Espace').click();
    await canvas.locator('[data-nl-type="spacer"]').first().waitFor();
    await page.locator('[data-nl-pane="block"] .ve-field', { hasText: 'Couleur' }).locator('.nl-swatch').nth(9).click(); // #e1ebff (le rang 0 est « transparent »)
    check(await until(async () => (await canvas.locator('[data-nl-type="spacer"] > div').first().evaluate((el) => getComputedStyle(el).backgroundColor)) === 'rgb(225, 235, 255)'), 'espace : la couleur choisie colore la bande');
    check((await canvas.locator('[data-nl-type="spacer"]').first().evaluate((el) => el.getBoundingClientRect().width)) > 630, 'espace : la bande va d\'un bord à l\'autre de la newsletter');
    await canvas.locator('.nl-bar [data-op="del"]').click();

    // Colonnes : « Photo + texte » (image dans la première colonne, titre + texte + bouton dans la seconde).
    await addTab();
    await card('Photo + texte').click();
    await canvas.locator('[data-nl-type="columns"]').first().waitFor();
    const cols = canvas.locator('[data-nl-type="columns"] td[data-nl-list]');
    check((await cols.count()) === 2 && (await cols.nth(0).locator('[data-nl-type="image"], [data-nl-placeholder]').count()) >= 1, 'colonnes : « Photo + texte » pose une image à gauche et le texte à droite');
    const sideBySide = await cols.evaluateAll((els) => Math.abs(els[0].getBoundingClientRect().top - els[1].getBoundingClientRect().top) < 40 && els[1].getBoundingClientRect().left > els[0].getBoundingClientRect().left + 100);
    check(sideBySide, 'colonnes : les deux colonnes sont côte à côte');
    if (out) await page.screenshot({ path: path.join(out, 'nl-colonnes.png') });

    // Un bloc du panneau glissé dans la première colonne.
    await addTab();
    await card('Citation').dragTo(cols.nth(0), { targetPosition: { x: 40, y: 14 } });
    check(await until(async () => (await cols.nth(0).locator('[data-nl-type="quote"]').count()) === 1), 'glisser-déposer : la citation tombe dans la première colonne');

    // Un blocs de texte dans une colonne se modifie sur place.
    const colText = cols.nth(1).locator('[data-nl-field="html"]').first();
    await colText.click();
    await page.keyboard.press('Control+A');
    await page.keyboard.type('Texte dans la colonne');
    await page.keyboard.press('Escape');
    check(await saved(), 'colonne : le texte tapé dans une colonne est enregistré');
    const savedColumns = dbBlocks().find((b) => b.type === 'columns');
    check(savedColumns?.children?.length === 2 && JSON.stringify(savedColumns.children[1]).includes('Texte dans la colonne'), 'colonne : la base conserve l\'arbre (colonnes et blocs imbriqués)');

    // Sélection du conteneur : emplacement « Ajouter ici », réglage du nombre de colonnes.
    await canvas.locator('[data-nl-type="columns"]').first().click({ position: { x: 3, y: 3 } });
    check(await until(async () => (await canvas.locator('.nl-selected').first().getAttribute('data-nl-type')) === 'columns'), 'colonnes : un clic sur le fond sélectionne le conteneur');
    await canvas.locator('[data-nl-slot]').first().click();
    check(await until(async () => /colonne 1/.test(await page.locator('[data-nl-pane="add"] .ve-pane__intro').innerText())), 'emplacement : « Ajouter ici » désigne la colonne visée');
    await card('Titre').click();
    check(await until(async () => (await cols.nth(0).locator('[data-nl-type="heading"]').count()) === 1), 'emplacement : le titre est ajouté dans cette colonne');
    const before = await cols.evaluateAll((els) => els.reduce((n, el) => n + el.querySelectorAll('[data-nl-block]').length, 0));
    await canvas.locator('[data-nl-type="columns"]').first().click({ position: { x: 3, y: 3 } });
    await page.locator('[data-nl-pane="block"] .ve-field', { hasText: 'Nombre de colonnes' }).getByRole('button', { name: '3' }).click();
    check(await until(async () => (await canvas.locator('[data-nl-type="columns"] td[data-nl-list]').count()) === 3), 'colonnes : on passe à trois colonnes');
    await page.locator('[data-nl-pane="block"] .ve-field', { hasText: 'Nombre de colonnes' }).getByRole('button', { name: '2' }).click();
    check(await until(async () => (await canvas.locator('[data-nl-type="columns"] td[data-nl-list]').count()) === 2), 'colonnes : retour à deux colonnes');
    const after = await canvas.locator('[data-nl-type="columns"] td[data-nl-list]').evaluateAll((els) => els.reduce((n, el) => n + el.querySelectorAll('[data-nl-block]').length, 0));
    check(after === before, `colonnes : aucun bloc perdu en changeant le nombre de colonnes (${before} → ${after})`);

    // Monter un bloc dans sa colonne (barre flottante) ; la structure montre l'arbre.
    await cols.nth(0).locator('[data-nl-type="heading"]').first().click({ position: { x: 4, y: 4 } });
    check((await canvas.locator('.nl-bar [data-op="parent"]').count()) === 1, 'barre : un bouton « conteneur » apparaît pour un bloc imbriqué');
    await page.locator('[data-nl-tab="structure"]').click();
    const tree = await page.locator('[data-nl-pane="structure"]').innerText();
    check(/colonne 1/i.test(tree) && /colonne 2/i.test(tree) && /colonnes/i.test(tree), 'structure : l\'arbre montre le conteneur et ses colonnes');

    // Déplacement d'un bloc de premier niveau dans une colonne, par la poignée de la barre.
    await top.nth(0).click({ position: { x: 4, y: 4 } });
    const grip = await canvas.locator('.nl-bar [data-op="grip"]').boundingBox();
    const aim = await cols.nth(1).boundingBox();
    const nestedBefore = await cols.nth(1).locator('[data-nl-block]').count();
    await page.mouse.move(grip.x + grip.width / 2, grip.y + grip.height / 2);
    await page.mouse.down();
    await page.mouse.move(aim.x + 60, aim.y + 20, { steps: 8 });
    await page.mouse.up();
    check(await until(async () => (await canvas.locator('[data-nl-type="columns"] td[data-nl-list]').nth(1).locator('[data-nl-block]').count()) === nestedBefore + 1), 'poignée : un bloc glissé par sa poignée entre dans la colonne visée');
    await page.locator('[data-nl-undo]').click();
    check(await until(async () => (await canvas.locator('[data-nl-type="columns"] td[data-nl-list]').nth(1).locator('[data-nl-block]').count()) === nestedBefore), 'annulation : le déplacement se défait');

    // Duplication d'un conteneur : les blocs copiés ont leurs propres identifiants.
    await canvas.locator('[data-nl-type="columns"]').first().click({ position: { x: 3, y: 3 } });
    await canvas.locator('.nl-bar [data-op="dup"]').click();
    check(await until(async () => (await canvas.locator('[data-nl-type="columns"]').count()) === 2), 'duplication : les colonnes sont copiées avec leur contenu');
    const ids = await canvas.locator('[data-nl-block]').evaluateAll((els) => els.map((el) => el.dataset.nlId));
    check(new Set(ids).size === ids.length, 'duplication : chaque bloc a son identifiant (pas de doublon)');
    await canvas.locator('.nl-bar [data-op="del"]').click();
    check(await until(async () => (await canvas.locator('[data-nl-type="columns"]').count()) === 1), 'suppression : la copie disparaît');

    // Bandeau foncé : le texte passe en clair tout seul.
    await addTab();
    await card('Bandeau foncé').click();
    await canvas.locator('[data-nl-type="section"]').first().waitFor();
    const lightText = await canvas.locator('[data-nl-type="section"] [data-nl-type="text"] [data-nl-field]').first().evaluate((el) => getComputedStyle(el).color);
    check(lightText === 'rgb(241, 237, 228)', 'bandeau : sur fond sombre le texte est clair');
    if (out) await page.screenshot({ path: path.join(out, 'nl-bandeau.png') });

    // Texte enrichi : gras sur le premier paragraphe.
    const paragraph = top.locator('[data-nl-field="html"]').first();
    await paragraph.click();
    await page.keyboard.press('Control+A');
    await page.keyboard.press('Control+B');
    await page.keyboard.press('Escape');
    check(await until(async () => /<(b|strong)>/i.test(await paragraph.innerHTML()), 4000), 'texte : le gras s\'applique sur le plan de travail');

    // Thème : couleur de fond de la newsletter (rechargement complet : le contenu et la structure sont conservés).
    const nbBefore = await canvas.locator('[data-nl-block]').count();
    await page.locator('[data-nl-tab="letter"]').click();
    await page.locator('[data-nl-pane="letter"] .ve-field', { hasText: 'Fond autour de la newsletter' }).locator('.nl-swatch').nth(1).click();
    check(await until(async () => (await canvas.locator('body').evaluate((el) => getComputedStyle(el).backgroundColor)) === 'rgb(251, 249, 244)'), 'thème : le fond autour de la newsletter change');
    check(await until(async () => (await canvas.locator('[data-nl-block]').count()) === nbBefore), 'thème : le contenu reste identique après le rechargement du plan de travail');

    // En-tête : un clic l'ouvre dans le panneau ; disposition de l'UAZ ; hashtag modifié sur la newsletter ; emplacements ; hauteur ; masquage durable.
    const header = canvas.locator('[data-nl-header]');
    await header.click({ position: { x: 250, y: 18 } });
    check(await until(async () => /En-tête de la newsletter/.test(await page.locator('[data-nl-pane="block"]').innerText())), 'en-tête : un clic sur l\'en-tête ouvre ses réglages');
    await page.getByRole('button', { name: 'Disposition de l\'UAZ' }).click();
    check(await until(async () => (await header.locator('td[align="center"] img.nl-hd-logo').count()) === 1 && (await header.locator('[data-nl-theme-field]').count()) === 1), 'en-tête : « Disposition de l\'UAZ » met le sceau au centre et le hashtag à gauche');
    check(await until(async () => (await header.locator('img.nl-hd-ico').count()) >= 1), 'en-tête : les icônes des réseaux sociaux du site s\'affichent');
    if (out) await page.screenshot({ path: path.join(out, 'nl-entete.png') });
    await header.locator('[data-nl-theme-field]').click();
    await page.keyboard.press('Control+A');
    await page.keyboard.type('#UAZNEWS');
    await page.keyboard.press('Enter');
    check(await until(async () => (await page.locator('[data-nl-header-tag]').inputValue()) === '#UAZNEWS'), 'en-tête : le hashtag modifié sur la newsletter apparaît dans le panneau');
    await page.locator('.ve-field', { hasText: 'Hashtag' }).first().getByRole('button', { name: 'Droite' }).click();
    check(await until(async () => (await header.locator('td[align="right"] [data-nl-theme-field]').count()) === 1), 'en-tête : le hashtag passe à droite');
    await page.locator('.ve-field', { hasText: 'Logo' }).first().getByRole('button', { name: 'Masqué' }).click();
    check(await until(async () => (await header.locator('img.nl-hd-logo').count()) === 0), 'en-tête : le logo peut être masqué');
    await page.locator('[data-nl-undo]').click();
    check(await until(async () => (await header.locator('img.nl-hd-logo').count()) === 1), 'en-tête : annuler remet le logo');
    await page.locator('.ve-field', { hasText: 'Hauteur de l\'en-tête' }).locator('input[type=range]').evaluate((el) => { el.value = '210'; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); });
    check(await until(async () => Math.round((await header.evaluate((el) => el.getBoundingClientRect().height))) >= 210), 'en-tête : la hauteur se règle');
    await page.locator('.ve-field', { hasText: 'Taille du logo' }).locator('input[type=range]').evaluate((el) => { el.value = '140'; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); });
    check(await until(async () => Math.round(await header.locator('img.nl-hd-logo').evaluate((el) => el.getBoundingClientRect().width)) === 140), 'en-tête : la taille du logo se règle');
    await saved();
    const savedTheme = JSON.parse(tinker('echo json_encode(App\\Models\\Newsletter::find(' + id + ')->theme);'));
    check(savedTheme.header_tag_text === '#UAZNEWS' && savedTheme.header_tag === 'right' && savedTheme.header_height === 210 && savedTheme.header_logo_size === 140, 'en-tête : les réglages sont enregistrés');
    await page.getByLabel('Afficher l\'en-tête').uncheck();
    check(await until(async () => (await header.count()) === 0), 'en-tête : on peut le masquer');
    await saved();
    await page.reload({ waitUntil: 'load' });
    check(await until(async () => (await top.count()) > 0) && (await header.count()) === 0, 'en-tête : masqué, il le reste après rechargement');
    await page.locator('[data-nl-tab="letter"]').click();
    await page.getByRole('button', { name: 'Régler l\'en-tête…' }).click();
    await page.getByLabel('Afficher l\'en-tête').check();
    const back = await until(async () => (await header.count()) === 1);
    if (!back) console.log('   (en-tête : ' + (await header.count()) + ' | panneau : ' + (await page.locator('[data-nl-pane="block"]').innerText()).slice(0, 80).replace(/\n/g, ' / ') + ' | toast : ' + (await page.locator('[data-nl-toast]').innerText()) + ')');
    check(back, 'en-tête : il se réaffiche avec ses réglages');
    await page.locator('[data-nl-tab="letter"]').click();

    // Modèles : appliquer, puis annuler.
    const sizeBefore = (await top.count());
    await page.getByRole('button', { name: 'Choisir un modèle…' }).click();
    await page.locator('.nl-tpl', { hasText: 'Newsletter simple' }).getByRole('button', { name: /Utiliser/ }).click();
    check(await until(async () => (await top.count()) === 5 && (await types()).join() === 'heading,text,divider,text,button'), 'modèle : « Newsletter simple » remplace la mise en page');
    await page.locator('[data-nl-undo]').click();
    check(await until(async () => (await top.count()) === sizeBefore), 'modèle : Ctrl+Z revient à la newsletter d\'avant');
    await page.locator('[data-nl-redo]').click();
    await page.locator('[data-nl-undo]').click();

    // Image de la médiathèque : un clic sur l'emplacement vide ouvre la médiathèque.
    await canvas.locator('[data-nl-placeholder]').first().click();
    check(await until(async () => page.locator('[data-nl-media]').evaluate((d) => d.open)), 'image : un clic sur l\'emplacement vide ouvre la médiathèque');
    await page.locator('[data-nl-media-grid] .ve-thumb').first().click();
    check(await until(async () => (await canvas.locator('[data-nl-type="image"] img').count()) >= 1), 'image : la photo choisie apparaît dans la newsletter');
    if (out) await page.screenshot({ path: path.join(out, 'nl-plan-image.png') });

    // Écran mobile : les colonnes s'empilent.
    await page.locator('[data-nl-device="mobile"]').click();
    await wait(600);
    check(Math.round((await page.locator('[data-nl-frame-wrap]').boundingBox()).width) === 390, 'mobile : le plan de travail passe à 390 px');
    const stacked = await canvas.locator('[data-nl-type="columns"] td[data-nl-list]').evaluateAll((els) => els[1].getBoundingClientRect().top > els[0].getBoundingClientRect().bottom - 2);
    check(stacked, 'mobile : les colonnes s\'empilent');
    if (out) await page.screenshot({ path: path.join(out, 'nl-plan-mobile.png') });
    await page.locator('[data-nl-device="desktop"]').click();

    // Tout est enregistré, rechargement : la newsletter revient identique (arbre compris).
    await saved();
    const countBefore = await canvas.locator('[data-nl-block]').count();
    await page.reload({ waitUntil: 'load' });
    check(await until(async () => (await canvas.locator('[data-nl-block]').count()) === countBefore), `rechargement : la newsletter est retrouvée telle qu'elle a été laissée (${countBefore} blocs, colonnes comprises)`);

    // Envoi d'essai (courrier en mode « log »).
    await page.locator('[data-nl-open-test]').click();
    await page.locator('[data-nl-test-to]').fill('essai-e2e@example.test');
    await page.locator('[data-nl-test-submit]').click();
    const sent = await until(async () => /Essai envoyé/.test(await page.locator('[data-nl-toast]').innerText()), 8000);
    if (!sent) console.log('   (erreur affichée : « ' + (await page.locator('[data-nl-test-error]').innerText()) + ' » | toast : « ' + (await page.locator('[data-nl-toast]').innerText()) + ' »)');
    check(sent, 'essai : le courrier d\'essai est envoyé');

    // Envoi à tous : la fenêtre nomme l'expéditeur et signale les oublis.
    await page.locator('[data-nl-open-send]').click();
    const sendText = await page.locator('[data-nl-send-text]').innerText();
    check(sendText.includes('2 abonnés actifs') && sendText.includes('newsletter@zurcher.edu.mg'), 'envoi : la fenêtre annonce 2 abonnés actifs et l\'adresse d\'expédition');
    check((await page.locator('[data-nl-send-warn]').innerText()).includes('sans adresse de lien'), 'envoi : la fenêtre signale les boutons sans adresse de lien');
    await page.locator('[data-nl-send-confirm]').check();
    await page.locator('[data-nl-send-submit]').click();
    check(await until(async () => page.url().endsWith('/admin/newsletters'), 12000), 'envoi : retour à la liste après l\'envoi');
    await wait(1500);
    check(tinker('$n=App\\Models\\Newsletter::find(' + id + '); echo $n->status, "|", $n->recipients_count, "|", $n->deliveries()->where("status","sent")->count();') === 'sent|2|2', 'envoi : la newsletter est « envoyée », 2 destinataires, 2 envois réussis');
    check(tinker('echo App\\Models\\NewsletterDelivery::where("newsletter_id",' + id + ')->join("newsletter_subscribers","newsletter_subscribers.id","=","newsletter_deliveries.newsletter_subscriber_id")->whereIn("email",["e2e-nl-attente@example.test","e2e-nl-parti@example.test"])->count();') === '0', 'envoi : ni l\'abonné en attente, ni le désinscrit ne reçoivent la newsletter');
    const log = fs.readFileSync(path.join(path.dirname(fileURLToPath(import.meta.url)), '..', '..', 'storage', 'logs', 'laravel.log'), 'utf8').slice(-600000);
    check(/List-Unsubscribe: <[^>]*desinscription/.test(log), 'envoi : chaque courrier porte son lien de désinscription personnel (en-tête List-Unsubscribe)');
    check(/From:[^<]*\n? ?<newsletter@zurcher\.edu\.mg>/.test(log), 'envoi : les newsletters partent de newsletter@zurcher.edu.mg');

    // Newsletter figée.
    await page.goto(`${base}/admin/newsletter-editor/${id}`, { waitUntil: 'load' });
    check(await until(async () => (await page.locator('.ve-notice').count()) === 1 && (await page.locator('[data-nl-tab]').count()) === 0), 'après envoi : la newsletter est figée (plus d\'onglets de modification)');
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
