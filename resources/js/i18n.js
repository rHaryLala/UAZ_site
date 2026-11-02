/**
 * Textes affichés par JavaScript. La page publie le dictionnaire de sa langue (window.uazI18n, voir App\Support\JsStrings) ;
 * sans dictionnaire (français, tests), la phrase française est rendue telle quelle. Les « :nom » sont remplacés par vars.nom.
 */
export function t(text, vars = {}) {
    const dictionary = (typeof window !== 'undefined' && window.uazI18n) || {};

    return (dictionary[text] ?? text).replace(/:([A-Za-z_]\w*)/g, (match, name) => (name in vars ? String(vars[name]) : match));
}

/** Vrai tant qu'aucun dictionnaire n'est publié (page en français) : heures « 8 h 30 », sinon « 8:30 ». */
export const isFrench = () => typeof window === 'undefined' || !window.uazI18n;
