// Test de fumée Playwright sur les pages principales : rendu, erreurs console, animations, mouvement réduit,
// absence de JavaScript, formulaire de contact (Livewire), recherche, 404.
// Prérequis : serveur lancé (php artisan serve) avec les données de démonstration et `npm run build` fait.
// Usage : node tests/e2e/smoke.mjs   (BASE_URL pour changer l'adresse)
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const browser = await chromium.launch();
let failures = 0;
const check = (ok, label) => {
    console.log(`${ok ? '✓' : '✗'} ${label}`);
    if (!ok) failures++;
};

async function open(path, options = {}) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 }, ...options });
    const page = await context.newPage();
    const errors = [];
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
    page.on('pageerror', (e) => errors.push(e.message));
    const response = await page.goto(base + path, { waitUntil: 'networkidle' });
    return { page, context, errors, response };
}

// 1. Pages principales : statut, h1 unique, aucune erreur console, pas de défilement horizontal.
for (const path of ['/', '/formations', '/actualites', '/evenements', '/galerie', '/equipe', '/contact', '/recherche', '/a-propos', '/admissions']) {
    const { page, context, errors, response } = await open(path);
    const h1 = await page.locator('h1').count();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    check(response.status() === 200 && h1 === 1 && errors.length === 0 && overflow <= 1, `${path} : 200, un h1, sans erreur console ni débordement${errors.length ? ' — ' + errors.join('; ') : ''}`);
    await context.close();
}

// 2. Accueil avec animations : titre découpé par SplitText, compteurs jusqu'à la valeur finale.
{
    const { page, context } = await open('/');
    await page.waitForFunction(() => document.querySelector('[data-hero-title]')?.hasAttribute('data-split'), null, { timeout: 8000 }).catch(() => {});
    check(await page.locator('[data-hero-title]').evaluate((el) => el.hasAttribute('data-split')), 'accueil : le titre du héros est révélé par GSAP (data-split)');
    check(await page.evaluate(() => document.documentElement.classList.contains('js')), 'accueil : html.js présent');

    await page.locator('[data-key-figures]').scrollIntoViewIfNeeded();
    await page.waitForTimeout(3200);
    const figures = await page.locator('[data-counter]').allTextContents();
    check(figures.map((t) => t.replace(/\s/g, '')).join(',') === '1995,7,180,200', `accueil : compteurs arrivés aux valeurs finales (${figures.join(' | ')})`);
    check(await page.evaluate(() => window.Alpine?.version !== undefined), 'accueil : Alpine démarré');
    check(!(await page.evaluate(() => 'Livewire' in window)), 'accueil : Livewire non chargé (page sans composant Livewire)');
    await context.close();
}

// 3. Mouvement réduit : tout est visible immédiatement, rien ne bouge.
{
    const { page, context } = await open('/', { reducedMotion: 'reduce' });
    await page.waitForTimeout(500);
    const hidden = await page.evaluate(() => [...document.querySelectorAll('[data-reveal], [data-hero-title]')].filter((el) => getComputedStyle(el).opacity !== '1').length);
    check(hidden === 0, `mouvement réduit : aucun élément masqué (${hidden} masqué(s))`);
    const lenis = await page.evaluate(() => Boolean(window.__lenis));
    check(!lenis, 'mouvement réduit : défilement fluide (Lenis) désactivé');
    await context.close();
}

// 4. Sans JavaScript : le contenu est lisible, aucun état initial d'animation ne le cache.
{
    const { page, context } = await open('/', { javaScriptEnabled: false });
    const visible = await page.evaluate(() => {
        const opacity = (selector) => [...document.querySelectorAll(selector)].every((el) => getComputedStyle(el).opacity === '1');
        return {
            h1: document.querySelector('h1')?.innerText.length > 10 && opacity('h1'),
            reveals: opacity('[data-reveal]'),
            figures: [...document.querySelectorAll('[data-counter]')].map((e) => e.textContent.trim()).join(','),
            js: document.documentElement.classList.contains('js'),
            nav: document.querySelectorAll('#site-menu a').length > 5,
        };
    });
    check(visible.h1 && visible.reveals && !visible.js && visible.nav, 'sans JavaScript : titre, sections et navigation visibles');
    check(visible.figures === '1995,7,180,200', `sans JavaScript : chiffres clés affichés (${visible.figures})`);
    await context.close();
}

// 5. Formulaire de contact (Livewire) : erreurs puis envoi réussi.
{
    const { page, context, errors } = await open('/contact');
    await page.waitForFunction(() => window.Livewire, null, { timeout: 8000 }).catch(() => {});
    await page.getByRole('button', { name: 'Envoyer le message' }).click();
    await page.waitForSelector('.field-error', { timeout: 5000 }).catch(() => {});
    check((await page.locator('.field-error').count()) >= 3, 'contact : erreurs de validation affichées (champs vides)');

    await page.getByLabel('Nom complet').fill('Aina Rakoto');
    await page.getByLabel('Adresse e-mail').fill('aina@example.org');
    await page.getByLabel('Sujet').fill('Test de fumée Playwright');
    await page.getByLabel('Message').fill('Bonjour, ceci est un message automatique de test.');
    await page.waitForTimeout(3500); // délai minimal anti-robot
    await page.getByRole('button', { name: 'Envoyer le message' }).click();
    const ok = await page.getByText('Merci, votre message a bien été envoyé.').waitFor({ timeout: 8000 }).then(() => true).catch(() => false);
    check(ok && errors.length === 0, `contact : message envoyé, confirmation affichée${errors.length ? ' — ' + errors.join('; ') : ''}`);
    await context.close();
}

// 6. Recherche en direct.
{
    const { page, context } = await open('/recherche');
    await page.waitForFunction(() => window.Livewire, null, { timeout: 8000 }).catch(() => {});
    await page.getByRole('searchbox').fill('licence');
    const found = await page.getByText('Licence en').first().waitFor({ timeout: 6000 }).then(() => true).catch(() => false);
    check(found, 'recherche : résultats affichés en direct pour « licence »');
    await context.close();
}

// 7. Page 404 soignée.
{
    const { page, context, response } = await open('/une-page-qui-nexiste-pas');
    check(response.status() === 404 && (await page.getByText('Cette page est introuvable.').count()) === 1, '404 : page d\'erreur de marque');
    await context.close();
}

await browser.close();
process.exit(failures ? 1 : 0);
