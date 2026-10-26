// Génère l'échelle de nuances de la marque (mélange en OKLab) et vérifie les contrastes WCAG.
// Couleurs de base extraites du logo avec scripts/extract-logo-colors.mjs.
// Usage : node scripts/build-palette.mjs [--css]
const BRAND = { navy: '#232E66', gold: '#8A724B', ink: '#060807', white: '#FFFFFF' };

const toRgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255);
const toHex = (rgb) =>
    '#' +
    rgb
        .map((v) =>
            Math.round(Math.min(1, Math.max(0, v)) * 255)
                .toString(16)
                .padStart(2, '0'),
        )
        .join('')
        .toUpperCase();
const lin = (c) => (c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
const delin = (c) => (c <= 0.0031308 ? c * 12.92 : 1.055 * c ** (1 / 2.4) - 0.055);

function toOklab([r, g, b]) {
    [r, g, b] = [lin(r), lin(g), lin(b)];
    const l = Math.cbrt(0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b);
    const m = Math.cbrt(0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b);
    const s = Math.cbrt(0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b);
    return [0.2104542553 * l + 0.793617785 * m - 0.0040720468 * s, 1.9779984951 * l - 2.428592205 * m + 0.4505937099 * s, 0.0259040371 * l + 0.7827717662 * m - 0.808675766 * s];
}
function fromOklab([L, a, b]) {
    const l = (L + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const m = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const s = (L - 0.0894841775 * a - 1.291485548 * b) ** 3;
    return [
        delin(4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s),
        delin(-1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s),
        delin(-0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s),
    ];
}
const mix = (hexA, hexB, t) => {
    const a = toOklab(toRgb(hexA)), b = toOklab(toRgb(hexB));
    return toHex(fromOklab(a.map((v, i) => v + (b[i] - v) * t)));
};
const luminance = (hex) => {
    const [r, g, b] = toRgb(hex).map(lin);
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
export const contrast = (a, b) => {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (hi + 0.05) / (lo + 0.05);
};

/** Éclaircit en OKLCH : la luminosité monte vers `targetL` mais on garde `keep` de la saturation (évite les beiges ternes). */
function lighten(hex, t, keep, targetL = 0.97) {
    const [L, a, b] = toOklab(toRgb(hex));
    const C = Math.hypot(a, b), h = Math.atan2(b, a);
    const L2 = L + (targetL - L) * t;
    const C2 = C * (1 - t * (1 - keep));
    return toHex(fromOklab([L2, C2 * Math.cos(h), C2 * Math.sin(h)]));
}

/**
 * Échelle 50-950 : `base` est placé au cran `at` ; plus clair = éclaircissement OKLCH (saturation conservée à `keep`),
 * plus sombre = mélange avec `shade`.
 */
function scale(base, at, { keep = 0.5, shade = '#000000' } = {}) {
    const steps = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
    const idx = steps.indexOf(at);
    const out = {};
    steps.forEach((step, i) => {
        if (i === idx) out[step] = base;
        else if (i < idx) out[step] = lighten(base, (idx - i) / (idx + 0.35), keep);
        else out[step] = mix(base, shade, (i - idx) / (steps.length - idx + 0.6));
    });
    return out;
}

export const palette = {
    // Le bleu marine du sceau est une couleur très sombre : on le place au cran 800.
    navy: scale(BRAND.navy, 800, { keep: 0.45 }),
    // Le bronze est moyen : cran 600. Les crans 200-400 servent de « or clair » sur fond marine.
    gold: scale(BRAND.gold, 600, { keep: 0.9 }),
    // Encre : noir du logo au cran 950, gris légèrement bleutés pour le texte courant.
    ink: scale('#4B5160', 600, { keep: 0.5, shade: BRAND.ink }),
    // Papier : blanc cassé chaud, lié à l'or du sceau.
    paper: { 50: '#FFFFFF', 100: '#FBF9F4', 200: '#F5F1E8', 300: '#ECE6D8' },
};

if (process.argv.includes('--css')) {
    for (const [name, steps] of Object.entries(palette)) {
        for (const [k, v] of Object.entries(steps)) console.log(`    --color-${name}-${k}: ${v};`);
    }
} else {
    for (const [name, steps] of Object.entries(palette)) console.log(name, JSON.stringify(steps));

    const pairs = [
        ['texte courant', palette.ink[800], palette.paper[100]],
        ['texte courant sur blanc', palette.ink[800], '#FFFFFF'],
        ['texte secondaire', palette.ink[600], palette.paper[100]],
        ['titre marine sur papier', palette.navy[800], palette.paper[100]],
        ['marine sur blanc', palette.navy[800], '#FFFFFF'],
        ['blanc sur marine', '#FFFFFF', palette.navy[800]],
        ['blanc sur marine foncé', '#FFFFFF', palette.navy[900]],
        ['or de marque sur blanc', BRAND.gold, '#FFFFFF'],
        ['or de marque sur papier', BRAND.gold, palette.paper[100]],
        ['or 700 sur papier (texte or)', palette.gold[700], palette.paper[100]],
        ['or 300 sur marine', palette.gold[300], palette.navy[800]],
        ['or 400 sur marine', palette.gold[400], palette.navy[800]],
        ['or 300 sur marine foncé', palette.gold[300], palette.navy[900]],
        ['texte noir sur or 400 (bouton)', BRAND.ink, palette.gold[400]],
        ['texte marine sur or 300 (bouton)', palette.navy[900], palette.gold[300]],
        ['blanc 70 % sur marine ≈ navy-200', palette.navy[200], palette.navy[800]],
        ['marine 100 sur marine 900', palette.navy[100], palette.navy[900]],
    ];
    console.log('\nContrastes (AA texte normal ≥ 4,5 ; grand texte ≥ 3) :');
    for (const [label, fg, bg] of pairs) {
        const c = contrast(fg, bg);
        console.log(`${c >= 7 ? 'AAA' : c >= 4.5 ? 'AA ' : c >= 3 ? 'AA-grand' : 'ÉCHEC'}  ${c.toFixed(2).padStart(5)}:1  ${label}  (${fg} sur ${bg})`);
    }
}
