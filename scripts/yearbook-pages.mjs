// Transforme un PDF (yearbook…) en images WebP, une par page, pour le livre à feuilleter de la page /yearbook.
// Usage : node scripts/yearbook-pages.mjs <pdf> <dossier-sortie> [largeur=1100]
//   ex. : node scripts/yearbook-pages.mjs Yearbook-2026.pdf public/books/yearbook/2026 1100
// Puis : ajouter l'édition dans config/uaz.php (clé « yearbooks », avec le nombre de pages) et générer les miniatures
// (220 px de large) dans <dossier>/thumbs/, par exemple avec sharp.
// Prérequis : npm i -D pdfjs-dist@4 (le rendu se fait dans Chromium, via Playwright).
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { chromium } from '@playwright/test';

const [pdfPath, out, widthArg] = process.argv.slice(2);
if (!pdfPath || !out) {
    console.error('Usage : node scripts/yearbook-pages.mjs <pdf> <dossier-sortie> [largeur=1100]');
    process.exit(1);
}
const width = Number(widthArg ?? 1100);
const root = path.resolve('.');
fs.mkdirSync(out, { recursive: true });

const server = http
    .createServer((req, res) => {
        const url = decodeURIComponent(req.url.split('?')[0]);
        if (url === '/') return res.writeHead(200, { 'Content-Type': 'text/html' }).end('<!doctype html><html><body></body></html>');
        if (url === '/__pdf') return res.writeHead(200, { 'Content-Type': 'application/pdf' }).end(fs.readFileSync(path.resolve(pdfPath)));
        const file = path.join(root, url);
        if (!file.startsWith(path.join(root, 'node_modules')) || !fs.existsSync(file)) return res.writeHead(404).end();
        res.writeHead(200, { 'Content-Type': file.endsWith('.mjs') ? 'text/javascript' : 'application/octet-stream' }).end(fs.readFileSync(file));
    })
    .listen(8765);

const browser = await chromium.launch();
const page = await browser.newPage();
await page.goto('http://127.0.0.1:8765/');
const count = await page.evaluate(async () => {
    const pdfjs = await import('/node_modules/pdfjs-dist/build/pdf.mjs');
    pdfjs.GlobalWorkerOptions.workerSrc = '/node_modules/pdfjs-dist/build/pdf.worker.mjs';
    window.__pdf = await pdfjs.getDocument('/__pdf').promise;

    return window.__pdf.numPages;
});
console.log(`${count} pages`);

for (let n = 1; n <= count; n++) {
    const data = await page.evaluate(
        async ({ n, width }) => {
            const p = await window.__pdf.getPage(n);
            const base = p.getViewport({ scale: 1 });
            const viewport = p.getViewport({ scale: width / base.width });
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(viewport.width);
            canvas.height = Math.round(viewport.height);
            await p.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;

            return canvas.toDataURL('image/webp', 0.82).split(',')[1];
        },
        { n, width },
    );
    fs.writeFileSync(path.join(out, `page-${String(n).padStart(3, '0')}.webp`), Buffer.from(data, 'base64'));
}

await browser.close();
server.close();
console.log(`Terminé : ${count} images dans ${out}`);
