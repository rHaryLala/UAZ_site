// Menus de la cafétéria dans le back-office, de bout en bout dans un vrai navigateur : liste, création d'une semaine (un plat par ligne), apparition sur la
// page publique, modification, désactivation, duplication, suppression. Il crée la semaine PROPOSÉE PAR DÉFAUT (celle qui suit le dernier menu) et sa copie,
// puis supprime ces deux semaines : la base est laissée comme il l'a trouvée.
// Prérequis : serveur lancé, compte admin@uaz.test (mot de passe « password », ou ADMIN_EMAIL / ADMIN_PASSWORD).
// Usage : node tests/e2e/cafeteria-admin.mjs [dossier-captures]
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

const fr = (date, options) => date.toLocaleDateString('fr-FR', options);
const addDays = (date, days) => new Date(date.getTime() + days * 86400000);
/** « Semaine du 1 au 7 décembre 2026 » (le mois n'est répété au début que si la semaine change de mois), comme sur le site. */
const weekLabel = (start) => {
    const end = addDays(start, 6);
    const from = start.getMonth() === end.getMonth() ? `${start.getDate()}` : `${start.getDate()} ${fr(start, { month: 'long' })}`;
    return `Semaine du ${from} au ${end.getDate()} ${fr(end, { month: 'long' })} ${end.getFullYear()}`;
};

const browser = await chromium.launch();
const errors = [];
const created = []; // libellés des semaines à supprimer à la fin
let page;

/** Supprime les semaines créées par ce parcours (appelé aussi quand il s'interrompt : la base ne garde rien). */
async function cleanup() {
    await page.goto(`${base}/admin/cafeteria-menus`, { waitUntil: 'networkidle' });
    for (const target of created) {
        const line = page.locator('table tbody tr', { hasText: target });
        if ((await line.count()) === 0) continue;
        await line.getByRole('button', { name: /Actions/ }).click();
        await page.getByRole('menuitem', { name: /Supprimer/ }).click();
        await page.getByRole('button', { name: /Confirmer|Supprimer/ }).last().click();
        await page.waitForTimeout(1200);
    }
    const left = await Promise.all(created.map((target) => page.locator('table tbody tr', { hasText: target }).count()));
    check(left.every((count) => count === 0), 'nettoyage : les deux semaines de test sont supprimées');
}

try {
    page = await (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
    page.on('pageerror', (e) => errors.push(e.message.slice(0, 140)));
    await page.goto(`${base}/admin/login`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    await page.locator('input[type=email]').fill(process.env.ADMIN_EMAIL ?? 'admin@uaz.test');
    await page.locator('input[type=password]').fill(process.env.ADMIN_PASSWORD ?? 'password');
    await page.getByRole('button', { name: /Connexion|Se connecter/ }).click();
    await page.waitForURL('**/admin');

    // Liste
    await page.goto(`${base}/admin/cafeteria-menus`, { waitUntil: 'networkidle' });
    check((await page.getByRole('heading', { name: /menus de la caf/i }).count()) > 0, 'liste : l\'écran « Menus de la cafétéria » s\'ouvre');
    check((await page.locator('table tbody tr').count()) >= 1 && (await page.getByText(/Semaine du/).count()) >= 1, 'liste : les semaines sont affichées');
    if (out) await page.screenshot({ path: path.join(out, 'admin-1-liste.png') });

    // Création : la semaine proposée par défaut
    await page.goto(`${base}/admin/cafeteria-menus/create`, { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.Livewire);
    const proposed = await page.getByLabel(/Premier jour de la semaine/).first().inputValue(); // jj/mm/aaaa
    check(/^\d{2}\/\d{2}\/\d{4}$/.test(proposed), `création : la semaine suivant le dernier menu est proposée (${proposed})`);
    const [dd, mm, yyyy] = proposed.split('/').map(Number);
    const start = new Date(yyyy, mm - 1, dd, 12);
    const label = weekLabel(start);
    const tabName = new RegExp(fr(start, { weekday: 'long', day: 'numeric', month: 'short' }).replace('.', '\\.'), 'i');
    created.push(label, weekLabel(addDays(start, 7)));

    check((await page.getByRole('tab', { name: tabName }).count()) > 0, 'création : les onglets prennent le nom et la date des jours');
    await page.getByRole('tab', { name: tabName }).click();
    const boxes = page.locator('[id$="meals.0.morning"], [id$="meals.0.noon"], [id$="meals.0.evening"]');
    check((await boxes.count()) === 3, 'création : trois champs (matin, midi, soir) par jour');
    await boxes.nth(0).fill('Plat test e2e 1\nPlat test e2e 2\n\n  Plat test e2e 3  ');
    await boxes.nth(1).fill('Déjeuner test e2e');
    await page.waitForTimeout(500);
    if (out) await page.screenshot({ path: path.join(out, 'admin-2-creation.png'), fullPage: true });
    await page.getByRole('button', { name: /^Créer$/ }).first().click();
    await page.waitForURL(/cafeteria-menus\/\d+\/edit/, { timeout: 15000 });
    check(true, 'création : le menu est enregistré, la fiche s\'ouvre');

    // Rechargement : les plats reviennent un par ligne
    await page.reload({ waitUntil: 'networkidle' });
    await page.getByRole('tab', { name: tabName }).click();
    const saved = await page.locator('[id$="meals.0.morning"]').inputValue();
    check(saved === 'Plat test e2e 1\nPlat test e2e 2\nPlat test e2e 3', `édition : les plats reviennent un par ligne, lignes vides retirées (${JSON.stringify(saved)})`);

    // Sur le site : la semaine apparaît (semaine à venir)
    const siteShows = async (text) => {
        const site = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
        await site.goto(`${base}/vie-etudiante/cafeteria`, { waitUntil: 'load' });
        const found = (await site.locator('#menu').getByText(text).count()) > 0;
        await site.context().close();
        return found;
    };
    check((await siteShows('Plat test e2e 2')) && (await siteShows(label)), 'site : la nouvelle semaine apparaît sur la page Cafétéria avec ses plats');

    // Modification : un plat de plus
    await page.locator('[id$="meals.0.morning"]').fill('Plat test e2e 1\nPlat test e2e 2\nPlat test e2e 3\nPlat test e2e 4');
    await page.getByRole('button', { name: /^Sauvegarder/ }).first().click();
    await page.waitForTimeout(1500);
    check(await siteShows('Plat test e2e 4'), 'modification : le plat ajouté est visible sur le site');

    // Désactivation : la semaine disparaît du site
    await page.goto(`${base}/admin/cafeteria-menus`, { waitUntil: 'networkidle' });
    const row = page.locator('table tbody tr', { hasText: label });
    check((await row.count()) === 1, 'liste : la semaine créée est dans la liste');
    await row.getByRole('switch').click();
    await page.waitForTimeout(1200);
    check(!(await siteShows('Plat test e2e 4')), 'désactivation : la semaine disparaît du site');

    // Duplication : la semaine suivante est proposée, la copie n'est pas affichée
    await row.getByRole('button', { name: /Actions/ }).click();
    await page.getByRole('menuitem', { name: /Dupliquer vers une autre semaine/ }).click();
    await page.waitForTimeout(600);
    const copyDate = await page.getByLabel(/Premier jour de la nouvelle semaine/).first().inputValue();
    check(copyDate !== '' && copyDate !== proposed, `duplication : la semaine suivante est proposée (${copyDate})`);
    await page.getByRole('button', { name: /Créer la copie/ }).click();
    await page.waitForTimeout(1500);
    check((await page.locator('table tbody tr', { hasText: weekLabel(addDays(start, 7)) }).count()) === 1, 'duplication : la copie apparaît dans la liste');
    if (out) await page.screenshot({ path: path.join(out, 'admin-3-liste-apres.png') });

    await cleanup();
    created.length = 0;

    check(errors.length === 0, `aucune erreur JavaScript${errors.length ? ' : ' + errors.join('; ') : ''}`);
} catch (error) {
    console.log(`✗ parcours interrompu : ${error.message.split('\n')[0]}`);
    failures++;
    if (created.length && page) await cleanup().catch((e) => console.log(`✗ nettoyage impossible : ${e.message.split('\n')[0]}`));
} finally {
    await browser.close();
}

process.exit(failures ? 1 : 0);
