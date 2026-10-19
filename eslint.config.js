const js = require('@eslint/js');
const globals = require('globals');

module.exports = [
    {
        ignores: ['vendor/**', 'node_modules/**', '**/*.min.js']
    },
    js.configs.recommended,
    {
        files: ['assets/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'script',
            globals: {
                ...globals.browser,
                Cropper: 'readonly',
                Stripe: 'readonly',
                Vendove: 'readonly'
            }
        },
        rules: {
            'no-var': 'error',
            'prefer-const': 'error',
            'prefer-arrow-callback': 'error',
            'func-style': ['error', 'declaration'],
            'no-console': 'error',
            eqeqeq: 'error',
            curly: 'error'
        }
    },
    {
        files: ['eslint.config.js'],
        languageOptions: { sourceType: 'commonjs', globals: globals.node }
    }
];
