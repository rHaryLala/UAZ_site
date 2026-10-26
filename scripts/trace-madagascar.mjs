// Vectorise la silhouette de Madagascar du sceau (docs/assets/UAZLogo.png) en SVG.
// Étapes : isoler l'île (pixels non noirs, composante connexe autour d'un point sûr, mot « Service » inclus),
// retirer les côtés du triangle qui la frôlent, puis tracer avec potrace.
// Usage : node scripts/trace-madagascar.mjs   →   resources/svg/madagascar.svg
import fs from 'node:fs';
import sharp from 'sharp';
import potrace from 'potrace';

const SRC = 'docs/assets/UAZLogo.png';
const OUT = 'resources/svg/madagascar.svg';
// Boîte englobant l'île dans l'image 1417×1417.
const BOX = { x: 585, y: 505, w: 340, h: 475 };
const SEED = [720, 640];

const { data, info } = await sharp(SRC).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
const W = info.width;
const px = (x, y) => (y * W + x) * 4;

// Côté gauche du triangle : x = 345 + 0,588 (y − 505). On écarte une bande de 24 px autour.
const leftEdge = (y) => 345 + 0.588 * (y - 505);
const rightEdge = (y) => 1065 - 0.588 * (y - 505);
// Pixel « île » : bleu marine dominant (le texte blanc « Service » est exclu puis refermé plus bas).
const bright = (x, y) => {
    const i = px(x, y);
    return (
        data[i + 3] > 200 &&
        data[i + 2] - data[i] > 22 &&
        data[i + 2] > 60 &&
        x > leftEdge(y) + 24 &&
        x < rightEdge(y) - 24 &&
        y > 514
    );
};

const mask = new Uint8Array(BOX.w * BOX.h);
const seen = new Uint8Array(BOX.w * BOX.h);
const at = (x, y) => (y - BOX.y) * BOX.w + (x - BOX.x);

// Remplissage depuis le point sûr (composante principale).
const stack = [SEED];
while (stack.length) {
    const [x, y] = stack.pop();
    if (x < BOX.x || y < BOX.y || x >= BOX.x + BOX.w || y >= BOX.y + BOX.h) continue;
    const k = at(x, y);
    if (seen[k] || !bright(x, y)) continue;
    seen[k] = mask[k] = 1;
    stack.push([x + 1, y], [x - 1, y], [x, y + 1], [x, y - 1]);
}

// Petites îles (Sainte-Marie, Nosy Be…) : composantes isolées de plus de 12 px dans la moitié droite haute.
for (let y = BOX.y; y < BOX.y + 260; y++) {
    for (let x = BOX.x + 240; x < BOX.x + BOX.w; x++) {
        const k = at(x, y);
        if (seen[k] || !bright(x, y)) continue;
        const comp = [];
        const st = [[x, y]];
        while (st.length) {
            const [cx, cy] = st.pop();
            if (cx < BOX.x || cy < BOX.y || cx >= BOX.x + BOX.w || cy >= BOX.y + BOX.h) continue;
            const kk = at(cx, cy);
            if (seen[kk] || !bright(cx, cy)) continue;
            seen[kk] = 1;
            comp.push(kk);
            st.push([cx + 1, cy], [cx - 1, cy], [cx, cy + 1], [cx, cy - 1]);
        }
        if (comp.length > 12) comp.forEach((kk) => (mask[kk] = 1));
    }
}

// Fermeture morphologique (rayon 9 px) : comble les lettres blanches du mot « Service » qui coupent l'île.
const R = 9;
const dilate = (src) => {
    const tmp = new Uint8Array(src.length);
    const dst = new Uint8Array(src.length);
    for (let y = 0; y < BOX.h; y++) for (let x = 0; x < BOX.w; x++) {
        for (let k = -R; k <= R && !tmp[y * BOX.w + x]; k++) if (x + k >= 0 && x + k < BOX.w && src[y * BOX.w + x + k]) tmp[y * BOX.w + x] = 1;
    }
    for (let y = 0; y < BOX.h; y++) for (let x = 0; x < BOX.w; x++) {
        for (let k = -R; k <= R && !dst[y * BOX.w + x]; k++) if (y + k >= 0 && y + k < BOX.h && tmp[(y + k) * BOX.w + x]) dst[y * BOX.w + x] = 1;
    }
    return dst;
};
const invert = (src) => src.map((v) => 1 - v);
const closed = invert(dilate(invert(dilate(mask)))); // dilatation puis érosion
// On ne garde que ce qui était île OU comblé par la fermeture, sans déborder hors de la zone sûre.
for (let i = 0; i < mask.length; i++) {
    const x = (i % BOX.w) + BOX.x;
    const y = Math.floor(i / BOX.w) + BOX.y;
    if (closed[i] && x > leftEdge(y) + 24 && x < rightEdge(y) - 24 && y > 514) mask[i] = 1;
}

// Image binaire (île noire sur fond blanc) pour potrace, avec un cadre de 10 px.
const pad = 10;
const out = Buffer.alloc((BOX.w + pad * 2) * (BOX.h + pad * 2), 255);
for (let y = 0; y < BOX.h; y++) for (let x = 0; x < BOX.w; x++) if (mask[y * BOX.w + x]) out[(y + pad) * (BOX.w + pad * 2) + x + pad] = 0;
const png = await sharp(out, { raw: { width: BOX.w + pad * 2, height: BOX.h + pad * 2, channels: 1 } }).png().toBuffer();

const svg = await new Promise((resolve, reject) =>
    potrace.trace(png, { threshold: 128, turdSize: 6, optTolerance: 0.4, color: 'currentColor', background: 'transparent' }, (err, s) => (err ? reject(err) : resolve(s))),
);

const d = [...svg.matchAll(/ d="([^"]+)"/g)].map((m) => m[1]).join(' ');
const viewBox = `0 0 ${BOX.w + pad * 2} ${BOX.h + pad * 2}`;
fs.mkdirSync('resources/svg', { recursive: true });
fs.writeFileSync(
    OUT,
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${viewBox}" role="img" aria-label="Silhouette de Madagascar"><path fill="currentColor" fill-rule="evenodd" d="${d}"/></svg>\n`,
);
console.log(`${OUT} — ${(d.length / 1024).toFixed(1)} Ko de tracé, viewBox ${viewBox}`);
