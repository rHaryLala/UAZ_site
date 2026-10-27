// Audit d'accessibilité automatisé (axe-core, WCAG 2.0/2.1 A et AA + bonnes pratiques) sur les pages principales,
// en mobile et en bureau. Échoue si une violation « sérieuse » ou « critique » subsiste.
// Usage : node tests/e2e/a11y.mjs [chemin…]   (BASE_URL pour changer l'adresse)
import AxeBuilder from '@axe-core/playwright';
import { chromium } from '@playwright/test';

const base = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const paths = process.argv.slice(2).length
    ? process.argv.slice(2)
    : ['/', '/formations', '/actualites', '/evenements', '/galerie', '/equipe', '/contact', '/recherche', '/a-propos', '/admissions/telechargements', '/design-system', '/page-inexistante'];

const browser = await chromium.launch();
let blocking = 0;

for (const path of paths) {
    for (const [label, viewport] of [['mobile', { width: 390, height: 844 }], ['bureau', { width: 1440, height: 900 }]]) {
        const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
        const page = await context.newPage();
        await page.goto(base + path, { waitUntil: 'networkidle' });
        await page.waitForTimeout(600);

        const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice']).analyze();
        const serious = violations.filter((v) => ['serious', 'critical'].includes(v.impact));
        blocking += serious.length;

        console.log(`${violations.length === 0 ? '✓' : serious.length ? '✗' : '~'} ${path} @${label} — ${violations.length} violation(s), ${serious.length} bloquante(s)`);
        for (const v of violations) {
            console.log(`    [${v.impact}] ${v.id} (${v.nodes.length}) : ${v.help}`);
            for (const node of v.nodes.slice(0, 2)) console.log(`        ${node.target.join(' ')}  ${node.html.slice(0, 110).replace(/\s+/g, ' ')}`);
        }
        await context.close();
    }
}

await browser.close();
process.exit(blocking ? 1 : 0);
