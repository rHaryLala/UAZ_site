import js from '@eslint/js';
import globals from 'globals';
import prettier from 'eslint-config-prettier';

export default [
    {
        ignores: [
            'public/**',
            'vendor/**',
            'node_modules/**',
            'storage/**',
            'bootstrap/cache/**',
            'tests/e2e/.cache/**',
        ],
    },
    js.configs.recommended,
    {
        // Code du site : navigateur uniquement.
        files: ['resources/js/**/*.js'],
        languageOptions: { ecmaVersion: 2023, sourceType: 'module', globals: { ...globals.browser } },
        rules: {
            'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
            'no-console': ['warn', { allow: ['warn', 'error'] }],
        },
    },
    {
        // Outils Node (scripts de build, captures Playwright) : console autorisée, DOM dans page.evaluate().
        files: ['scripts/**/*.mjs', 'tests/e2e/**/*.mjs', '*.config.js'],
        languageOptions: { ecmaVersion: 2023, sourceType: 'module', globals: { ...globals.node, ...globals.browser } },
        rules: { 'no-unused-vars': ['error', { argsIgnorePattern: '^_' }] },
    },
    prettier,
];
