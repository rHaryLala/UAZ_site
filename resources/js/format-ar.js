// Montants en Ariary à la française : 2 354 000 (espaces insécables, pour ne jamais couper un nombre en fin de ligne).
export function formatAr(value) {
    return Math.round(value)
        .toLocaleString('fr-FR')
        .replace(/[  \s]/g, ' ');
}
